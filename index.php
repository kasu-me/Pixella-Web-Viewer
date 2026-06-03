<?php
// ============================================================
// Pixella Web Viewer — index.php
// ============================================================

require __DIR__ . '/config.php';

$images_json = '[]';
$tags_json   = '[]';
$groups_json = '[]';
$error       = null;

if (!file_exists(DATA_FILE)) {
    $error = 'データファイルが見つかりません: ' . DATA_FILE;
} else {
    $raw = file_get_contents(DATA_FILE);
    if ($raw === false) {
        $error = 'データファイルの読み込みに失敗しました。';
    } else {
        $data = json_decode($raw, true);
        if ($data === null) {
            $error = 'JSON のパースに失敗しました。ファイルが正しい形式か確認してください。';
        } else {
            // id → filename / ctime のルックアップテーブル
            $id_to_filename = [];
            $id_to_ctime    = [];
            foreach ($data['images'] ?? [] as $img) {
                if (isset($img['id'], $img['path'])) {
                    $id_to_filename[(int)$img['id']] = basename($img['path']);
                    $id_to_ctime[(int)$img['id']]    = isset($img['ctime']) ? (float)$img['ctime'] : null;
                }
            }

            // path → filename / mtime / ctime に変換
            $images = array_values(array_map(function ($img) {
                $filename = basename($img['path'] ?? '');
                $fs_path  = IMAGES_FS_DIR . $filename;
                $mtime    = $filename !== '' ? (@filemtime($fs_path) ?: null) : null;
                return [
                    'id'       => (int)($img['id'] ?? 0),
                    'filename' => $filename,
                    'groupId'  => isset($img['group_id']) ? (int)$img['group_id'] : null,
                    'tags'     => array_values($img['tags'] ?? []),
                    'mtime'    => $mtime,
                    'ctime'    => isset($img['ctime']) ? (float)$img['ctime'] : null,
                ];
            }, $data['images'] ?? []));

            $tags = array_values(array_map(function ($t) {
                return [
                    'name'  => (string)($t['name'] ?? ''),
                    'color' => isset($t['color']) ? (string)$t['color'] : null,
                ];
            }, $data['tags'] ?? []));

            // グループ: カバー画像ファイル名を解決、先頭画像の mtime/ctime を付与
            $groups = array_values(array_map(function ($g) use ($id_to_filename, $id_to_ctime) {
                $cover = null;
                if (isset($g['cover_image_id']) && $g['cover_image_id'] !== null) {
                    $cover = $id_to_filename[(int)$g['cover_image_id']] ?? null;
                }
                if ($cover === null && !empty($g['image_ids'])) {
                    $first_id = (int)($g['image_ids'][0]);
                    $cover = $id_to_filename[$first_id] ?? null;
                }
                // 代表ファイル (先頭) の mtime / ctime
                $rep_id    = !empty($g['image_ids']) ? (int)($g['image_ids'][0]) : null;
                $rep_fname = $rep_id !== null ? ($id_to_filename[$rep_id] ?? null) : null;
                $mtime     = ($rep_fname !== null && $rep_fname !== '')
                    ? (@filemtime(IMAGES_FS_DIR . $rep_fname) ?: null) : null;
                $ctime     = $rep_id !== null ? ($id_to_ctime[$rep_id] ?? null) : null;
                return [
                    'id'       => (int)($g['id'] ?? 0),
                    'name'     => (string)($g['name'] ?? ''),
                    'cover'    => $cover,
                    'tags'     => array_values($g['tags'] ?? []),
                    'imageIds' => array_values(array_map('intval', $g['image_ids'] ?? [])),
                    'mtime'    => $mtime,
                    'ctime'    => $ctime,
                ];
            }, $data['groups'] ?? []));

            // JSON_HEX_TAG で </script> インジェクションを防止
            $images_json = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
            $tags_json   = json_encode($tags,   JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
            $groups_json = json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        }
    }
}
?><!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pixella Web Viewer<?php if (defined('PIXELLA_TITLE_SUFFIX')) { echo ' - ' . htmlspecialchars(PIXELLA_TITLE_SUFFIX, ENT_QUOTES, 'UTF-8'); } ?></title>
  <style>
    /* ── Variables: dark (default) ──────────────────────── */
    :root {
      --bg:                  #111318;
      --surface:             #1b1d27;
      --card:                #22253a;
      --accent:              #6c8fff;
      --accent-dim:          #6c8fff33;
      --text:                #e8eaf0;
      --text-muted:          #8b90a8;
      --border:              #2e3148;
      --tag-bg:              #1e2540;
      --tag-selected:        #3a4f99;
      --tag-selected-border: #6c8fff;
      --danger:              #ff5c5c;
      --group-badge-bg:      rgba(0,0,0,0.60);
      --group-name-bg:       rgba(0,0,0,0.68);
      --shadow:              rgba(0,0,0,0.45);
    }

    /* ── Variables: light (OS default) ─────────────────── */
    @media (prefers-color-scheme: light) {
      :root:not([data-theme="dark"]) {
        --bg:                  #f2f4fb;
        --surface:             #ffffff;
        --card:                #e8eaf6;
        --accent:              #3d58d4;
        --accent-dim:          #3d58d420;
        --text:                #1a1d2e;
        --text-muted:          #636889;
        --border:              #cdd0e8;
        --tag-bg:              #dde0f4;
        --tag-selected:        #3d58d4;
        --tag-selected-border: #2c46bf;
        --danger:              #cc2c2c;
        --group-badge-bg:      rgba(0,0,0,0.45);
        --group-name-bg:       rgba(0,0,0,0.50);
        --shadow:              rgba(0,0,0,0.15);
      }
    }

    /* ── Variables: manual dark override ───────────────── */
    :root[data-theme="dark"] {
      --bg:                  #111318;
      --surface:             #1b1d27;
      --card:                #22253a;
      --accent:              #6c8fff;
      --accent-dim:          #6c8fff33;
      --text:                #e8eaf0;
      --text-muted:          #8b90a8;
      --border:              #2e3148;
      --tag-bg:              #1e2540;
      --tag-selected:        #3a4f99;
      --tag-selected-border: #6c8fff;
      --danger:              #ff5c5c;
      --group-badge-bg:      rgba(0,0,0,0.60);
      --group-name-bg:       rgba(0,0,0,0.68);
      --shadow:              rgba(0,0,0,0.45);
    }

    /* ── Variables: manual light override ──────────────── */
    :root[data-theme="light"] {
      --bg:                  #f2f4fb;
      --surface:             #ffffff;
      --card:                #e8eaf6;
      --accent:              #3d58d4;
      --accent-dim:          #3d58d420;
      --text:                #1a1d2e;
      --text-muted:          #636889;
      --border:              #cdd0e8;
      --tag-bg:              #dde0f4;
      --tag-selected:        #3d58d4;
      --tag-selected-border: #2c46bf;
      --danger:              #cc2c2c;
      --group-badge-bg:      rgba(0,0,0,0.45);
      --group-name-bg:       rgba(0,0,0,0.50);
      --shadow:              rgba(0,0,0,0.15);
    }

    /* ── Reset / Base ───────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', 'Hiragino Sans', 'Yu Gothic', sans-serif;
      min-height: 100vh;
      transition: background 0.2s, color 0.2s;
    }
    button { cursor: pointer; font-family: inherit; }

    /* ── Header ─────────────────────────────────────────── */
    header {
      background: var(--surface);
      padding: 12px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 10px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 6px var(--shadow);
    }
    header h1 {
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--text);
      letter-spacing: 0.02em;
      flex: 1;
    }
    /* テーマトグル */
    .theme-toggle {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-muted);
      border-radius: 6px;
      padding: 5px 10px;
      font-size: 1rem;
      line-height: 1;
      transition: color 0.15s, border-color 0.15s, background 0.15s;
    }
    .theme-toggle:hover {
      color: var(--accent);
      border-color: var(--accent);
      background: var(--accent-dim);
    }

    /* ── Breadcrumb bar (グループビュー) ─────────────────── */
    .breadcrumb-bar {
      background: var(--surface);
      padding: 10px 24px;
      border-bottom: 1px solid var(--border);
      display: none;
      align-items: center;
      gap: 10px;
    }
    .breadcrumb-bar.visible { display: flex; }
    .breadcrumb-back {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-muted);
      border-radius: 6px;
      padding: 5px 12px;
      font-size: 0.85rem;
      transition: color 0.15s, border-color 0.15s;
    }
    .breadcrumb-back:hover { color: var(--accent); border-color: var(--accent); }
    .breadcrumb-sep { color: var(--text-muted); font-size: 0.85rem; }
    .breadcrumb-home {
      color: var(--text-muted);
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
    }
    .breadcrumb-home:hover { color: var(--accent); text-decoration: underline; }
    .breadcrumb-current {
      color: var(--text);
      font-size: 0.85rem;
      font-weight: 600;
	  & a{
		color: var(--text);
		text-decoration: none;
		&:hover { text-decoration: underline; }
	  }
    }

    /* ── Group tags bar ──────────────────────────────────── */
    .group-tags-bar {
      background: var(--surface);
      padding: 8px 24px;
      border-bottom: 1px solid var(--border);
      display: none;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
    }
    .group-tags-bar.visible { display: flex; }

    /* ── Search area ─────────────────────────────────────── */
    .search-area {
      background: var(--surface);
      padding: 14px 24px 12px;
      border-bottom: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .search-area.hidden { display: none; }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
    }

    /* 選択済みタグ */
    #selected-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      flex: 1;
      min-width: 0;
      align-items: center;
    }
    .placeholder-text {
      color: var(--text-muted);
      font-size: 0.88rem;
    }

    /* AND / OR toggle */
    .mode-toggle {
      display: flex;
      border-radius: 6px;
      overflow: hidden;
      border: 1px solid var(--border);
      flex-shrink: 0;
    }
    .mode-toggle button {
      padding: 6px 14px;
      background: transparent;
      color: var(--text-muted);
      border: none;
      font-size: 0.82rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      transition: background 0.15s, color 0.15s;
    }
    .mode-toggle button + button { border-left: 1px solid var(--border); }
    .mode-toggle button.active { background: var(--accent); color: #fff; }

    /* Clear button */
    .clear-btn {
      padding: 6px 12px;
      background: transparent;
      color: var(--text-muted);
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.82rem;
      transition: color 0.15s, border-color 0.15s;
      flex-shrink: 0;
    }
    .clear-btn:hover { color: var(--danger); border-color: var(--danger); }

    /* Tag filter input */
    .tag-search-input {
      padding: 6px 12px;
      background: var(--card);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: 6px;
      font-size: 0.88rem;
      width: 190px;
      transition: border-color 0.15s;
    }
    .tag-search-input::placeholder { color: var(--text-muted); }
    .tag-search-input:focus { outline: none; border-color: var(--accent); }

    /* Available tags list */
    .available-tags-wrap { display: flex; flex-direction: column; gap: 6px; }
    .available-tags-header { color: var(--text-muted); font-size: 0.8rem; }
    #available-tags-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      max-height: 130px;
      overflow-y: auto;
      padding-right: 4px;
    }
    #available-tags-list::-webkit-scrollbar { width: 4px; }
    #available-tags-list::-webkit-scrollbar-track { background: transparent; }
    #available-tags-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

    /* ── Chip ────────────────────────────────────────────── */
    .chip {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.82rem;
      line-height: 1.5;
      user-select: none;
      transition: opacity 0.15s;
    }
    .chip.selected {
      background: var(--tag-selected);
      color: #fff;
      border: 1px solid var(--tag-selected-border);
    }
    .chip.available {
      background: var(--tag-bg);
      color: var(--text-muted);
      border: 1px solid var(--border);
      cursor: pointer;
    }
    .chip.available:hover {
      color: var(--text);
      border-color: var(--accent);
      background: var(--accent-dim);
    }
    .chip .remove-btn {
      font-size: 1rem;
      line-height: 1;
      opacity: 0.7;
      flex-shrink: 0;
    }
    .chip .remove-btn:hover { opacity: 1; }

    /* ── Info bar ─────────────────────────────────────────── */
    .info-bar {
      padding: 8px 24px;
      color: var(--text-muted);
      font-size: 0.82rem;
      border-bottom: 1px solid var(--border);
    }

    /* ── Image Grid ───────────────────────────────────────── */
    .grid {
      padding: 20px 24px;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 10px;
    }
    @media (max-width: 600px) {
      .grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); padding: 12px; }
    }

    /* ── Grid item (共通) ─────────────────────────────────── */
    .grid-item {
      aspect-ratio: 1;
      overflow: hidden;
      border-radius: 6px;
      background: var(--card);
      cursor: pointer;
      position: relative;
      border: 1px solid var(--border);
      transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
    }
    .grid-item:hover {
      border-color: var(--accent);
      transform: scale(1.025);
      box-shadow: 0 4px 16px var(--shadow);
    }
    .grid-item > img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    /* タグオーバーレイ (画像タイル) */
    .grid-item .tag-overlay {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      background: linear-gradient(transparent, rgba(0,0,0,0.75));
      padding: 22px 6px 6px;
      opacity: 0;
      transition: opacity 0.2s;
      display: flex;
      flex-wrap: wrap;
      gap: 3px;
    }
    .grid-item:hover .tag-overlay { opacity: 1; }
    .tag-overlay .tag-label {
      font-size: 0.68rem;
      padding: 2px 6px;
      border-radius: 10px;
      background: rgba(255,255,255,0.18);
      color: #fff;
    }

    /* ── Group tile ───────────────────────────────────────── */
    .grid-item.group-tile { border-color: var(--accent-dim); }
    .grid-item.group-tile:hover { border-color: var(--accent); }

    /* グループバッジ (⊞) */
    .group-badge {
      position: absolute;
      top: 6px;
      left: 6px;
      background: var(--group-badge-bg);
      color: #fff;
      font-size: 0.82rem;
      padding: 2px 7px;
      border-radius: 4px;
      backdrop-filter: blur(4px);
      pointer-events: none;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .group-badge-icon { font-style: normal; }

    /* グループ画像枚数バッジ (右上) */
    .group-count {
      position: absolute;
      top: 6px;
      right: 6px;
      background: var(--group-badge-bg);
      color: rgba(255,255,255,0.85);
      font-size: 0.72rem;
      padding: 2px 6px;
      border-radius: 4px;
      backdrop-filter: blur(4px);
      pointer-events: none;
    }

    /* グループ名 (下部) */
    .group-name-bar {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      background: var(--group-name-bg);
      color: #fff;
      font-size: 0.82rem;
      font-weight: 600;
      padding: 6px 8px;
      backdrop-filter: blur(4px);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      pointer-events: none;
    }

    /* グループ: タグありの場合、ホバーでタグを表示 */
    .group-tile .tag-overlay {
      /* 名前バー分だけ上に余白を確保 */
      padding-bottom: 30px;
    }
    /* タグなし or 非ホバー時はグループ名を常時表示 */
    .group-tile .group-name-bar {
      transition: opacity 0.2s;
    }
    /* タグありグループ: ホバーでタグ表示→名前バーを半透明に */
    .group-tile.has-tags:hover .group-name-bar {
      opacity: 0;
    }

    /* カバー画像なし時のプレースホルダー */
    .group-no-cover {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      color: var(--text-muted);
      font-size: 2rem;
    }
    .group-no-cover span { font-size: 0.78rem; }

    /* ── Empty state ──────────────────────────────────────── */
    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 60px 20px;
      color: var(--text-muted);
    }
    .empty-state p { font-size: 1rem; margin-bottom: 6px; }

    /* ── Pagination ───────────────────────────────────────── */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 4px;
      padding: 24px 16px 36px;
      flex-wrap: wrap;
    }
    .pagination-top {
      padding: 10px 16px;
      border-bottom: 1px solid var(--border);
    }
    .pagination button {
      padding: 7px 13px;
      background: var(--surface);
      color: var(--text-muted);
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.88rem;
      min-width: 38px;
      transition: background 0.15s, color 0.15s, border-color 0.15s;
    }
    .pagination button:hover:not(:disabled):not(.active) {
      border-color: var(--accent);
      color: var(--accent);
    }
    .pagination button.active {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
      font-weight: 600;
    }
    .pagination button:disabled { opacity: 0.35; cursor: default; }
    .pagination .ellipsis { padding: 7px 6px; color: var(--text-muted); font-size: 0.88rem; }

    /* ── Lightbox ─────────────────────────────────────────── */
    .lightbox {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.92);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    .lightbox.open { display: flex; }

    .lightbox-inner {
      max-width: 92vw;
      max-height: 92vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }
    .lightbox-inner img {
      max-width: 88vw;
      max-height: 78vh;
      object-fit: contain;
      border-radius: 4px;
      display: block;
    }
    .lb-meta {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      max-width: 88vw;
    }
    #lb-filename {
      color: rgba(255,255,255,0.55);
      font-size: 0.8rem;
      max-width: 88vw;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    #lb-tags { display: flex; flex-wrap: wrap; gap: 5px; justify-content: center; }
    #lb-tags .chip {
      background: rgba(255,255,255,0.1);
      color: #ddd;
      border: 1px solid rgba(255,255,255,0.15);
    }
    #lb-title {
      color: rgba(255,255,255,0.9);
      font-size: 1rem;
      font-weight: 500;
      max-width: 88vw;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      text-align: center;
	  &:empty { display: none; }
	  & a{
		color: rgba(255,255,255,0.9);
		text-decoration: none;
		&:hover { text-decoration: underline; }
	  }
    }

    .lightbox-close {
      position: fixed;
      top: 14px;
      right: 18px;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.15);
      color: #fff;
      font-size: 1.4rem;
      line-height: 1;
      padding: 6px 10px;
      border-radius: 6px;
      transition: background 0.15s;
    }
    .lightbox-close:hover { background: rgba(255,255,255,0.22); }

    .lightbox-nav {
      position: fixed;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      color: #fff;
      font-size: 1.8rem;
      padding: 16px 10px;
      border-radius: 6px;
      transition: background 0.15s;
      line-height: 1;
    }
    .lightbox-nav:hover:not(:disabled) { background: rgba(255,255,255,0.18); }
    .lightbox-nav:disabled { opacity: 0.2; cursor: default; }
    .lightbox-nav.prev { left: 8px; }
    .lightbox-nav.next { right: 8px; }

    /* ── Lightbox spinner ───────────────────────────────── */
    .lb-spinner {
      display: none;
      width: 44px;
      height: 44px;
      border: 3px solid rgba(255,255,255,0.15);
      border-top-color: rgba(255,255,255,0.85);
      border-radius: 50%;
      animation: lb-spin 0.7s linear infinite;
      flex-shrink: 0;
    }
    .lb-spinner.visible { display: block; }
    @keyframes lb-spin { to { transform: rotate(360deg); } }

    /* ── Sort bar ─────────────────────────────────────────── */
    .sort-bar {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 8px 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .sort-label {
      color: var(--text-muted);
      font-size: 0.82rem;
      flex-shrink: 0;
    }
    .sort-select {
      padding: 5px 10px;
      background: var(--card);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: 6px;
      font-size: 0.82rem;
      cursor: pointer;
      transition: border-color 0.15s;
    }
    .sort-select:focus { outline: none; border-color: var(--accent); }
    .sort-dir-btn {
      padding: 5px 11px;
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-muted);
      border-radius: 6px;
      font-size: 0.82rem;
      flex-shrink: 0;
      transition: color 0.15s, border-color 0.15s, background 0.15s;
    }
    .sort-dir-btn:hover {
      color: var(--accent);
      border-color: var(--accent);
      background: var(--accent-dim);
    }

    /* ── Error box ────────────────────────────────────────── */
    .error-box {
      margin: 32px 24px;
      padding: 16px 20px;
      background: #2d0b0b;
      border: 1px solid #7a2020;
      border-radius: 8px;
      color: #f08080;
      font-size: 0.9rem;
    }
  </style>
  <link rel="icon" href="<?= htmlspecialchars(FAVICON_FILE, ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">
