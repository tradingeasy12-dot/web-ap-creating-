<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_setting('site_name', trim($_POST['site_name'] ?? ''));
    set_setting('tagline', trim($_POST['tagline'] ?? ''));
    set_setting('meta_title_template', trim($_POST['meta_title_template'] ?? ''));
    set_setting('meta_description_template', trim($_POST['meta_description_template'] ?? ''));
    set_setting('sitemap_enabled', isset($_POST['sitemap_enabled']) ? '1' : '0');
    set_setting('schema_enabled', isset($_POST['schema_enabled']) ? '1' : '0');
    set_setting('robots_txt', trim($_POST['robots_txt'] ?? ''));
    flash('success', 'Branding & SEO settings saved.');
    header('Location: /admin/branding.php');
    exit;
}

$activeNav = 'branding';
$pageTitle = 'Branding';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<form method="POST">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><div><p class="card-title">Site identity</p></div></div>
    <div class="row">
      <div class="field"><label>Site name</label><input type="text" name="site_name" value="<?= htmlspecialchars(get_setting('site_name')) ?>"></div>
      <div class="field"><label>Tagline</label><input type="text" name="tagline" value="<?= htmlspecialchars(get_setting('tagline')) ?>"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><p class="card-title">SEO</p></div></div>
    <div class="row">
      <div class="field"><label>Meta title template</label><input type="text" class="mono" name="meta_title_template" value="<?= htmlspecialchars(get_setting('meta_title_template', '{video_title} — {site_name}')) ?>"></div>
      <div class="field"><label>Meta description template</label><input type="text" name="meta_description_template" value="<?= htmlspecialchars(get_setting('meta_description_template')) ?>"></div>
    </div>
    <div class="switch-row">
      <div><div class="switch-label">Auto-generate sitemap.xml</div></div>
      <label class="switch"><input type="checkbox" name="sitemap_enabled" <?= get_setting('sitemap_enabled','1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
    </div>
    <div class="switch-row">
      <div><div class="switch-label">Structured data (VideoObject)</div></div>
      <label class="switch"><input type="checkbox" name="schema_enabled" <?= get_setting('schema_enabled','1') === '1' ? 'checked' : '' ?>><span class="slider"></span></label>
    </div>
    <div class="field" style="margin-top:14px;"><label>robots.txt</label>
      <textarea name="robots_txt" class="mono"><?= htmlspecialchars(get_setting('robots_txt', "User-agent: *\nAllow: /\nSitemap: /sitemap.xml")) ?></textarea>
    </div>
    <div class="actions-row"><button type="submit" class="btn btn-primary">Save settings</button></div>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
