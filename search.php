<?php
require_once __DIR__ . '/includes/site_header.php';

$q = trim($_GET['q'] ?? '');
$videos = [];

if ($q !== '') {
    $stmt = db()->prepare(
        "SELECT * FROM videos WHERE status = 'published' AND (title LIKE ? OR description LIKE ?)
         ORDER BY created_at DESC LIMIT 40"
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    $videos = $stmt->fetchAll();
}
?>

<div class="page">
  <div class="page-layout">
    <main>
      <div class="section-title">
        <h1><?= $q !== '' ? 'Search results for "' . htmlspecialchars($q) . '"' : 'Search' ?></h1>
      </div>

      <?php if ($q === ''): ?>
        <p style="color:var(--text-faint);">Type something in the search box above to find videos.</p>
      <?php elseif (!$videos): ?>
        <p style="color:var(--text-faint);">No videos matched "<?= htmlspecialchars($q) ?>".</p>
      <?php else: ?>
        <?php render_video_grid($videos); ?>
      <?php endif; ?>
    </main>

    <aside class="page-aside">
      <?php render_ad_slot('sidebar_banner'); ?>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