</head>
<body>

<header>
  <h1>Pixella Web Viewer</h1>
  <button id="btn-theme" class="theme-toggle" aria-label="テーマ切り替え" title="テーマ切り替え">🌙</button>
</header>

<?php if ($error): ?>
<div class="error-box"><strong>エラー:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php else: ?>

<!-- パンくずバー (グループビュー時のみ表示) -->
<div id="breadcrumb-bar" class="breadcrumb-bar" aria-label="パンくずリスト">
  <button id="btn-back" class="breadcrumb-back">← 戻る</button>
  <a id="bc-home" class="breadcrumb-home" href="#" role="button">ホーム</a>
  <span class="breadcrumb-sep">›</span>
  <span id="bc-group-name" class="breadcrumb-current"></span>
</div>

<!-- グループタグバー (グループビュー時にタグがある場合のみ表示) -->
<div id="group-tags-bar" class="group-tags-bar"></div>

<!-- 検索エリア (ホームビュー時のみ表示) -->
<div id="search-area" class="search-area">
  <div class="search-row">
    <div id="selected-tags">
      <span class="placeholder-text">タグを選択して絞り込み…</span>
    </div>
    <div class="mode-toggle">
      <button id="btn-and" class="active" title="すべてのタグに一致">AND</button>
      <button id="btn-or" title="いずれかのタグに一致">OR</button>
    </div>
    <button id="btn-clear" class="clear-btn">クリア</button>
    <input type="text" id="tag-filter-input" class="tag-search-input" placeholder="タグを検索…" autocomplete="off">
  </div>
  <div class="available-tags-wrap">
    <div class="available-tags-header">利用可能なタグ</div>
    <div id="available-tags-list"></div>
  </div>
