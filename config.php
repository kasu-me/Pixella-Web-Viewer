<?php
// ============================================================
// Pixella Web Viewer — config.php
// ファイル・ディレクトリの場所を設定してください
// ============================================================

// ページタイトルのサフィックス
define('PIXELLA_TITLE_SUFFIX', "");

// faviconファイルの場所 (このファイルからの相対パス)
define('FAVICON_FILE', './favicon.ico');

// JSONファイルの場所 (このファイルからの相対パス)
define('DATA_FILE', __DIR__ . '/data/pixella_export.json');

// URL 上の画像ディレクトリ（末尾スラッシュ必須）
define('IMAGES_DIR',    'images/');

// ファイルシステム上の画像ディレクトリ (filemtime 取得用)
define('IMAGES_FS_DIR',     __DIR__ . '/images/');

// ファイルシステム上のサムネイルディレクトリ
define('THUMBNAILS_FS_DIR', __DIR__ . '/thumbnails/');

// グループ内画像のデフォルトソート順
// 'same'          : 一覧と同じ（デフォルト）
// 'mtime_asc'     : 更新時刻 昇順
// 'mtime_desc'    : 更新時刻 降順
// 'ctime_asc'     : 追加時刻 昇順
// 'ctime_desc'    : 追加時刻 降順
// 'filename_asc'  : ファイル名 昇順
// 'filename_desc' : ファイル名 降順
define('GROUP_SORT_DEFAULT', 'same');
