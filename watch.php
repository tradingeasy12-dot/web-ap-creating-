<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM videos WHERE slug = ? AND status = "published"');
$stmt->execute([$slug]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    die('Video not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react'])) {
    csrf_check();
    $already = $_SESSION['reacted'][$video['id']] ?? null;
    $react = $_POST['react'] === 'like' ? 'like' : 'dislike';

    if ($already !== $react) {
        if ($already === 'like') { db()->prepare('UPDATE videos SET likes_count = likes_count - 1 WHERE id = ?')->execute([$video['id']]); }
        if ($already === 'dislike') { db()->prepare('UPDATE videos SET dislikes_count = dislikes_count - 1 WHERE id = ?')->execute([$video['id']]); }
        $col = $react === 'like' ? 'likes_count' : 'dislikes_count';
        db()->prepare("UPDATE videos SET {$col} = {$col} + 1 WHERE id = ?")->execute([$video['id']]);
        $_SESSION['reacted'][$video['id']] = $react;
    }
    header('Location: /watch.php?slug=' . urlencode($slug));
    exit;
}

if (empty($_SESSION['viewed'][$video['id']])) {
    try {
        db()->prepare('UPDATE videos SET views_count = views_count + 1 WHERE id = ?')->execute([$video['id']]);
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $countryCode = get_visitor_country();
        db()->prepare('INSERT INTO video_views (video_id, ip_hash, country_code) VALUES (?, ?, ?)')->execute([$video['id'], $ipHash, $countryCode]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'country_code') || str_contains($e->getMessage(), 'column')) {
            try {
                db()->exec('ALTER TABLE video_views ADD COLUMN country_code VARCHAR(2) NULL');
                db()->prepare('INSERT INTO video_views (video_id, ip_hash, country_code) VALUES (?, ?, ?)')->execute([$video['id'], $ipHash, $countryCode]);
            } catch (PDOException $e2) {
                try {
                    db()->prepare('INSERT INTO video_views (video_id, ip_hash) VALUES (?, ?)')->execute([$video['id'], $ipHash]);
                } catch (PDOException $e3) {}
            }
        } else {
            try {
                db()->prepare('INSERT INTO video_views (video_id, ip_hash) VALUES (?, ?)')->execute([$video['id'], $ipHash]);
            } catch (PDOException $e4) {}
        }
    }
    $_SESSION['viewed'][$video['id']] = true;
    $video['views_count']++;
}

$tags = db()->prepare('SELECT t.name, t.slug FROM tags t JOIN video_tags vt ON vt.tag_id = t.id WHERE vt.video_id = ?');
$tags->execute([$video['id']]);
$tags = $tags->fetchAll();

$relatedEnabled = site_setting('seo_related_videos_enabled', '1') === '1';
$relatedCount   = max(1, min(30, (int) site_setting('seo_related_count', '10')));
$related = [];

if ($relatedEnabled) {
    $stmt = db()->prepare(
        "SELECT * FROM videos WHERE status='published' AND category_id = ? AND id != ? ORDER BY views_count DESC LIMIT {$relatedCount}"
    );
    $stmt->execute([$video['category_id'], $video['id']]);
    $related = $stmt->fetchAll();

    if (count($related) < $relatedCount) {
        $need = $relatedCount - count($related);
        $exclude = array_merge([$video['id']], array_column($related, 'id'));
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));
        $fill = db()->prepare("SELECT * FROM videos WHERE status='published' AND id NOT IN ({$placeholders}) ORDER BY views_count DESC LIMIT {$need}");
        $fill->execute($exclude);
        $related = array_merge($related, $fill->fetchAll());
    }
}