</div>

<!-- 件数表示 -->
<div id="info-bar" class="info-bar"></div>

<!-- ソートバー (ホーム・グループ両ビューで常時表示) -->
<div id="sort-bar" class="sort-bar">
  <span class="sort-label">並び順:</span>
  <select id="sort-key" class="sort-select" aria-label="ソートキー">
    <option value="mtime">更新時刻</option>
    <option value="ctime">追加時刻</option>
    <option value="filename">ファイル名</option>
  </select>
  <button id="sort-dir" class="sort-dir-btn" aria-label="昇順/降順切り替え">↓ 降順</button>
</div>

<!-- 上部ページネーション -->
<div id="pagination-top" class="pagination pagination-top"></div>

<!-- 画像グリッド -->
<div id="image-grid" class="grid"></div>

<!-- 下部ページネーション -->
<div id="pagination" class="pagination"></div>

<!-- ライトボックス -->
<div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="画像プレビュー">
  <button id="lb-close" class="lightbox-close" aria-label="閉じる">✕</button>
  <button id="lb-prev" class="lightbox-nav prev" aria-label="前の画像">‹</button>
  <button id="lb-next" class="lightbox-nav next" aria-label="次の画像">›</button>
  <div class="lightbox-inner">
    <div id="lb-spinner" class="lb-spinner"></div>
    <img id="lb-image" src="" alt="">
    <div class="lb-meta">
      <p id="lb-title"></p>
      <p id="lb-filename"></p>
      <div id="lb-tags"></div>
    </div>
  </div>
