<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = db()->prepare(
            'INSERT INTO storage_providers (provider, label, access_key, secret_key, bucket_name, region, endpoint_url, cdn_url, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $secret = trim($_POST['secret_key'] ?? '');
        $stmt->execute([
            trim($_POST['provider'] ?? ''), trim($_POST['label'] ?? ''),
            encrypt_secret(trim($_POST['access_key'] ?? '')),
            $secret !== '' ? encrypt_secret($secret) : null,
            trim($_POST['bucket_name'] ?? ''), trim($_POST['region'] ?? ''),
            trim($_POST['endpoint_url'] ?? '') ?: null, trim($_POST['cdn_url'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
        ]);
        flash('success', 'Storage provider saved.');
    } elseif ($action === 'activate') {
        db()->exec('UPDATE storage_providers SET is_active = 0');
        db()->prepare('UPDATE storage_providers SET is_active = 1 WHERE id = ?')->execute([(int) $_POST['provider_id']]);
        flash('success', 'Active storage provider updated.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM storage_providers WHERE id = ?')->execute([(int) $_POST['provider_id']]);
        flash('success', 'Storage provider removed.');
    }
    header('Location: /admin/storage.php');
    exit;
}

$providers = db()->query('SELECT * FROM storage_providers ORDER BY created_at DESC')->fetchAll();

$activeNav = 'storage';
$pageTitle = 'Storage API';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<div class="card">
  <div class="card-head"><div><p class="card-title">Connected providers</p><p class="card-sub">Your video files live here — the panel only stores the connection details (encrypted).</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Provider</th><th>Bucket</th><th>Notes</th><th>Status</th><th></th></tr>
      <?php if (!$providers): ?>
        <tr><td colspan="5" style="color:var(--text-faint);">No storage provider added yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($providers as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['label'] ?: $p['provider']) ?></td>
          <td class="mono"><?= htmlspecialchars($p['bucket_name']) ?></td>
          <td style="color:var(--text-faint);max-width:200px;"><?= htmlspecialchars($p['notes'] ?? '—') ?></td>
          <td><span class="status-dot <?= $p['is_active'] ? 'status-ok' : 'status-warn' ?>"></span><?= $p['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td>
            <?php if (!$p['is_active']): ?>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="activate">
                <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">Make active</button>
              </form>
            <?php endif; ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this provider?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete">
              <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><p class="card-title">Add provider</p></div></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="row">
      <div class="field"><label>Provider name</label>
        <input type="text" name="provider" placeholder="e.g. bunnycdn, wasabi, aws_s3, my-new-provider" required>
        <div class="hint">Type anything — this is just a label so you can tell providers apart. No fixed list, so new providers work right away.</div>
      </div>
      <div class="field"><label>Label</label><input type="text" name="label" placeholder="e.g. Primary bucket"></div>
    </div>
    <div class="row">
      <div class="field"><label>Access key / API key</label><input type="text" class="mono" name="access_key" required></div>
      <div class="field"><label>Secret key <span class="hint" style="display:inline;">(leave blank if this provider doesn't use one)</span></label><input type="password" class="mono" name="secret_key"></div>
    </div>
    <div class="row">
      <div class="field"><label>Bucket / storage zone name</label><input type="text" name="bucket_name" required></div>
      <div class="field"><label>Region <span class="hint" style="display:inline;">(if applicable)</span></label><input type="text" name="region" placeholder="e.g. us-east-1"></div>
    </div>
    <div class="row">
      <div class="field"><label>Endpoint / hostname</label><input type="text" class="mono" name="endpoint_url" placeholder="e.g. storage.bunnycdn.com"></div>
      <div class="field"><label>CDN URL</label><input type="text" class="mono" name="cdn_url" placeholder="e.g. https://yourzone.b-cdn.net"></div>
    </div>
    <div class="field">
      <label>Notes <span class="hint" style="display:inline;">(optional — jot down anything specific to this provider, for your own reference)</span></label>
      <textarea name="notes" placeholder="e.g. This provider doesn't use a secret key. Auth header is X-Api-Key instead of Authorization."></textarea>
    </div>
    <div class="actions-row"><button type="submit" class="btn btn-primary">Save provider</button></div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