$reacted = $_SESSION['reacted'][$video['id']] ?? null;
$pageTitle = $video['title'];
require_once __DIR__ . '/includes/site_header.php';
?>

 <?php render_ad_slot('homepage_banner'); ?>

  <div class="player-shell">
    <?php
      $embedCode  = trim($video['embed_code'] ?? '');
      $isRawMarkup = $video['upload_type'] === 'embed' && $embedCode !== '' && str_contains($embedCode, '<');
      $isEmbedPageUrl = $video['upload_type'] === 'embed' && $embedCode !== '' && !$isRawMarkup && (
          str_contains($embedCode, '/e/') ||
          str_contains($embedCode, '/embed/') ||
          str_contains($embedCode, 'streamtape.') ||
          str_contains($embedCode, 'dood.') ||
          str_contains($embedCode, 'filemoon.') ||
          str_contains($embedCode, 'voe.sx')
      );

      if ($isRawMarkup):
    ?>
      <?= $embedCode ?>
    <?php
      elseif ($isEmbedPageUrl):
    ?>
      <iframe src="<?= htmlspecialchars($embedCode) ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="width:100%;height:100%;border:0;"></iframe>
    <?php
      else:
        $directSrc = $video['upload_type'] === 'embed' ? $embedCode : asset_url($video['storage_path']);
        if ($directSrc):
          $prerollAdUrl = null;
          if (!empty($video['ads_enabled'])) {
              if (!empty($video['preroll_ad_id'])) {
                  $stmtAd = db()->prepare("SELECT ad_code FROM ad_library WHERE id = ? AND status = 'active'");
                  $stmtAd->execute([$video['preroll_ad_id']]);
                  $prerollAdUrl = $stmtAd->fetchColumn() ?: null;
              }
              if (!$prerollAdUrl) {
                  $stmtSlot = db()->query("SELECT s.custom_ad_code, a.ad_code FROM ad_slots s LEFT JOIN ad_library a ON a.id = s.ad_library_id AND a.status = 'active' WHERE s.slot_key = 'preroll' AND s.is_enabled = 1");
                  if ($rowSlot = $stmtSlot->fetch()) {
                      $prerollAdUrl = trim($rowSlot['custom_ad_code'] ?: ($rowSlot['ad_code'] ?? ''));
                  }
              }
          }

          $playerParams = [
              'title'  => $video['title'],
              'poster' => $video['thumbnail_path'] ? asset_url($video['thumbnail_path']) : '',
              'src'    => $directSrc,
          ];
          if ($prerollAdUrl) {
              $playerParams['preroll'] = $prerollAdUrl;
          }
          $playerParams['v'] = filemtime(__DIR__ . '/player.html');
          $playerUrl = '/player.html?' . http_build_query($playerParams);
    ?>
      <iframe src="<?= htmlspecialchars($playerUrl) ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="width:100%;height:100%;border:0;"></iframe>
    <?php
        endif;
      endif;
    ?>
  </div>

  <h1 class="watch-title"><?= htmlspecialchars($video['title']) ?></h1>
  <div class="watch-meta"><?= number_format($video['views_count']) ?> views · <?= date('M j, Y', strtotime($video['created_at'])) ?></div>

  <div class="watch-actions">
    <form method="POST" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="react" value="like">
      <button type="submit" class="react-btn <?= $reacted === 'like' ? 'active' : '' ?>">👍 <?= number_format($video['likes_count']) ?></button>
    </form>
    <form method="POST" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="react" value="dislike">
      <button type="submit" class="react-btn <?= $reacted === 'dislike' ? 'active' : '' ?>">👎 <?= number_format($video['dislikes_count']) ?></button>
    </form>
    <button type="button" class="react-btn" id="shareBtn" onclick="copyShareLink()">🔗 Share</button>
    <?php if (!empty($video['source_url'])): ?>
      <a href="<?= htmlspecialchars($video['source_url']) ?>" target="_blank" rel="noopener noreferrer" class="react-btn" style="text-decoration:none;">🌐 Source</a>
    <?php endif; ?>
  </div>

  <?php if ($tags): ?>
    <div class="watch-tags">
      <?php foreach ($tags as $t): ?>
        <a href="/index.php?tag=<?= urlencode($t['slug']) ?>">#<?= htmlspecialchars($t['name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($video['description']): ?>
    <p class="watch-desc"><?= nl2br(htmlspecialchars($video['description'])) ?></p>
  <?php endif; ?>
 
  <?php render_ad_slot('content_middle_banner'); ?>

  <?php if ($relatedEnabled && $related): ?>
    <div class="section-title" style="margin:26px 0 14px;"><h2>Related videos</h2></div>
    <?php render_video_grid($related); ?>
  <?php endif; ?>
</div>
</main>


<script>
function copyShareLink() {
    var shareBtn = document.getElementById('shareBtn');
    var dummy = document.createElement('input');
    var text = window.location.href;
    
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).catch(function(){});
    }
    
    var originalText = shareBtn.innerHTML;
    shareBtn.innerHTML = "✓ Copied!";
    shareBtn.style.color = "var(--good)";
    setTimeout(function() {
        shareBtn.innerHTML = originalText;
        shareBtn.style.color = "";
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/includes/site_footer.php'; ?>