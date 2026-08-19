<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

// ---- Delete a video entirely ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int) $_POST['video_id'];
    $stmt = db()->prepare('SELECT storage_path FROM videos WHERE id = ?');
    $stmt->execute([$id]);
    $video = $stmt->fetch();
    if ($video && $video['storage_path']) {
        $path = __DIR__ . '/../' . $video['storage_path'];
        if (file_exists($path)) {
            unlink($path);
        }
    }
    db()->prepare('DELETE FROM videos WHERE id = ?')->execute([$id]);
    flash('success', 'Video deleted.');
    header('Location: /admin/videos.php');
    exit;
}

// ---- Publish / Pause toggle ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    csrf_check();
    $id = (int) $_POST['video_id'];
    $stmt = db()->prepare('SELECT status FROM videos WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();
    $newStatus = $current === 'published' ? 'draft' : 'published';
    db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
    flash('success', $newStatus === 'published' ? 'Video published.' : 'Video paused (hidden from visitors).');
    header('Location: /admin/videos.php');
    exit;
}

// ---- Inline tag manager: replace this video's tags from a comma list ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_tags') {
    csrf_check();
    $id = (int) $_POST['video_id'];
    $tagsInput = trim($_POST['tags'] ?? '');

    db()->prepare('DELETE FROM video_tags WHERE video_id = ?')->execute([$id]);
    foreach (array_filter(array_map('trim', explode(',', $tagsInput))) as $tagName) {
        $tagSlug = slugify($tagName);
        db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id')
            ->execute([$tagName, $tagSlug]);
        $tagId = (int) db()->query('SELECT id FROM tags WHERE slug = ' . db()->quote($tagSlug))->fetchColumn();
        if ($tagId) {
            db()->prepare('INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)')->execute([$id, $tagId]);
        }
    }
    flash('success', 'Tags updated.');
    header('Location: /admin/videos.php');
    exit;
}

// ---- Main list, with type, tags, and simple traffic numbers ----
$videos = db()->query(
    "SELECT v.id, v.title, v.status, v.upload_type, v.views_count, c.name AS category_name,
            GROUP_CONCAT(t.name SEPARATOR ', ') AS tag_names
     FROM videos v
     LEFT JOIN categories c ON c.id = v.category_id
     LEFT JOIN video_tags vt ON vt.video_id = v.id
     LEFT JOIN tags t ON t.id = vt.tag_id
     GROUP BY v.id
     ORDER BY v.created_at DESC"
)->fetchAll();

// Today's traffic per video (0 if traffic_daily has no row for today yet — needs the daily rollup job to be running)
$todayTraffic = [];
$rows = db()->query("SELECT video_id, views FROM traffic_daily WHERE stat_date = CURDATE() AND video_id IS NOT NULL")->fetchAll();
foreach ($rows as $r) {
    $todayTraffic[$r['video_id']] = (int) $r['views'];
}

$activeNav = 'videos';
$pageTitle = 'All Videos';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<div class="card">
  <div class="card-head">
    <div><p class="card-title">All videos</p><p class="card-sub">Manage published, paused, and draft content — uploads and embeds together.</p></div>
    <a href="/admin/upload-video.php" class="btn btn-primary btn-sm">+ Upload video</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Title</th><th>Type</th><th>Category</th><th>Status</th><th>Views (total / today)</th><th>Tags</th><th></th></tr>
      <?php if (!$videos): ?>
        <tr><td colspan="7" style="color:var(--text-faint);">No videos yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($videos as $v): ?>
        <tr>
          <td><?= htmlspecialchars($v['title']) ?></td>
          <td>
            <span class="hint" style="font-weight:600;color:var(--text-dim);">
              <?= $v['upload_type'] === 'embed' ? '🔗 Embed' : '📁 Self-hosted' ?>
            </span>
          </td>
          <td><?= htmlspecialchars($v['category_name'] ?? '—') ?></td>
          <td>
            <span class="status-dot <?= $v['status'] === 'published' ? 'status-ok' : 'status-warn' ?>"></span>
            <?= $v['status'] === 'published' ? 'Published' : ucfirst($v['status']) ?>
          </td>
          <td>
            <?= number_format($v['views_count']) ?> / <?= number_format($todayTraffic[$v['id']] ?? 0) ?>
          </td>
          <td style="max-width:160px;">
            <span class="hint"><?= htmlspecialchars($v['tag_names'] ?: '—') ?></span>
          </td>
          <td style="white-space:nowrap;">
            <a class="btn btn-ghost btn-sm" href="/admin/upload-video.php?id=<?= $v['id'] ?>">Edit</a>

            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">
                <?= $v['status'] === 'published' ? 'Pause' : 'Publish' ?>
              </button>
            </form>

            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleTagBox(<?= $v['id'] ?>)">Tags</button>

            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this video permanently?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <tr id="tagRow-<?= $v['id'] ?>" style="display:none;">
          <td colspan="7" style="background:var(--panel-2);">
            <form method="POST" style="display:flex;gap:10px;align-items:center;padding:6px 0;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_tags">
              <input type="hidden" name="video_id" value="<?= $v['id'] ?>">
              <label style="margin:0;font-size:12.5px;color:var(--text-faint);white-space:nowrap;">Tags for this video:</label>
              <input type="text" name="tags" value="<?= htmlspecialchars($v['tag_names'] ?? '') ?>"
                     placeholder="tag-one, tag-two, tag-three" style="flex:1;">
              <button type="submit" class="btn btn-primary btn-sm">Save tags</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="hint" style="margin-top:12px;">
    "Today" traffic needs the daily rollup job running (see <span class="mono">traffic_daily</span> table) — it'll show 0 until that's set up.
  </div>
</div>

<script>
  function toggleTagBox(id){
    const row = document.getElementById('tagRow-' + id);
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
  }
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
