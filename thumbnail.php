<?php
// ============================================================
// Pixella Web Viewer — thumbnail.php
// サムネイルをオンデマンドで生成・配信する
// GET パラメータ: f=<元画像ファイル名>
// ============================================================

require __DIR__ . '/config.php';

// ── ファイル名検証 ────────────────────────────────────────────
$filename = isset($_GET['f']) ? (string)$_GET['f'] : '';

// 空・パス区切り文字・ドットドット を拒否
if ($filename === '' || strpbrk($filename, '/\\') !== false || strpos($filename, '..') !== false) {
    http_response_code(400);
    exit;
}

// 許可する元画像の拡張子
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
    http_response_code(415);
    exit;
}

// ── パス解決 ──────────────────────────────────────────────────
$src_path   = IMAGES_FS_DIR     . $filename;
$thumb_name = preg_replace('/\.[^.]+$/', '.jpg', $filename);
$thumb_path = THUMBNAILS_FS_DIR . $thumb_name;

// ── ディレクトリトラバーサル対策 (realpath チェック) ──────────
$real_images_dir = realpath(IMAGES_FS_DIR);
$real_src        = realpath($src_path);

if ($real_images_dir === false) {
    http_response_code(500);
    exit;
}
if ($real_src === false || strpos($real_src, $real_images_dir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    exit;
}

// ── サムネイルが存在すれば即配信 ─────────────────────────────
if (file_exists($thumb_path)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($thumb_path);
    exit;
}

// ── 元画像の読み込み ──────────────────────────────────────────
$info = @getimagesize($real_src);
if ($info === false) {
    http_response_code(415);
    exit;
}

switch ($info[2]) {
    case IMAGETYPE_JPEG:
        $src_img = @imagecreatefromjpeg($real_src);
        break;
    case IMAGETYPE_PNG:
        $src_img = @imagecreatefrompng($real_src);
        break;
    default:
        http_response_code(415);
        exit;
}

if ($src_img === false) {
    http_response_code(500);
    exit;
}

// ── リサイズ計算 (長辺 150px、縦横比維持) ────────────────────
$orig_w = imagesx($src_img);
$orig_h = imagesy($src_img);
$max    = 150;

if ($orig_w >= $orig_h) {
    $new_w = $max;
    $new_h = (int)max(1, round($orig_h * $max / $orig_w));
} else {
    $new_h = $max;
    $new_w = (int)max(1, round($orig_w * $max / $orig_h));
}

$dst_img = imagecreatetruecolor($new_w, $new_h);

// PNG の透過部分は白背景に合成
if ($info[2] === IMAGETYPE_PNG) {
    $white = imagecolorallocate($dst_img, 255, 255, 255);
    imagefill($dst_img, 0, 0, $white);
}

imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
imagedestroy($src_img);

// ── サムネイルを保存 ──────────────────────────────────────────
if (!is_dir(THUMBNAILS_FS_DIR)) {
    mkdir(THUMBNAILS_FS_DIR, 0755, true);
}

imagejpeg($dst_img, $thumb_path, 85);
imagedestroy($dst_img);

// ── 配信 ──────────────────────────────────────────────────────
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=31536000, immutable');
readfile($thumb_path);
