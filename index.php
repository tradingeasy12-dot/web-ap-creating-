<?php
require_once __DIR__ . '/includes/site_header.php';

$categorySlug = $_GET['category'] ?? null;
$tagSlug      = $_GET['tag'] ?? null;
$heading      = 'Recommended for you';

if ($categorySlug) {
    $stmt = db()->prepare(
        "SELECT v.* FROM videos v JOIN categories c ON c.id = v.category_id
         WHERE v.status = 'published' AND c.slug = ? ORDER BY v.created_at DESC LIMIT 40"
    );
    $stmt->execute([$categorySlug]);
    $catRow = db()->prepare('SELECT name FROM categories WHERE slug = ?');
    $catRow->execute([$categorySlug]);
    if ($name = $catRow->fetchColumn()) {
        $heading = htmlspecialchars($name) . ' videos';
    }
} elseif ($tagSlug) {
    $stmt = db()->prepare(
        "SELECT v.* FROM videos v
         JOIN video_tags vt ON vt.video_id = v.id
         JOIN tags t ON t.id = vt.tag_id
         WHERE v.status = 'published' AND t.slug = ? ORDER BY v.created_at DESC LIMIT 40"
    );
    $stmt->execute([$tagSlug]);
    $tagRow = db()->prepare('SELECT name FROM tags WHERE slug = ?');
    $tagRow->execute([$tagSlug]);
    if ($name = $tagRow->fetchColumn()) {
        $heading = '#' . htmlspecialchars($name) . ' videos';
    }
} else {
    $stmt = db()->query("SELECT * FROM videos WHERE status = 'published' ORDER BY created_at DESC LIMIT 40");
}
$videos = $stmt->fetchAll();
?>

<div class="page">
  <div class="page-layout">
    <main>
      <?php render_ad_slot('homepage_banner'); ?>
      <div class="section-title"><h1><?= $heading ?></h1></div>

      <?php
        $firstHalf  = array_slice($videos, 0, (int) ceil(count($videos) / 2));
        $secondHalf = array_slice($videos, (int) ceil(count($videos) / 2));
      ?>

      <?php if (!$videos): ?>
        <p style="color:var(--text-faint);">No videos published yet.</p>
      <?php else: ?>
        <?php render_video_grid($firstHalf); ?>
        <?php if ($secondHalf): ?>
          <?php render_ad_slot('content_middle_banner'); ?>
          <?php render_video_grid($secondHalf); ?>
        <?php else: ?>
          <?php render_ad_slot('content_middle_banner'); ?>
        <?php endif; ?>
      <?php endif; ?>
    </main>

    <aside class="page-aside">
      <?php render_ad_slot('sidebar_banner'); ?>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/site_footer.php'; ?>