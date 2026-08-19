<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = db()->prepare(
        'UPDATE age_gate_settings SET is_enabled=?, heading=?, body_text=?, button_text=?, exit_redirect_url=?, remember_duration=? WHERE id=1'
    );
    $stmt->execute([
        isset($_POST['is_enabled']) ? 1 : 0,
        trim($_POST['heading'] ?? ''),
        trim($_POST['body_text'] ?? ''),
        trim($_POST['button_text'] ?? ''),
        trim($_POST['exit_redirect_url'] ?? 'https://google.com'),
        $_POST['remember_duration'] ?? '1_day',
    ]);
    flash('success', 'Age verification settings saved.');
    header('Location: /admin/agegate.php');
    exit;
}

$settings = db()->query('SELECT * FROM age_gate_settings WHERE id = 1')->fetch();

$activeNav = 'agegate';
$pageTitle = 'Age Verification';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<form method="POST">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><div><p class="card-title">Age verification popup</p><p class="card-sub">Shown before any page loads.</p></div></div>
    <div class="switch-row">
      <div><div class="switch-label">Show 18+ popup</div><div class="switch-desc">Master on/off for the whole site</div></div>
      <label class="switch"><input type="checkbox" name="is_enabled" <?= $settings['is_enabled'] ? 'checked' : '' ?>><span class="slider"></span></label>
    </div>
    <div class="field" style="margin-top:14px;">
      <label>Remember choice</label>
      <select name="remember_duration">
        <?php foreach (['session'=>'This session','1_day'=>'1 day','30_days'=>'30 days'] as $val=>$label): ?>
          <option value="<?= $val ?>" <?= $settings['remember_duration'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><p class="card-title">Content</p></div></div>
    <div class="field"><label>Heading</label><input type="text" name="heading" value="<?= htmlspecialchars($settings['heading']) ?>"></div>
    <div class="field"><label>Body text</label><textarea name="body_text"><?= htmlspecialchars($settings['body_text']) ?></textarea></div>
    <div class="row">
      <div class="field"><label>Confirm button text</label><input type="text" name="button_text" value="<?= htmlspecialchars($settings['button_text']) ?>"></div>
      <div class="field"><label>Exit redirect URL</label><input type="text" class="mono" name="exit_redirect_url" value="<?= htmlspecialchars($settings['exit_redirect_url']) ?>"></div>
    </div>
    <div class="actions-row"><button type="submit" class="btn btn-primary">Save popup settings</button></div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
