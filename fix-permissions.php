<?php
/**
 * One-time helper: fixes permissions on the upload folders so video/thumbnail
 * uploads work. Visit this file once in your browser, then delete it.
 *
 * Usage: upload this file to your site root, visit yourdomain.com/fix-permissions.php,
 * confirm it says "writable", then delete this file.
 */

$folders = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/videos',
    __DIR__ . '/uploads/thumbnails',
];

$results = [];

foreach ($folders as $folder) {
    $label = str_replace(__DIR__ . '/', '', $folder);

    if (!is_dir($folder)) {
        @mkdir($folder, 0755, true);
    }

    $ok = @chmod($folder, 0755);
    if (!$ok || !is_writable($folder)) {
        $ok = @chmod($folder, 0775);
    }

    $results[$label] = is_writable($folder);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fix upload permissions</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0D0F14;color:#E8EAF0;
    display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
  .card{background:#151822;border:1px solid #262B38;border-radius:14px;padding:30px;max-width:440px;width:100%;}
  h1{font-size:17px;margin:0 0 16px;}
  .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #262B38;font-size:13.5px;}
  .row:last-child{border-bottom:none;}
  .ok{color:#3DD68C;font-weight:600;}
  .fail{color:#F0607A;font-weight:600;}
  .note{margin-top:18px;font-size:12px;color:#8B93A7;line-height:1.6;}
  code{background:#1B1F2B;padding:2px 6px;border-radius:4px;}
</style>
</head>
<body>
<div class="card">
  <h1>Upload folder permissions</h1>
  <?php foreach ($results as $label => $writable): ?>
    <div class="row">
      <span><code><?= htmlspecialchars($label) ?></code></span>
      <?php if ($writable): ?>
        <span class="ok">✓ Writable</span>
      <?php else: ?>
        <span class="fail">✗ Still not writable</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (in_array(false, $results, true)): ?>
    <div class="note">
      This server won't let PHP change permissions on some folders. Set 755 (or 775) manually
      via File Manager or FTP on the folders marked ✗ above, then re-visit this page to confirm.
    </div>
  <?php else: ?>
    <div class="note">
      All good — video and thumbnail uploads should work now. <strong>Delete this file</strong> from your
      server, since it's no longer needed and shouldn't be left publicly accessible.
    </div>
  <?php endif; ?>
</div>
</body>
</html>
