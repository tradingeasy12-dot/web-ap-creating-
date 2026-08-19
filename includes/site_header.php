<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$siteName = site_setting('site_name', 'My Video Site');
$gate = db()->query('SELECT * FROM age_gate_settings WHERE id = 1')->fetch();
$categories = db()->query('SELECT id, name, slug FROM categories ORDER BY sort_order, name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? $siteName) ?></title>
<?php if (site_setting('seo_canonical_enabled', '1') === '1'): ?>
<link rel="canonical" href="<?= htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
<?php endif; ?>
<?php if (site_setting('seo_gsc_enabled', '0') === '1'):
    $gscTag = trim(site_setting('seo_gsc_verification_tag', ''));
    if ($gscTag !== '') { echo $gscTag . "\n"; }
endif; ?>
<link rel="stylesheet" href="/<?= htmlspecialchars(css_url('assets/css/site.css')) ?>">
</head>
<body>

<?php if ($gate && $gate['is_enabled'] && empty($_COOKIE['age_verified'])): ?>
<div class="gate" id="gate">
  <div class="gate-card">
    <div class="gate-badge">18+</div>
    <h2><?= htmlspecialchars($gate['heading']) ?></h2>
    <p><?= htmlspecialchars($gate['body_text']) ?></p>
    <div class="gate-btns">
      <button class="gate-enter" onclick="confirmAge()"><?= htmlspecialchars($gate['button_text']) ?></button>
      <button class="gate-exit" onclick="window.location.href='<?= htmlspecialchars($gate['exit_redirect_url']) ?>'">Exit</button>
    </div>
  </div>
</div>
<script>
function confirmAge(){
  const days = <?= $gate['remember_duration'] === '30_days' ? 30 : ($gate['remember_duration'] === '1_day' ? 1 : 0) ?>;
  if(days > 0){
    const d = new Date(); d.setTime(d.getTime() + days*24*60*60*1000);
    document.cookie = "age_verified=1; expires=" + d.toUTCString() + "; path=/";
  } else {
    document.cookie = "age_verified=1; path=/";
  }
  document.getElementById('gate').style.display = 'none';
}
</script>
<?php endif; ?>

<header>
  <div class="header-top">
    <a class="logo" href="/index.php">
      <div class="logo-mark"><?= htmlspecialchars(substr($siteName, 0, 1)) ?></div>
      <div class="logo-name"><?= htmlspecialchars($siteName) ?></div>
    </a>
    <form class="search-wrap" action="/search.php" method="GET">
      <input type="text" name="q" placeholder="Search videos, categories…">
      <button type="submit" class="search-submit">Search</button>
    </form>
  </div>
  <nav class="cat-nav">
    <a class="cat-chip <?= empty($_GET['category']) ? 'active' : '' ?>" href="/index.php">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="cat-chip <?= ($_GET['category'] ?? '') === $c['slug'] ? 'active' : '' ?>" href="/index.php?category=<?= urlencode($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a>
    <?php endforeach; ?>
    <?php if (site_setting('seo_trending_tags_page', '0') === '1'): ?>
      <a class="cat-chip" href="/tags.php"></a>
    <?php endif; ?>
  </nav>
</header>
