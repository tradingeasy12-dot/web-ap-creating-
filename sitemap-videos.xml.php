<?php
/**
 * Video sitemap — separate from a general sitemap.xml, this format lets
 * search engines discover video-specific metadata (thumbnail, duration,
 * publish date) directly. Controlled by the "Auto-generate video sitemap"
 * toggle in Admin → SEO Growth.
 *
 * Map this file to a clean URL in your host if you want it at exactly
 * /sitemap-videos.xml (most hosts serve *.php as-is via that filename anyway).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (site_setting('seo_video_sitemap_enabled', '1') !== '1') {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Video sitemap is disabled in Admin → SEO Growth.';
    exit;
}

$videos = db()->query(
    "SELECT slug, title, description, thumbnail_path, duration_seconds, created_at, updated_at
     FROM videos WHERE status = 'published' ORDER BY created_at DESC"
)->fetchAll();

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
<?php foreach ($videos as $v): ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/watch.php?slug=' . urlencode($v['slug'])) ?></loc>
    <video:video>
      <video:thumbnail_loc><?= htmlspecialchars($v['thumbnail_path'] ? asset_url($v['thumbnail_path']) : $baseUrl . '/assets/images/default-thumb.jpg') ?></video:thumbnail_loc>
      <video:title><?= htmlspecialchars($v['title']) ?></video:title>
      <video:description><?= htmlspecialchars($v['description'] ?: $v['title']) ?></video:description>
      <?php if ($v['duration_seconds']): ?>
      <video:duration><?= (int) $v['duration_seconds'] ?></video:duration>
      <?php endif; ?>
      <video:publication_date><?= date('c', strtotime($v['created_at'])) ?></video:publication_date>
      <video:family_friendly>no</video:family_friendly>
    </video:video>
    <lastmod><?= date('c', strtotime($v['updated_at'] ?? $v['created_at'])) ?></lastmod>
  </url>
<?php endforeach; ?>
</urlset>
