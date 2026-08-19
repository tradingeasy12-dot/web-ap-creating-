<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

// 1. Fetch category details if edit_id is provided in the URL
$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : null;
$editCategory = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Handle add category action
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $slug = unique_slug('categories', $name);
            db()->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            flash('success', 'Category added.');
        }
    // 2. Handle edit/update category action
    } elseif ($action === 'edit') {
        $id = (int) $_POST['category_id'];
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($name !== '' && $id) {
            $finalSlug = $slug !== '' ? unique_slug('categories', $slug, $id) : unique_slug('categories', $name, $id);
            db()->prepare('UPDATE categories SET name = ?, slug = ? WHERE id = ?')
                ->execute([$name, $finalSlug, $id]);
            flash('success', 'Category updated.');
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $_POST['category_id']]);
        flash('success', 'Category deleted.');
    }
    header('Location: /admin/categories.php');
    exit;
}

$categories = db()->query(
    "SELECT c.*, (SELECT COUNT(*) FROM videos v WHERE v.category_id = c.id) AS video_count
     FROM categories c ORDER BY c.name"
)->fetchAll();

$activeNav = 'categories';
$pageTitle = 'Categories & Tags';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<div class="card">
  <div class="card-head"><div><p class="card-title">Categories</p><p class="card-sub">Organize videos into browsable sections.</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <tr><th>Name</th><th>Slug</th><th>Videos</th><th>Actions</th></tr>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td class="mono"><?= htmlspecialchars($c['slug']) ?></td>
          <td><?= $c['video_count'] ?></td>
          <td>
            <!-- 3. Edit category button link -->
            <a href="/admin/categories.php?edit_id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" style="margin-right:4px;">Edit</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category? Videos in it will become uncategorized.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<!-- 4. Dynamic category edit form when edit_id is active -->
<?php if ($editCategory): ?>
  <div class="card" id="editCard">
    <div class="card-head"><div><p class="card-title">Edit category: <?= htmlspecialchars($editCategory['name']) ?></p></div></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
      <div class="row">
        <div class="field"><label>Name</label><input type="text" name="name" required value="<?= htmlspecialchars($editCategory['name']) ?>"></div>
        <div class="field"><label>Slug / URL</label><input type="text" name="slug" class="mono" value="<?= htmlspecialchars($editCategory['slug']) ?>"></div>
      </div>
      <div class="actions-row">
        <button type="submit" class="btn btn-primary">Save changes</button>
        <a href="/admin/categories.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-head"><div><p class="card-title">Add category</p></div></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="row">
        <div class="field"><label>Name</label><input type="text" name="name" required placeholder="e.g. Category D"></div>
        <div class="field" style="display:flex;align-items:flex-end;">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">+ Add category</button>
        </div>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>