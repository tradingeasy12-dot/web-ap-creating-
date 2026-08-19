<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $label = trim($_POST['button_text'] ?? '');
        $body  = trim($_POST['popup_content'] ?? '');
        if ($label !== '' && $body !== '') {
            $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM footer_pages')->fetchColumn();
            db()->prepare('INSERT INTO footer_pages (button_text, popup_content, is_visible, sort_order) VALUES (?, ?, 1, ?)')
                ->execute([$label, $body, $maxOrder + 1]);
            flash('success', 'Footer button added.');
        }
    } elseif ($action === 'update') {
        $id = (int) $_POST['page_id'];
        db()->prepare('UPDATE footer_pages SET button_text=?, popup_content=?, is_visible=? WHERE id=?')
            ->execute([trim($_POST['button_text']), trim($_POST['popup_content']), isset($_POST['is_visible']) ? 1 : 0, $id]);
        flash('success', 'Footer button updated.');
    } elseif ($action === 'toggle_visible') {
        $stmt = db()->prepare('SELECT is_visible FROM footer_pages WHERE id = ?');
        $stmt->execute([(int) $_POST['page_id']]);
        $current = $stmt->fetchColumn();
        db()->prepare('UPDATE footer_pages SET is_visible = ? WHERE id = ?')->execute([$current ? 0 : 1, (int) $_POST['page_id']]);
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM footer_pages WHERE id = ?')->execute([(int) $_POST['page_id']]);
        flash('success', 'Footer button deleted.');
    }
    header('Location: /admin/footerpages.php');
    exit;
}

$pages = db()->query('SELECT * FROM footer_pages ORDER BY sort_order')->fetchAll();

$activeNav = 'footerpages';
$pageTitle = 'Footer Pages';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<div class="card">
  <div class="card-head"><div><p class="card-title">Footer buttons</p><p class="card-sub">Each button shows a popup with its own text when clicked.</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Button text</th><th>Visible</th><th></th></tr>
      <?php foreach ($pages as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['button_text']) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle_visible">
              <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">
                <span class="status-dot <?= $p['is_visible'] ? 'status-ok' : 'status-warn' ?>"></span><?= $p['is_visible'] ? 'Visible' : 'Hidden' ?>
              </button>
            </form>
          </td>
          <td>
            <button type="button" class="btn btn-ghost btn-sm" onclick="openPreview('<?= htmlspecialchars(addslashes($p['button_text'])) ?>','<?= htmlspecialchars(addslashes($p['popup_content'])) ?>')">Preview</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('edit-<?= $p['id'] ?>').style.display='block'">Edit</button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this button?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete">
              <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php foreach ($pages as $p): ?>
  <div class="card" id="edit-<?= $p['id'] ?>" style="display:none;">
    <div class="card-head"><div><p class="card-title">Edit: <?= htmlspecialchars($p['button_text']) ?></p></div></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
      <div class="field"><label>Button text</label><input type="text" name="button_text" value="<?= htmlspecialchars($p['button_text']) ?>" required></div>
      <div class="field"><label>Popup content</label><textarea name="popup_content" required><?= htmlspecialchars($p['popup_content']) ?></textarea></div>
      <div class="switch-row">
        <div><div class="switch-label">Show in footer</div></div>
        <label class="switch"><input type="checkbox" name="is_visible" <?= $p['is_visible'] ? 'checked' : '' ?>><span class="slider"></span></label>
      </div>
      <div class="actions-row">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-<?= $p['id'] ?>').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
    </form>
  </div>
<?php endforeach; ?>

<div class="card">
  <div class="card-head"><div><p class="card-title">New footer button</p></div></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field"><label>Button text</label><input type="text" name="button_text" required placeholder="e.g. Careers"></div>
    <div class="field"><label>Popup content</label><textarea name="popup_content" required></textarea></div>
    <div class="actions-row"><button type="submit" class="btn btn-primary">+ Add button</button></div>
  </form>
</div>

<div class="pv-overlay" id="pvOverlay" style="display:none;position:fixed;inset:0;z-index:999;background:rgba(6,7,10,.72);align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)closePreview()">
  <div style="background:var(--panel);border:1px solid var(--border);border-radius:14px;max-width:420px;width:100%;padding:24px;position:relative;">
    <div onclick="closePreview()" style="position:absolute;top:12px;right:12px;cursor:pointer;color:var(--text-faint);">✕</div>
    <h3 id="pvTitle" style="margin:0 0 10px;"></h3>
    <p id="pvBody" style="color:var(--text-dim);font-size:13px;line-height:1.6;margin:0;"></p>
  </div>
</div>

<script>
function openPreview(title, body){
  document.getElementById('pvTitle').textContent = title;
  document.getElementById('pvBody').textContent = body;
  document.getElementById('pvOverlay').style.display = 'flex';
}
function closePreview(){ document.getElementById('pvOverlay').style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
