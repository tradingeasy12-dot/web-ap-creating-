<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$totalViews = (int) db()->query('SELECT COALESCE(SUM(views_count),0) FROM videos')->fetchColumn();

$todayViews = (int) db()->query(
    "SELECT COUNT(*) FROM video_views WHERE viewed_at >= CURDATE()"
)->fetchColumn();

$uniqueVisitors7d = (int) db()->query(
    "SELECT COUNT(DISTINCT ip_hash) FROM video_views WHERE viewed_at >= NOW() - INTERVAL 7 DAY"
)->fetchColumn();

$topVideos = db()->query(
    "SELECT v.id, v.title, c.name AS category_name, v.views_count,
            (SELECT COUNT(*) FROM video_views vv WHERE vv.video_id = v.id AND vv.viewed_at >= CURDATE()) AS today_views
     FROM videos v
     LEFT JOIN categories c ON c.id = v.category_id
     ORDER BY v.views_count DESC
     LIMIT 10"
)->fetchAll();

// কান্ট্রি কোড কলাম অনুপস্থিত থাকলে অটো-ফিক্স ও কুয়েরি রান লজিক
$byCountry = [];
try {
    $byCountry = db()->query(
        "SELECT country_code, COUNT(*) AS views
         FROM video_views
         WHERE viewed_at >= NOW() - INTERVAL 7 DAY AND country_code IS NOT NULL
         GROUP BY country_code
         ORDER BY views DESC
         LIMIT 8"
    )->fetchAll();
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'country_code') || str_contains($e->getMessage(), 'column')) {
        try {
            db()->exec('ALTER TABLE video_views ADD COLUMN country_code VARCHAR(2) NULL');
            $byCountry = db()->query(
                "SELECT country_code, COUNT(*) AS views
                 FROM video_views
                 WHERE viewed_at >= NOW() - INTERVAL 7 DAY AND country_code IS NOT NULL
                 GROUP BY country_code
                 ORDER BY views DESC
                 LIMIT 8"
            )->fetchAll();
        } catch (PDOException $e2) {}
    }
}
$maxCountryViews = $byCountry ? max(array_column($byCountry, 'views')) : 1;

$adSlotLabels = [
    'preroll' => 'Pre-roll', 'midroll' => 'Mid-roll', 'postroll' => 'Post-roll',
    'homepage_banner' => 'Homepage banner', 'sidebar_banner' => 'Sidebar banner',
    'content_middle_banner' => 'Middle bar', 'footer_banner' => 'Footer bar',
    'popunder' => 'Popunder / Direct Link',
];
$adSlots = db()->query(
    "SELECT s.slot_key, s.is_enabled, s.custom_ad_code, a.name AS ad_name
     FROM ad_slots s LEFT JOIN ad_library a ON a.id = s.ad_library_id
     ORDER BY FIELD(s.slot_key, 'preroll','midroll','postroll','homepage_banner','sidebar_banner','content_middle_banner','footer_banner','popunder')"
)->fetchAll();

$activeNav = 'dashboard';
$pageTitle = 'Dashboard & Traffic';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total views</div>
    <div class="stat-value"><?= number_format($totalViews) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Traffic today</div>
    <div class="stat-value"><?= number_format($todayViews) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Unique visitors (7d)</div>
    <div class="stat-value"><?= number_format($uniqueVisitors7d) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Videos published</div>
    <div class="stat-value"><?= number_format((int) db()->query("SELECT COUNT(*) FROM videos WHERE status='published'")->fetchColumn()) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><p class="card-title">Traffic by video</p><p class="card-sub">All-time views, ranked.</p></div>
  </div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>#</th><th>Video</th><th>Category</th><th>Views</th><th>Today</th></tr>
      <?php if (!$topVideos): ?>
        <tr><td colspan="5" style="color:var(--text-faint);">No videos yet — upload one to see traffic here.</td></tr>
      <?php endif; ?>
      <?php foreach ($topVideos as $i => $v): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($v['title']) ?></td>
          <td><?= htmlspecialchars($v['category_name'] ?? '—') ?></td>
          <td><?= number_format($v['views_count']) ?></td>
          <td><?= number_format($v['today_views']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><p class="card-title">Traffic by country</p><p class="card-sub">Last 7 days, based on visitor IP.</p></div>
  </div>
  <?php if (!$byCountry): ?>
    <p class="hint">No traffic recorded yet.</p>
  <?php endif; ?>
  <?php foreach ($byCountry as $row): ?>
    <?php $pct = round(($row['views'] / $maxCountryViews) * 100); ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <span style="width:34px;font-size:12.5px;color:var(--text-dim);"><?= htmlspecialchars($row['country_code']) ?></span>
      <div style="flex:1;height:7px;border-radius:100px;background:var(--panel-2);overflow:hidden;">
        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--accent),#7B5CFF);"></div>
      </div>
      <span style="width:70px;text-align:right;font-size:12.5px;color:var(--text-dim);"><?= number_format($row['views']) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-head">
    <div><p class="card-title">Ads overview</p><p class="card-sub">Every slot's current status — manage these in Ads Settings.</p></div>
    <a href="/admin/ads.php" class="btn btn-ghost btn-sm">Manage ads</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Slot</th><th>Status</th><th>Assigned ad</th></tr>
      <?php foreach ($adSlots as $s): ?>
        <tr>
          <td><?= htmlspecialchars($adSlotLabels[$s['slot_key']] ?? $s['slot_key']) ?></td>
          <td>
            <span class="status-dot <?= $s['is_enabled'] ? 'status-ok' : 'status-warn' ?>"></span>
            <?= $s['is_enabled'] ? 'On' : 'Off' ?>
          </td>
          <td style="color:var(--text-faint);">
            <?php if (!empty($s['custom_ad_code'])): ?>
              Custom code for this slot
            <?php elseif (!empty($s['ad_name'])): ?>
              <?= htmlspecialchars($s['ad_name']) ?>
            <?php else: ?>
              — none set —
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>