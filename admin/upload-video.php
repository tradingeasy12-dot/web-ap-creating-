<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$existing = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM videos WHERE id = ?');
    $stmt->execute([$editId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('error', 'Video not found.');
        header('Location: /admin/videos.php');
        exit;
    }
}

$redirectBack = '/admin/upload-video.php' . ($editId ? '?id=' . $editId : '');

// ---- Step 1: just the video file was submitted. Save it, then try to generate thumbnail
//      suggestions automatically (if ffmpeg is available on this server), and come back here.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_video') {
    csrf_check();

    if (empty($_FILES['video_file']['name'])) {
        flash('error', 'Choose a video file first.');
        header('Location: ' . $redirectBack);
        exit;
    }
    $file = $_FILES['video_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'File upload failed (error code ' . $file['error'] . ').');
        header('Location: ' . $redirectBack);
        exit;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'mov', 'webm', 'mkv'])) {
        flash('error', 'Unsupported file type. Allowed: mp4, mov, webm, mkv');
        header('Location: ' . $redirectBack);
        exit;
    }

    $filename = uniqid('vid_', true) . '.' . $ext;
    $absoluteDestination = UPLOAD_DIR . 'videos/' . $filename;
    move_uploaded_file($file['tmp_name'], $absoluteDestination);
    $storagePath = 'uploads/videos/' . $filename;

    $candidates = generate_thumbnail_candidates($absoluteDestination);

    $_SESSION['upload_pending'] = ['storage_path' => $storagePath, 'candidates' => $candidates];

    flash('success', $candidates
        ? 'Video uploaded. Pick a thumbnail below, or upload your own.'
        : 'Video uploaded. This server can\'t auto-generate thumbnails (ffmpeg not available) — upload one manually below.');
    header('Location: ' . $redirectBack);
    exit;
}

