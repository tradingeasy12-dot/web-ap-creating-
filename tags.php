<?php
/**
 * Trending tags — crawlable page listing all tags with at least one published
 * video, ordered by how many published videos use them. Controlled by the
 * "Auto-generate trending tags page" toggle in Admin → SEO Growth.
 */
require_once __DIR__ . '/includes/site_header.php';

if (site_setting('seo_trending_tags_page', '0') !== '1') {
    http_response_code(404);
    require_once __DIR__ . '/includes/site_footer.php';
    exit;
}

$tags = db()->query(
    "SELECT t.name, t.slug, COUNT(vt.video_id) AS video_count
     FROM tags t
     JOIN video_tags vt ON vt.tag_id = t.id
     JOIN videos v ON v.id = vt.video_id AND v.status = 'published'
     GROUP BY t.id
     ORDER BY video_count DESC, t.name ASC"
)->fetchAll();
?>

<div class="page">
  <div class="section-title"><h1>Trending tags</h1></div>

  <?php if (!$tags): ?>
    <p style="color:var(--text-faint);">No tagged videos published yet.</p>
  <?php else: ?>
    <div class="tag-cloud">
      <?php foreach ($tags as $t): ?>
        <a class="tag-pill" href="/index.php?tag=<?= urlencode($t['slug']) ?>">
          #<?= htmlspecialchars($t['name']) ?>
          <span class="tag-count"><?= (int) $t['video_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/site_footer.php'; ?>
