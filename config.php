<?php
// ============================================================
// Pixella Web Viewer — config.php
// ファイル・ディレクトリの場所を設定してください
// ============================================================

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