// ---- Regenerate thumbnail suggestions from a video that's already saved (new pending upload,
//      or an existing video being edited) — useful if the first batch wasn't great.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regen_thumbs') {
    csrf_check();
    $videoPath = $_SESSION['upload_pending']['storage_path'] ?? ($existing['storage_path'] ?? null);
    if ($videoPath) {
        $candidates = generate_thumbnail_candidates(__DIR__ . '/../' . $videoPath);
        $_SESSION['upload_pending'] = ['storage_path' => $videoPath, 'candidates' => $candidates];
        flash($candidates ? 'success' : 'error', $candidates ? 'New thumbnail suggestions ready.' : 'Could not generate thumbnails from this video.');
    }
    header('Location: ' . $redirectBack);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['action'])) {
    csrf_check();

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId  = $_POST['category_id'] ?: null;
    $uploadType  = $_POST['upload_type'] === 'embed' ? 'embed' : 'self_hosted';
    $embedCode   = trim($_POST['embed_code'] ?? '');
    $adsEnabled  = isset($_POST['ads_enabled']) ? 1 : 0;
    $prerollAdId = $_POST['preroll_ad_id'] ?: null;
    $tagsInput   = trim($_POST['tags'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';

    if ($title === '') {
        flash('error', 'Title is required.');
        header('Location: /admin/upload-video.php' . ($editId ? '?id=' . $editId : ''));
        exit;
    }

    $pending = $_SESSION['upload_pending'] ?? null;

    $storagePath   = $pending['storage_path'] ?? ($existing['storage_path'] ?? null);
    $thumbnailPath = $existing['thumbnail_path'] ?? null;

    // Priority for the thumbnail: manual upload > pasted image link > a chosen auto-suggestion > whatever it already had.
    if (!empty($_FILES['thumbnail_file']['name'])) {
        $thumb = $_FILES['thumbnail_file'];
        if ($thumb['error'] === UPLOAD_ERR_OK) {
            $thumbExt = strtolower(pathinfo($thumb['name'], PATHINFO_EXTENSION));
            $allowedThumb = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($thumbExt, $allowedThumb)) {
                $thumbFilename = uniqid('thumb_', true) . '.' . $thumbExt;
                $thumbDestination = UPLOAD_DIR . 'thumbnails/' . $thumbFilename;
                if (move_uploaded_file($thumb['tmp_name'], $thumbDestination)) {
                    $thumbnailPath = 'uploads/thumbnails/' . $thumbFilename;
                }
            } else {
                flash('error', 'Thumbnail must be JPG, PNG, or WebP.');
                header('Location: ' . $redirectBack);
                exit;
            }
        }
    } elseif (!empty($_POST['thumbnail_url']) && filter_var(trim($_POST['thumbnail_url']), FILTER_VALIDATE_URL)) {
        $url    = trim($_POST['thumbnail_url']);
        $host   = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        // Lightweight guard against pointing this at internal/local addresses.
        $isBlockedHost = $host && (
            strtolower($host) === 'localhost' ||
            preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|169\.254\.)/', $host)
        );

        if (in_array($scheme, ['http', 'https'], true) && !$isBlockedHost) {
            $ctx  = stream_context_create(['http' => [
                'timeout' => 8,
                'header'  => "User-Agent: VideoPlatformThumbnailFetcher/1.0\r\n",
            ]]);
            // Soft cap around 5MB — good enough for a thumbnail-sized image.
            $data = @file_get_contents($url, false, $ctx, 0, 5 * 1024 * 1024);

            if ($data !== false) {
                $info = @getimagesizefromstring($data);
                $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if ($info && isset($mimeToExt[$info['mime']])) {
                    $thumbFilename = uniqid('thumb_url_', true) . '.' . $mimeToExt[$info['mime']];
                    $thumbDestination = UPLOAD_DIR . 'thumbnails/' . $thumbFilename;
                    if (file_put_contents($thumbDestination, $data)) {
                        $thumbnailPath = 'uploads/thumbnails/' . $thumbFilename;
                    } else {
                        flash('error', 'Could not save the downloaded image — check that uploads/thumbnails is writable.');
                    }
                } else {
                    flash('error', 'That link did not point to a valid JPG/PNG/WebP image.');
                }
            } else {
                flash('error', 'Could not download the image from that link.');
            }
        } else {
            flash('error', 'That image link looks invalid or unsafe.');
        }
    } elseif (!empty($_POST['thumbnail_choice'])) {
        $choice = $_POST['thumbnail_choice'];
        // Only accept a choice that's actually one of the suggestions we generated this session —
        // never trust a raw path from the form directly.
        if ($pending && in_array($choice, $pending['candidates'] ?? [], true)) {
            $thumbnailPath = $choice;
        }
    }

    // Applies the "Auto-convert uploaded thumbnails to WebP" SEO setting, if it's on.
    // No-op if the setting is off, the file is already .webp, or this server can't do it.
    $thumbnailPath = maybe_convert_thumbnail_to_webp($thumbnailPath);

    // Fallback: if no pending session upload exists, allow a direct file on the main form too
    // (covers the case where ffmpeg isn't installed and Step 1 was skipped entirely).
    if ($uploadType === 'self_hosted' && !$pending && !empty($_FILES['video_file']['name'])) {
        $file = $_FILES['video_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'File upload failed (error code ' . $file['error'] . ').');
            header('Location: ' . $redirectBack);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['mp4', 'mov', 'webm', 'mkv'];
        if (!in_array($ext, $allowed)) {
            flash('error', 'Unsupported file type. Allowed: ' . implode(', ', $allowed));
            header('Location: ' . $redirectBack);
            exit;
        }
        $filename = uniqid('vid_', true) . '.' . $ext;
        $destination = UPLOAD_DIR . 'videos/' . $filename;
        move_uploaded_file($file['tmp_name'], $destination);
        $storagePath = 'uploads/videos/' . $filename;
    }

    $customSlugInput = trim($_POST['custom_slug'] ?? '');

    if ($editId) {
        // Use the custom slug if provided, otherwise keep the existing one so old links don't break.
        $slug = $customSlugInput !== ''
            ? unique_slug('videos', $customSlugInput, $editId)
            : $existing['slug'];

        $stmt = db()->prepare(
            'UPDATE videos SET title=?, slug=?, description=?, upload_type=?, embed_code=?, storage_path=?, thumbnail_path=?,
                                category_id=?, status=?, ads_enabled=?, preroll_ad_id=? WHERE id=?'
        );
        $stmt->execute([$title, $slug, $description, $uploadType, $embedCode ?: null, $storagePath, $thumbnailPath,
                         $categoryId, $status, $adsEnabled, $prerollAdId, $editId]);
        $videoId = $editId;
        db()->prepare('DELETE FROM video_tags WHERE video_id = ?')->execute([$videoId]);
    } else {
        // New video: use the custom slug if one was typed, otherwise derive it from the title.
        $slug = unique_slug('videos', $customSlugInput !== '' ? $customSlugInput : $title);
        $stmt = db()->prepare(
            'INSERT INTO videos (title, slug, description, upload_type, embed_code, storage_path, thumbnail_path,
                                  category_id, status, ads_enabled, preroll_ad_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $slug, $description, $uploadType, $embedCode ?: null, $storagePath, $thumbnailPath,
                        $categoryId, $status, $adsEnabled, $prerollAdId]);
        $videoId = (int) db()->lastInsertId();
    }

    if ($tagsInput !== '') {
        foreach (array_filter(array_map('trim', explode(',', $tagsInput))) as $tagName) {
            $tagSlug = slugify($tagName);
            $stmt = db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id');
            $stmt->execute([$tagName, $tagSlug]);
            $tagId = (int) db()->query('SELECT id FROM tags WHERE slug = ' . db()->quote($tagSlug))->fetchColumn();
            db()->prepare('INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)')->execute([$videoId, $tagId]);
        }
    }

    unset($_SESSION['upload_pending']);
    unset($_SESSION['upload_pending_fields']);

    if ($status === 'published') {
        $finalSlug = $editId ? $slug : $slug; // $slug holds the final value in both branches above
        $watchUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/watch.php?slug=' . rawurlencode($finalSlug);
        ping_indexnow($watchUrl);
    }

    flash('success', 'Video "' . $title . '" saved.');
    header('Location: /admin/videos.php');
    exit;
}