</div>

<script>
// ── データ (PHP 埋め込み) ────────────────────────────────────
const IMAGES     = <?= $images_json ?>;
const ALL_TAGS   = <?= $tags_json ?>;
const GROUPS     = <?= $groups_json ?>;
const IMAGES_DIR = <?= json_encode(IMAGES_DIR, JSON_HEX_TAG) ?>;
const PER_PAGE   = 100;
const GROUP_SORT_DEFAULT = <?= json_encode(defined('GROUP_SORT_DEFAULT') ? GROUP_SORT_DEFAULT : 'same', JSON_HEX_TAG) ?>;

// ── 高速参照マップ ───────────────────────────────────────────
const tagColorMap  = {};   // タグ名 → カラー
const imageById    = {};   // image.id → image
const groupById    = {};   // group.id → group

ALL_TAGS.forEach(t => { if (t.color) tagColorMap[t.name] = t.color; });
IMAGES.forEach(img => { imageById[img.id] = img; });
GROUPS.forEach(g   => { groupById[g.id]   = g; });

// グループに属する image id セット
const groupedImageIds = new Set();
GROUPS.forEach(g => g.imageIds.forEach(id => groupedImageIds.add(id)));

// ── アプリ状態 ───────────────────────────────────────────────
let viewMode       = 'home';  // 'home' | 'group'
let currentGroupId = null;    // group view 時のグループ id
let selectedTags   = [];      // home view のフィルタタグ
let searchMode     = 'and';   // 'and' | 'or'
let currentPage    = 1;
let sortKey        = 'mtime'; // 'mtime' | 'ctime' | 'filename'
let sortDir        = 'desc';  // 'asc' | 'desc'
let groupSortKey   = 'mtime'; // グループビュー専用ソートキー (URL保存しない)
let groupSortDir   = 'desc';  // グループビュー専用ソート方向 (URL保存しない)

