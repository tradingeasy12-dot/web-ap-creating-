<?php
// Expects $activeNav (string key) and $pageTitle (string) to be set by the including page.
$activeNav  = $activeNav  ?? '';
$pageTitle  = $pageTitle  ?? 'Admin';
$admin      = current_admin();

$navItems = [
    'dashboard'   => ['📊 Dashboard & Traffic', '/admin/dashboard.php', 'Overview'],
    'upload'      => ['📤 Upload Video', '/admin/upload-video.php', 'Content'],
    'videos'      => ['🎞 All Videos', '/admin/videos.php', 'Content'],
    'categories'  => ['🗂 Categories & Tags', '/admin/categories.php', 'Content'],
    'ads'         => ['📢 Ads Settings', '/admin/ads.php', 'Monetization'],
    'agegate'     => ['🔒 Age Verification', '/admin/agegate.php', 'Access & Safety'],
    'storage'     => ['☁️ Storage API', '/admin/storage.php', 'Infrastructure'],
    'branding'    => ['🎨 Branding', '/admin/branding.php', 'Discovery'],
    'seo'         => ['🔍 SEO Growth', '/admin/seo.php', 'Discovery'],
    'footerpages' => ['🧾 Footer Pages', '/admin/footerpages.php', 'Discovery'],
];

$groups = [];
foreach ($navItems as $key => $item) {
    $groups[$item[2]][$key] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — Admin</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="overlay" id="overlay" onclick="document.getElementById('sidebar').classList.remove('open');this.classList.remove('open');"></div>

<div class="app">
  <div class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-mark">S</div>
      <div>
        <div class="brand-name">Studio Admin</div>
        <div class="brand-sub">platform control</div>
      </div>
    </div>

    <?php foreach ($groups as $groupName => $items): ?>
      <div class="nav-group-label"><?= htmlspecialchars($groupName) ?></div>
      <?php foreach ($items as $key => $item): ?>
        <a class="nav-item <?= $activeNav === $key ? 'active' : '' ?>" href="<?= $item[1] ?>"><?= $item[0] ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <div class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="hamburger" onclick="document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('open');"><span></span></div>
        <div>
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
          <div class="path"><?= htmlspecialchars($navItems[$activeNav][2] ?? '') ?> / <?= htmlspecialchars($pageTitle) ?></div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:12px;color:var(--text-faint);"><?= htmlspecialchars($admin['email'] ?? '') ?></span>
        <div class="avatar"><?= htmlspecialchars(strtoupper(substr($admin['name'] ?? 'A', 0, 2))) ?></div>
        <a href="/logout.php" class="btn btn-ghost btn-sm">Log out</a>
      </div>
    </div>

    <div class="content">