$existingTags = '';
if ($existing) {
    $stmt = db()->prepare('SELECT t.name FROM tags t JOIN video_tags vt ON vt.tag_id = t.id WHERE vt.video_id = ?');
    $stmt->execute([$editId]);
    $existingTags = implode(', ', array_column($stmt->fetchAll(), 'name'));
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$adLibrary  = db()->query("SELECT id, name FROM ad_library WHERE type = 'video_vast' AND status = 'active' ORDER BY name")->fetchAll();

// Retrieve values from the session if we are in the middle of a multi-step upload
$pendingFields = $_SESSION['upload_pending_fields'] ?? null;

$vTitle       = $pendingFields['title'] ?? ($existing['title'] ?? '');
$vDescription = $pendingFields['description'] ?? ($existing['description'] ?? '');
$vCategoryId  = $pendingFields['category_id'] ?? ($existing['category_id'] ?? '');
$vStatus      = $pendingFields['status'] ?? ($existing['status'] ?? 'draft');
$vUploadType  = $pendingFields['upload_type'] ?? ($existing['upload_type'] ?? 'self_hosted');
$vEmbedCode   = $pendingFields['embed_code'] ?? ($existing['embed_code'] ?? '');
$vAdsEnabled  = $pendingFields['ads_enabled'] ?? ($existing['ads_enabled'] ?? 1);
$vPrerollAdId = $pendingFields['preroll_ad_id'] ?? ($existing['preroll_ad_id'] ?? '');
$vTags        = $pendingFields['tags'] ?? $existingTags;
$vCustomSlug  = $pendingFields['custom_slug'] ?? ($existing['slug'] ?? '');

$pending          = $_SESSION['upload_pending'] ?? null;
$currentVideoPath = $pending['storage_path'] ?? ($existing['storage_path'] ?? null);
$thumbCandidates  = $pending['candidates'] ?? [];

$activeNav = 'upload';
$pageTitle = $existing ? 'Edit Video' : 'Upload Video';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<form method="POST" enctype="multipart/form-data" id="mainForm">
  <?= csrf_field() ?>
  <input type="hidden" name="upload_type" id="uploadTypeHidden" value="<?= htmlspecialchars($vUploadType) ?>">

  <div class="card">
    <div class="card-head"><div><p class="card-title">Source</p><p class="card-sub">Upload a file, or embed from an external player.</p></div></div>

    <div class="field">
      <label>Upload type</label>
      <select id="uploadTypeSelect" onchange="document.getElementById('fileBlock').style.display=this.value==='self_hosted'?'block':'none';document.getElementById('embedBlock').style.display=this.value==='embed'?'block':'none';document.getElementById('uploadTypeHidden').value=this.value;">
        <option value="self_hosted" <?= $vUploadType === 'self_hosted' ? 'selected' : '' ?>>Self-hosted file</option>
        <option value="embed" <?= $vUploadType === 'embed' ? 'selected' : '' ?>>Embed code</option>
      </select>
    </div>

    <div id="fileBlock" style="display:<?= $vUploadType === 'embed' ? 'none' : 'block' ?>;">
      <?php if ($currentVideoPath): ?>
        <div class="hint" style="margin-bottom:10px;">✓ Video ready: <span class="mono"><?= htmlspecialchars(basename($currentVideoPath)) ?></span></div>
        <div class="field">
          <label>Replace this video</label>
          <input type="file" name="video_file" accept=".mp4,.mov,.webm,.mkv">
        </div>
        <button type="submit" name="action" value="upload_video" class="btn btn-ghost btn-sm">Upload &amp; suggest thumbnails</button>
      <?php else: ?>
        <div class="field">
          <label>Video file</label>
          <input type="file" name="video_file" accept=".mp4,.mov,.webm,.mkv">
          <div class="hint">MP4, MOV, WebM, MKV. After uploading, thumbnail suggestions appear below (if this server has ffmpeg).</div>
        </div>
        <button type="submit" name="action" value="upload_video" class="btn btn-primary btn-sm">Upload &amp; suggest thumbnails</button>
      <?php endif; ?>
    </div>

    <div id="embedBlock" class="field" style="display:<?= $vUploadType === 'embed' ? 'block' : 'none' ?>;">
      <label>Embed code</label>
      <textarea id="embedCodeField" name="embed_code" placeholder="&lt;iframe src=&quot;https://player.example.com/embed/xxxx&quot;&gt;&lt;/iframe&gt;"><?= htmlspecialchars($vEmbedCode) ?></textarea>
      <div class="hint">
        Paste a <strong>direct video file link</strong> (e.g. ending in .mp4) and it plays through our own player, same as self-hosted videos.
        Paste a third-party <strong>iframe/script embed</strong> and that source's own player controls show instead — a browser security rule means we can't put our skin around another site's player.
      </div>
    </div>
  </div>

  <?php if ($thumbCandidates): ?>
    <button type="submit" name="action" value="regen_thumbs" class="btn btn-ghost btn-sm" style="margin-bottom:6px;">↻ Suggest different frames</button>
  <?php elseif ($currentVideoPath && ffmpeg_available()): ?>
    <button type="submit" name="action" value="regen_thumbs" class="btn btn-ghost btn-sm" style="margin-bottom:6px;">✨ Generate thumbnail suggestions from this video</button>
  <?php elseif ($currentVideoPath): ?>
    <div class="hint" style="margin-bottom:14px;">This server can't auto-generate thumbnails from video (ffmpeg not installed) — upload one manually below.</div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><div><p class="card-title">Thumbnail</p><p class="card-sub">Shown on the homepage and watch page.</p></div></div>

    <?php if ($thumbCandidates): ?>
      <label style="margin-bottom:10px;">Auto-generated suggestions — pick one</label>
      <div class="thumb-pick-grid" id="thumbPickGrid">
        <?php foreach ($thumbCandidates as $i => $path): ?>
          <label class="thumb-pick-item">
            <input type="radio" name="thumbnail_choice" value="<?= htmlspecialchars($path) ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <img src="/<?= htmlspecialchars($path) ?>" alt="Suggestion <?= $i + 1 ?>">
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($existing['thumbnail_path']) && !$thumbCandidates): ?>
      <div style="margin-bottom:12px;">
        <img src="/<?= htmlspecialchars($existing['thumbnail_path']) ?>" alt="Current thumbnail"
             style="max-width:220px;border-radius:8px;border:1px solid var(--border);display:block;">
        <div class="hint">Current thumbnail — pick a suggestion above or upload a new image below to replace it.</div>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="field">
        <label>Or upload your own image</label>
        <input type="file" name="thumbnail_file" accept=".jpg,.jpeg,.png,.webp">
        <div class="hint">JPG, PNG, or WebP. Recommended 1280×720 (16:9).</div>
      </div>
      <div class="field">
        <label>Or paste an image link</label>
        <input type="text" name="thumbnail_url" class="mono" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($pendingFields['thumbnail_url'] ?? '') ?>">
        <div class="hint">We'll download and save a copy — so it still works if that link goes down later.</div>
      </div>
    </div>
    <div class="hint" style="margin-top:-8px;">If you fill in more than one of the options above, priority is: uploaded file → image link → suggestion picked above.</div>
  </div>

  <div class="card">
    <div class="card-head"><div><p class="card-title">Details</p></div></div>
    <div class="field"><label>Title</label><input type="text" name="title" required value="<?= htmlspecialchars($vTitle) ?>"></div>
    <div class="field"><label>Description</label><textarea name="description"><?= htmlspecialchars($vDescription) ?></textarea></div>
    <div class="field">
      <label>Custom Slug / URL</label>
      <div style="display:flex;align-items:center;gap:6px;">
        <span class="hint" style="white-space:nowrap;">yoursite.com/watch/</span>
        <input type="text" name="custom_slug" class="mono" placeholder="auto-generated-from-title"
               value="<?= htmlspecialchars($vCustomSlug) ?>">
      </div>
      <div class="hint">Leave as-is to auto-generate from the title, or set your own URL slug. Use only lowercase letters, numbers, and hyphens.</div>
    </div>
    <div class="row">
      <div class="field">
        <label>Category</label>
        <select name="category_id">
          <option value="">— Select —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $vCategoryId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Status</label>
        <select name="status">
          <option value="published" <?= $vStatus === 'published' ? 'selected' : '' ?>>Publish immediately</option>
          <option value="draft" <?= $vStatus === 'draft' ? 'selected' : '' ?>>Save as draft</option>
        </select>
      </div>
    </div>
    <div class="field"><label>Tags (comma separated)</label><input type="text" name="tags" placeholder="tag-one, tag-two" value="<?= htmlspecialchars($vTags) ?>"></div>
  </div>

  <div class="card">
    <div class="card-head"><div><p class="card-title">Ads for this video</p></div></div>
    <div class="switch-row">
      <div><div class="switch-label">Show ads on this video</div><div class="switch-desc">Uses global slot settings unless a specific ad is chosen below</div></div>
      <label class="switch"><input type="checkbox" name="ads_enabled" <?= $vAdsEnabled ? 'checked' : '' ?>><span class="slider"></span></label>
    </div>
    <div class="field" style="margin-top:14px;">
      <label>Pre-roll ad override</label>
      <select name="preroll_ad_id">
        <option value="">Use global default</option>
        <?php foreach ($adLibrary as $ad): ?>
          <option value="<?= $ad['id'] ?>" <?= $vPrerollAdId == $ad['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ad['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="actions-row">
    <button type="submit" class="btn btn-primary"><?= $existing ? 'Save changes' : 'Save video' ?></button>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/layout_header.php'; ?>