// config の GROUP_SORT_DEFAULT に基づいてグループソートの初期値を設定する
function initGroupSort() {
  if (GROUP_SORT_DEFAULT === 'same') {
    groupSortKey = sortKey;
    groupSortDir = sortDir;
    return;
  }
  // 'mtime_asc', 'ctime_desc', 'filename_asc' など「キー_方向」形式
  const validKeys = ['mtime', 'ctime', 'filename'];
  const validDirs = ['asc', 'desc'];
  const sep = GROUP_SORT_DEFAULT.lastIndexOf('_');
  if (sep > 0) {
    const cfgKey = GROUP_SORT_DEFAULT.slice(0, sep);
    const cfgDir = GROUP_SORT_DEFAULT.slice(sep + 1);
    if (validKeys.includes(cfgKey) && validDirs.includes(cfgDir)) {
      groupSortKey = cfgKey;
      groupSortDir = cfgDir;
      return;
    }
  }
  // 不正な値はフォールバック: 一覧と同じ
  groupSortKey = sortKey;
  groupSortDir = sortDir;
}

// 現在表示中のアイテム (image|group オブジェクト)
let displayItems  = [];
// ライトボックス対象の画像リスト (image オブジェクトのみ)
let lightboxImages = [];
let lightboxIndex  = -1;
let openImgId      = null; // URL から復元するライトボックス対象 image id

// ── テーマ ───────────────────────────────────────────────────
const THEME_KEY = 'pixella-theme';

function getEffectiveTheme() {
  const stored = localStorage.getItem(THEME_KEY);
  if (stored === 'dark' || stored === 'light') return stored;
  return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  document.getElementById('btn-theme').textContent = theme === 'dark' ? '☀️' : '🌙';
  document.getElementById('btn-theme').title =
    theme === 'dark' ? 'ライトモードに切り替え' : 'ダークモードに切り替え';
}

function initTheme() {
  applyTheme(getEffectiveTheme());
}

document.getElementById('btn-theme').addEventListener('click', () => {
  const next = getEffectiveTheme() === 'dark' ? 'light' : 'dark';
  localStorage.setItem(THEME_KEY, next);
  applyTheme(next);
});

// OS テーマ変更を即時反映 (ユーザーが手動設定していない場合のみ)
window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', e => {
  if (!localStorage.getItem(THEME_KEY)) {
    applyTheme(e.matches ? 'light' : 'dark');
  }
});

// ── URL パラメータ ────────────────────────────────────────────
function readURL() {
  const p = new URLSearchParams(location.search);
  if (p.has('group')) {
    viewMode       = 'group';
    currentGroupId = parseInt(p.get('group')) || null;
  } else {
    viewMode       = 'home';
    currentGroupId = null;
  }
  selectedTags = p.has('tags')
    ? p.get('tags').split(',').map(s => decodeURIComponent(s.trim())).filter(Boolean)
    : [];
  searchMode  = p.get('mode') === 'or' ? 'or' : 'and';
  currentPage = Math.max(1, parseInt(p.get('page')) || 1);
  sortKey   = ['mtime', 'ctime', 'filename'].includes(p.get('sort')) ? p.get('sort') : 'mtime';
  sortDir   = p.get('sortdir') === 'asc' ? 'asc' : 'desc';
  openImgId = p.has('imgid') ? (parseInt(p.get('imgid')) || null) : null;
  // グループビューで直接ロードされた場合、グループソートをconfig値で初期化
  if (viewMode === 'group') { initGroupSort(); }
}

function writeURL(push = true) {
  const p = new URLSearchParams();
  if (viewMode === 'group' && currentGroupId !== null) {
    p.set('group', String(currentGroupId));
  } else {
    if (selectedTags.length) p.set('tags', selectedTags.join(','));
    if (searchMode !== 'and') p.set('mode', searchMode);
  }
  if (currentPage > 1) p.set('page', String(currentPage));
  if (sortKey !== 'mtime')  p.set('sort',    sortKey);
  if (sortDir !== 'desc')   p.set('sortdir', sortDir);
  if (lightboxIndex >= 0 && lightboxImages[lightboxIndex]) {
    p.set('imgid', String(lightboxImages[lightboxIndex].id));
  }
  const url = p.toString() ? '?' + p.toString() : location.pathname;
  push ? history.pushState({}, '', url) : history.replaceState({}, '', url);
}

// ── ソートキー取得 ────────────────────────────────────────────
function getSortValue(item, key, dir) {
  if (item._type !== 'group') {
    if (key === 'filename') return item.filename ?? '';
    if (key === 'ctime')    return item.ctime  ?? 0;
    return item.mtime ?? 0;
  }
  // グループ: Pixella と同様に全画像の中から降順→max / 昇順→min を使う
  const ids = item.imageIds || [];
  if (!ids.length) return key === 'filename' ? '' : 0;
  if (key === 'filename') {
    const fnames = ids.map(id => imageById[id]?.filename ?? '');
    return fnames.reduce((best, v) => {
      const cmp = naturalCompare(v, best);
      return dir === 'desc' ? (cmp > 0 ? v : best) : (cmp < 0 ? v : best);
    });
  }
  const vals = ids.map(id => {
    const img = imageById[id];
    if (!img) return 0;
    return (key === 'ctime' ? img.ctime : img.mtime) ?? 0;
  });
  return dir === 'desc' ? Math.max(...vals) : Math.min(...vals);
}

// ファイル名の自然順比較 (file_2 < file_10 など)
function naturalCompare(a, b) {
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

// タグ名の配列を「色コード → タグ名」の順でソートする（色なしは末尾）
function sortTagsByColor(tags) {
  return [...tags].sort((a, b) => {
    const ca = tagColorMap[a] || '~';
    const cb = tagColorMap[b] || '~';
    if (ca !== cb) return ca < cb ? -1 : 1;
    return a.toLowerCase() < b.toLowerCase() ? -1 : a.toLowerCase() > b.toLowerCase() ? 1 : 0;
  });
}

function applySortToList(list, key, dir) {
  list.sort((a, b) => {
    const ka = getSortValue(a, key, dir);
    const kb = getSortValue(b, key, dir);
    if (key === 'filename') {
      const cmp = naturalCompare(ka, kb);
      return dir === 'asc' ? cmp : -cmp;
    }
    if (ka < kb) return dir === 'asc' ? -1 : 1;
    if (ka > kb) return dir === 'asc' ?  1 : -1;
    return 0;
  });
}

// ── フィルタリング ────────────────────────────────────────────
function buildDisplayItems() {
  if (viewMode === 'group') {
    // グループビュー: 該当グループの画像をすべて表示 (フィルタなし)
    const g = groupById[currentGroupId];
    if (!g) { displayItems = []; lightboxImages = []; return; }
    displayItems  = g.imageIds.map(id => imageById[id]).filter(Boolean);
    lightboxImages = displayItems.slice();
    applySortToList(displayItems, groupSortKey, groupSortDir);
    lightboxImages = displayItems.slice();
    return;
  }

  // ホームビュー: グループなし画像 + グループ
  const ungrouped = IMAGES.filter(img => !groupedImageIds.has(img.id));

  if (!selectedTags.length) {
    // フィルタなし: 全グループ + 全グループなし画像
    const groups = GROUPS.slice();
    displayItems  = [...groups.map(g => ({ _type: 'group', ...g })),
                      ...ungrouped.map(img => ({ _type: 'image', ...img }))];
    lightboxImages = ungrouped.slice();
  } else {
    const matchesTags = tags => {
      if (searchMode === 'and') return selectedTags.every(t => tags.includes(t));
      return selectedTags.some(t => tags.includes(t));
    };

    const matchedGroups    = GROUPS.filter(g => matchesTags(g.tags));
    const matchedUngrouped = ungrouped.filter(img => matchesTags(img.tags));

    displayItems  = [...matchedGroups.map(g => ({ _type: 'group', ...g })),
                      ...matchedUngrouped.map(img => ({ _type: 'image', ...img }))];
    lightboxImages = matchedUngrouped.slice();
  }

  applySortToList(displayItems, sortKey, sortDir);
  applySortToList(lightboxImages, sortKey, sortDir);
}

// ── メインレンダー ────────────────────────────────────────────
function render() {
  buildDisplayItems();
  const totalPages = Math.max(1, Math.ceil(displayItems.length / PER_PAGE));
  if (currentPage > totalPages) currentPage = totalPages;

  renderHeader();
  renderSearchArea();
  renderSortControls();
  renderInfoBar(displayItems.length, totalPages);
  renderGrid();
  renderPagination(totalPages);

  // URL に imgid が指定されていればライトボックスを開く
  if (openImgId !== null) {
    const idx = lightboxImages.findIndex(i => i.id === openImgId);
    openImgId = null;
    if (idx >= 0) {
      lightboxIndex = idx;
      openLightbox(false); // replaceState のみ (履歴追加なし)
    }
  }
}

// ── タグクリック → ホームで検索 ──────────────────────────────
function addTagAndGoHome(tag) {
  // ライトボックスが開いていれば閉じる (履歴を追加しない)
  if (lightboxIndex >= 0) {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
    lightboxIndex = -1;
  }
  viewMode       = 'home';
  currentGroupId = null;
  currentPage    = 1;
  if (!selectedTags.includes(tag)) selectedTags.push(tag);
  writeURL(true);
  render();
  window.scrollTo({ top: 0, behavior: 'instant' });
}

// ── ソートコントロール ────────────────────────────────────────
function renderSortControls() {
  const activeKey = viewMode === 'group' ? groupSortKey : sortKey;
  const activeDir = viewMode === 'group' ? groupSortDir : sortDir;
  document.getElementById('sort-key').value = activeKey;
  const btn = document.getElementById('sort-dir');
  btn.textContent = activeDir === 'asc' ? '↑ 昇順' : '↓ 降順';
}

// ── ヘッダ / パンくず ─────────────────────────────────────────
function renderHeader() {
  const bc  = document.getElementById('breadcrumb-bar');
  const sa  = document.getElementById('search-area');
  const gtb = document.getElementById('group-tags-bar');

  if (viewMode === 'group') {
    const g = groupById[currentGroupId];
    document.getElementById('bc-group-name').textContent = g ? g.name : '不明なグループ';
    bc.classList.add('visible');
    sa.classList.add('hidden');
	fetch('title_provider.php?id=' + encodeURIComponent(g.cover))
		.then(r => r.ok ? r.text() : '')
		.catch(() => '')
		.then(title => {
		const trimmed = title.trim();
		if(trimmed!=""){
			document.getElementById('bc-group-name').innerHTML = trimmed;
		}
	});
    // グループタグを表示
    gtb.innerHTML = '';
    if (g && g.tags && g.tags.length) {
      sortTagsByColor(g.tags).forEach(tag => {
        const chip = document.createElement('span');
        chip.className = 'chip available';
        chip.textContent = tag;
        chip.title = tag + ' で検索';
        if (tagColorMap[tag]) { chip.style.borderColor = tagColorMap[tag]; chip.style.color = tagColorMap[tag]; }
        chip.addEventListener('click', () => addTagAndGoHome(tag));
        gtb.appendChild(chip);
      });
      gtb.classList.add('visible');
    } else {
      gtb.classList.remove('visible');
    }
  } else {
    bc.classList.remove('visible');
    sa.classList.remove('hidden');
    gtb.classList.remove('visible');
    gtb.innerHTML = '';
  }
}

// ── 検索エリア ───────────────────────────────────────────────
function renderSearchArea() {
  if (viewMode === 'group') return;

  const area = document.getElementById('selected-tags');
  area.innerHTML = '';

  if (!selectedTags.length) {
    const ph = document.createElement('span');
    ph.className = 'placeholder-text';
    ph.textContent = 'タグを選択して絞り込み…';
    area.appendChild(ph);
  } else {
    selectedTags.forEach(tag => {
      area.appendChild(makeChip(tag, true, () => {
        selectedTags = selectedTags.filter(t => t !== tag);
        currentPage = 1;
        writeURL();
        render();
      }));
    });
  }

  document.getElementById('btn-and').classList.toggle('active', searchMode === 'and');
  document.getElementById('btn-or').classList.toggle('active', searchMode === 'or');
  renderAvailableTags();
}

function renderAvailableTags() {
  const query = document.getElementById('tag-filter-input').value.toLowerCase();
  const container = document.getElementById('available-tags-list');
  container.innerHTML = '';

  ALL_TAGS.forEach(t => {
    if (selectedTags.includes(t.name)) return;
    if (query && !t.name.toLowerCase().includes(query)) return;
    const chip = makeChip(t.name, false, () => {
      if (!selectedTags.includes(t.name)) {
        selectedTags.push(t.name);
        currentPage = 1;
		document.getElementById('tag-filter-input').value = '';
        writeURL();
        render();
      }
    });
    if (t.color) { chip.style.borderColor = t.color; chip.style.color = t.color; }
    container.appendChild(chip);
  });
}

function makeChip(label, selected, onClick) {
  const chip = document.createElement('span');
  chip.className = 'chip ' + (selected ? 'selected' : 'available');
  chip.appendChild(document.createTextNode(label));
  if (selected) {
    const rm = document.createElement('span');
    rm.className = 'remove-btn';
    rm.setAttribute('aria-label', label + ' を削除');
    rm.textContent = '×';
    rm.addEventListener('click', e => { e.stopPropagation(); onClick(); });
    chip.appendChild(rm);
  } else {
    chip.addEventListener('click', onClick);
  }
  return chip;
}

// ── 情報バー ─────────────────────────────────────────────────
function renderInfoBar(count, totalPages) {
  const bar = document.getElementById('info-bar');
  if (viewMode === 'group') {
    const g = groupById[currentGroupId];
    bar.textContent = `⊞ ${g ? g.name : ''} — ${count.toLocaleString()} 枚 — ${currentPage} / ${totalPages} ページ`;
  } else {
    const filter = selectedTags.length
      ? `"${selectedTags.join(' ' + searchMode.toUpperCase() + ' ')}" でフィルタ中 — ` : '';
    bar.textContent = `${filter}${count.toLocaleString()} 件 — ${currentPage} / ${totalPages} ページ`;
  }
}

// ── グリッド ─────────────────────────────────────────────────
function renderGrid() {
  const grid = document.getElementById('image-grid');
  grid.innerHTML = '';

  const start = (currentPage - 1) * PER_PAGE;
  const pageItems = displayItems.slice(start, start + PER_PAGE);

  if (!pageItems.length) {
    const empty = document.createElement('div');
    empty.className = 'empty-state';
    empty.innerHTML = (selectedTags.length && viewMode === 'home')
      ? '<p>条件に一致する画像がありません</p><p>タグや AND / OR モードを変えてみてください</p>'
      : '<p>画像が登録されていません</p>';
    grid.appendChild(empty);
    return;
  }

  const frag = document.createDocumentFragment();
  pageItems.forEach(item => {
    if (item._type === 'group') {
      frag.appendChild(makeGroupTile(item));
    } else {
      frag.appendChild(makeImageTile(item));
    }
  });
  grid.appendChild(frag);
}

function makeImageTile(img) {
  const item = document.createElement('div');
  item.className = 'grid-item';
  item.addEventListener('click', () => {
    lightboxIndex = lightboxImages.findIndex(i => i.id === img.id);
    if (lightboxIndex >= 0) openLightbox();
  });

  const image = document.createElement('img');
  image.src = 'thumbnail.php?f=' + encodeURIComponent(img.filename);
  image.alt = img.filename;
  image.loading = 'lazy';
  image.decoding = 'async';
  item.appendChild(image);

  const tags = img.tags || [];
  if (tags.length) {
    const overlay = document.createElement('div');
    overlay.className = 'tag-overlay';
    sortTagsByColor(tags).slice(0, 6).forEach(tag => {
      const span = document.createElement('span');
      span.className = 'tag-label';
      span.textContent = tag;
      if (tagColorMap[tag]) span.style.background = tagColorMap[tag] + '99';
      overlay.appendChild(span);
    });
    item.appendChild(overlay);
  }
  return item;
}

function makeGroupTile(g) {
  const item = document.createElement('div');
  item.className = 'grid-item group-tile';
  item.setAttribute('title', g.name);
  item.addEventListener('click', () => {
    viewMode       = 'group';
    currentGroupId = g.id;
    currentPage    = 1;
    groupSortKey   = sortKey;  // まずホームソートを基準にした上で config を適用
    groupSortDir   = sortDir;
    initGroupSort();  // GROUP_SORT_DEFAULT が 'same' 以外なら上書き
    writeURL();
    render();
    window.scrollTo({ top: 0, behavior: 'instant' });
  });

  // カバー画像
  if (g.cover) {
    const img = document.createElement('img');
    img.src = 'thumbnail.php?f=' + encodeURIComponent(g.cover);
    img.alt = g.name;
    img.loading = 'lazy';
    img.decoding = 'async';
    item.appendChild(img);
  } else {
    const ph = document.createElement('div');
    ph.className = 'group-no-cover';
    ph.innerHTML = '<span>⊞</span><span>画像なし</span>';
    item.appendChild(ph);
  }

  // ⊞ バッジ (左上)
  const badge = document.createElement('div');
  badge.className = 'group-badge';
  badge.innerHTML = '<i class="group-badge-icon">⊞</i>';
  item.appendChild(badge);

  // 枚数バッジ (右上)
  const cnt = document.createElement('div');
  cnt.className = 'group-count';
  cnt.textContent = g.imageIds.length + ' 枚';
  item.appendChild(cnt);

  // グループ名 (下部)
  const namebar = document.createElement('div');
  namebar.className = 'group-name-bar';
  namebar.textContent = g.name;
  item.appendChild(namebar);

  // タグオーバーレイ (タグがある場合のみ)
  if (g.tags && g.tags.length) {
    item.classList.add('has-tags');
    const overlay = document.createElement('div');
    overlay.className = 'tag-overlay';
    sortTagsByColor(g.tags).slice(0, 6).forEach(tag => {
      const span = document.createElement('span');
      span.className = 'tag-label';
      span.textContent = tag;
      if (tagColorMap[tag]) span.style.background = tagColorMap[tag] + '99';
      overlay.appendChild(span);
    });
    item.appendChild(overlay);
  }

  return item;
}

// ── ページネーション ──────────────────────────────────────────
function fillPagination(pag, totalPages) {
  pag.innerHTML = '';
  if (totalPages <= 1) return;

  const goTo = page => {
    currentPage = page;
    writeURL();
    render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const makeBtn = (label, page, disabled, active) => {
    const btn = document.createElement('button');
    btn.textContent = label;
    btn.disabled = !!disabled;
    if (active) btn.classList.add('active');
    if (!disabled) btn.addEventListener('click', () => goTo(page));
    return btn;
  };
  const ellipsis = () => {
    const s = document.createElement('span');
    s.className = 'ellipsis';
    s.textContent = '…';
    return s;
  };

  pag.appendChild(makeBtn('«', 1,               currentPage === 1));
  pag.appendChild(makeBtn('‹', currentPage - 1, currentPage === 1));

  const WING = 2;
  const rangeStart = Math.max(1, currentPage - WING);
  const rangeEnd   = Math.min(totalPages, currentPage + WING);

  if (rangeStart > 1) {
    pag.appendChild(makeBtn('1', 1, false, false));
    if (rangeStart > 2) pag.appendChild(ellipsis());
  }
  for (let p = rangeStart; p <= rangeEnd; p++) {
    pag.appendChild(makeBtn(String(p), p, false, p === currentPage));
  }
  if (rangeEnd < totalPages) {
    if (rangeEnd < totalPages - 1) pag.appendChild(ellipsis());
    pag.appendChild(makeBtn(String(totalPages), totalPages, false, false));
  }

  pag.appendChild(makeBtn('›', currentPage + 1, currentPage === totalPages));
  pag.appendChild(makeBtn('»', totalPages,       currentPage === totalPages));
}

function renderPagination(totalPages) {
  fillPagination(document.getElementById('pagination-top'), totalPages);
  fillPagination(document.getElementById('pagination'), totalPages);
}

// ── ライトボックス ────────────────────────────────────────────
function openLightbox(pushHistory = false) {
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
  updateLightbox();
  writeURL(pushHistory);
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
  lightboxIndex = -1;
  writeURL(false); // imgid を URL から除去 (履歴追加なし)
}
function updateLightbox() {
  if (lightboxIndex < 0 || lightboxIndex >= lightboxImages.length) return;
  const img     = lightboxImages[lightboxIndex];
  const lbImg   = document.getElementById('lb-image');
  const spinner = document.getElementById('lb-spinner');

  // 前の画像を隠してスピナーを表示
  lbImg.style.display = 'none';
  spinner.classList.add('visible');

  // ファイル名・タグ・タイトルは先に更新
  document.getElementById('lb-filename').textContent = img.filename;

  // タイトルエリアをクリアしてから非同期取得
  const titleEl = document.getElementById('lb-title');
  titleEl.textContent = '';
  titleEl.style.display = 'none';
  // グループの場合はタイトルを表示しない (グループ名はパンくずに表示されているため)
  if(img.groupId===null){
	const fetchImgId = img.id;
	fetch('title_provider.php?id=' + encodeURIComponent(img.filename))
		.then(r => r.ok ? r.text() : '')
		.catch(() => '')
		.then(title => {
		const trimmed = title.trim();
		if (lightboxImages[lightboxIndex]?.id === fetchImgId && trimmed) {
			titleEl.innerHTML = trimmed;
			titleEl.style.display = '';
		}
		});
  }

  const tagsEl = document.getElementById('lb-tags');
  tagsEl.innerHTML = '';
  sortTagsByColor(img.tags || []).forEach(tag => {
    const chip = document.createElement('span');
    chip.className = 'chip';
    chip.style.cursor = 'pointer';
    chip.title = tag + ' で検索';
    chip.textContent = tag;
    if (tagColorMap[tag]) { chip.style.borderColor = tagColorMap[tag]; chip.style.color = tagColorMap[tag]; }
    chip.addEventListener('click', () => addTagAndGoHome(tag));
    tagsEl.appendChild(chip);
  });

  document.getElementById('lb-prev').disabled = lightboxIndex === 0;
  document.getElementById('lb-next').disabled = lightboxIndex === lightboxImages.length - 1;

  // 画像読み込み完了 / エラーでスピナーを消して画像を表示
  const onFinish = () => {
    spinner.classList.remove('visible');
    lbImg.style.display = '';
  };
  lbImg.onload  = onFinish;
  lbImg.onerror = onFinish;
  lbImg.alt = img.filename;
  lbImg.src = IMAGES_DIR + encodeURIComponent(img.filename);
}

// ── イベントリスナー ──────────────────────────────────────────
// 検索
document.getElementById('btn-and').addEventListener('click', () => {
  searchMode = 'and'; currentPage = 1; writeURL(); render();
});
document.getElementById('btn-or').addEventListener('click', () => {
  searchMode = 'or'; currentPage = 1; writeURL(); render();
});
document.getElementById('btn-clear').addEventListener('click', () => {
  selectedTags = []; currentPage = 1; writeURL(); render();
});
document.getElementById('tag-filter-input').addEventListener('input', renderAvailableTags);

// ソート
document.getElementById('sort-key').addEventListener('change', e => {
  if (viewMode === 'group') { groupSortKey = e.target.value; } else { sortKey = e.target.value; }
  currentPage = 1; writeURL(); render();
});
document.getElementById('sort-dir').addEventListener('click', () => {
  if (viewMode === 'group') { groupSortDir = groupSortDir === 'asc' ? 'desc' : 'asc'; }
  else { sortDir = sortDir === 'asc' ? 'desc' : 'asc'; }
  currentPage = 1; writeURL(); render();
});

// パンくず / 戻る
function goHome() {
  viewMode = 'home'; currentGroupId = null; currentPage = 1;
  writeURL(); render();
}
document.getElementById('btn-back').addEventListener('click', () => history.back());
document.getElementById('bc-home').addEventListener('click', e => { e.preventDefault(); goHome(); });

// ライトボックス
document.getElementById('lightbox').addEventListener('click', e => {
  if (e.target === document.getElementById('lightbox')) closeLightbox();
});
document.getElementById('lb-close').addEventListener('click', closeLightbox);
document.getElementById('lb-prev').addEventListener('click', () => {
  if (lightboxIndex > 0) { lightboxIndex--; updateLightbox(); }
});
document.getElementById('lb-next').addEventListener('click', () => {
  if (lightboxIndex < lightboxImages.length - 1) { lightboxIndex++; updateLightbox(); }
});

// キーボード
document.addEventListener('keydown', e => {
  const lb = document.getElementById('lightbox');
  if (lb.classList.contains('open')) {
    if (e.key === 'Escape') { closeLightbox(); return; }
    if (e.key === 'ArrowLeft'  && lightboxIndex > 0) { lightboxIndex--; updateLightbox(); }
    if (e.key === 'ArrowRight' && lightboxIndex < lightboxImages.length - 1) { lightboxIndex++; updateLightbox(); }
  }
});

// ブラウザバック
window.addEventListener('popstate', () => {
  // ライトボックスが開いていれば一旦閉じる (render() で必要なら再度開く)
  if (lightboxIndex >= 0) {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
    lightboxIndex = -1;
  }
  readURL();
  render();
});

// ── 初期化 ───────────────────────────────────────────────────
initTheme();
readURL();
render();
</script>

<?php endif; ?>
</body>
</html>
