<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_ad') {
        $name = trim($_POST['name'] ?? '');
        $allowedTypes = ['video_vast', 'display_script', 'direct_link'];
        $type = in_array($_POST['type'] ?? '', $allowedTypes, true) ? $_POST['type'] : 'video_vast';
        $code = trim($_POST['ad_code'] ?? '');
        if ($name !== '' && $code !== '') {
            db()->prepare('INSERT INTO ad_library (name, type, ad_code, status) VALUES (?, ?, ?, ?)')
                ->execute([$name, $type, $code, 'active']);
            flash('success', 'Ad added to library.');
        }
    } elseif ($action === 'delete_ad') {
        db()->prepare('DELETE FROM ad_library WHERE id = ?')->execute([(int) $_POST['ad_id']]);
        flash('success', 'Ad removed from library.');
    } elseif ($action === 'toggle_ad_status') {
        $stmt = db()->prepare('SELECT status FROM ad_library WHERE id = ?');
        $stmt->execute([(int) $_POST['ad_id']]);
        $current = $stmt->fetchColumn();
        $new = $current === 'active' ? 'paused' : 'active';
        db()->prepare('UPDATE ad_library SET status = ? WHERE id = ?')->execute([$new, (int) $_POST['ad_id']]);
    } elseif ($action === 'save_slots') {
        $slots = $_POST['slots'] ?? [];
        foreach ($slots as $slotKey => $data) {
            $enabled       = isset($data['enabled']) ? 1 : 0;
            $adLibraryId   = $data['ad_library_id'] ?: null;
            $customCode    = trim($data['custom_ad_code'] ?? '');
            $skip          = $data['skip_after_seconds'] !== '' ? (int) $data['skip_after_seconds'] : null;
            $frequency     = $data['frequency'] ?? null;
            $triggerAt     = trim($data['trigger_at'] ?? '') ?: null;
            $placement     = $data['placement'] ?? null;

            db()->prepare(
                'UPDATE ad_slots SET is_enabled=?, ad_library_id=?, custom_ad_code=?, skip_after_seconds=?,
                                      frequency=?, trigger_at=?, placement=? WHERE slot_key=?'
            )->execute([$enabled, $adLibraryId, $customCode ?: null, $skip, $frequency, $triggerAt, $placement, $slotKey]);
        }
        flash('success', 'Ad settings saved.');
    }
    header('Location: /admin/ads.php');
    exit;
}

$adLibrary = db()->query('SELECT * FROM ad_library ORDER BY created_at DESC')->fetchAll();
$slots     = db()->query('SELECT * FROM ad_slots')->fetchAll();
$slotsByKey = [];
foreach ($slots as $s) { $slotsByKey[$s['slot_key']] = $s; }

$slotDefs = [
    'preroll'          => ['Pre-roll', 'plays before the video starts', 'video'],
    'midroll'          => ['Mid-roll', 'plays partway through longer videos', 'video'],
    'postroll'         => ['Post-roll', 'plays after the video ends', 'video'],
    'homepage_banner'  => ['Homepage banner', 'static/display ad, not tied to playback', 'display'],
    'sidebar_banner'   => ['Sidebar banner', 'shown next to the video grid on wider screens', 'display'],
    'content_middle_banner' => ['Middle bar', 'shown between rows of videos on the homepage', 'display'],
    'footer_banner'    => ['Footer bar', 'shown just above the site footer, on every page', 'display'],
    'popunder'         => ['Popunder / Direct Link', 'opens once per visitor session, on their first click anywhere on the site', 'popunder'],
];

$activeNav = 'ads';
$pageTitle = 'Ads Settings';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<div class="section-divider">Ad library</div>
<div class="card">
  <div class="table-wrap">
    <table class="table">
      <tr><th>Name</th><th>Type</th><th>Status</th><th></th></tr>
      <?php if (!$adLibrary): ?>
        <tr><td colspan="4" style="color:var(--text-faint);">No ads saved yet — add one below.</td></tr>
      <?php endif; ?>
      <?php foreach ($adLibrary as $ad): ?>
        <tr>
          <td><?= htmlspecialchars($ad['name']) ?></td>
          <td>
            <?php
              $typeLabels = ['video_vast' => 'Video (VAST)', 'display_script' => 'Display (script)', 'direct_link' => 'Direct Link / Popunder'];
              echo htmlspecialchars($typeLabels[$ad['type']] ?? $ad['type']);
            ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_ad_status">
              <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">
                <span class="status-dot <?= $ad['status'] === 'active' ? 'status-ok' : 'status-warn' ?>"></span><?= ucfirst($ad['status']) ?>
              </button>
            </form>
          </td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this ad from the library?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_ad">
              <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><p class="card-title">Add new ad</p></div></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_ad">
    <div class="row">
      <div class="field"><label>Name</label><input type="text" name="name" required placeholder="e.g. NetworkD — Preroll"></div>
      <div class="field"><label>Type</label>
        <select name="type">
          <option value="video_vast">Video (VAST/VPAID)</option>
          <option value="display_script">Display / banner (script or iframe)</option>
          <option value="direct_link">Direct Link / Popunder (plain URL)</option>
        </select>
      </div>
    </div>
    <div class="field"><label>Ad tag URL or script</label><textarea name="ad_code" class="mono" required></textarea></div>
    <div class="actions-row"><button type="submit" class="btn btn-primary">Save to library</button></div>
  </form>
</div>

<div class="section-divider">Ad slots</div>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_slots">

  <?php
    $slotToAdType = [
        'preroll' => 'video_vast', 'midroll' => 'video_vast', 'postroll' => 'video_vast',
        'homepage_banner' => 'display_script', 'sidebar_banner' => 'display_script',
        'content_middle_banner' => 'display_script', 'footer_banner' => 'display_script',
        'popunder' => 'direct_link',
    ];
  ?>
  <?php foreach ($slotDefs as $key => $def): $slot = $slotsByKey[$key] ?? []; $wantType = $slotToAdType[$key] ?? null; ?>
    <div class="card">
      <div class="card-head" style="align-items:center;">
        <div><p class="card-title"><?= $def[0] ?> <span class="hint" style="display:inline;">— <?= $def[1] ?></span></p></div>
        <label class="switch"><input type="checkbox" name="slots[<?= $key ?>][enabled]" <?= ($slot['is_enabled'] ?? 0) ? 'checked' : '' ?>><span class="slider"></span></label>
      </div>
      <div class="field">
        <label>Which ad plays here</label>
        <select name="slots[<?= $key ?>][ad_library_id]">
          <option value="">— Custom code below —</option>
          <?php foreach ($adLibrary as $ad): if ($wantType && $ad['type'] !== $wantType) continue; ?>
            <option value="<?= $ad['id'] ?>" <?= ($slot['ad_library_id'] ?? null) == $ad['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ad['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($key === 'popunder'): ?>
          <div class="hint">Only ads saved as type "Direct Link / Popunder" show up here.</div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label><?= $key === 'popunder' ? 'Or paste a direct link URL for this slot only' : 'Or paste one-off code for this slot only' ?></label>
        <textarea name="slots[<?= $key ?>][custom_ad_code]" class="mono" placeholder="<?= $key === 'popunder' ? 'https://your-adsterra-direct-link.example/xxxxx' : '' ?>"><?= htmlspecialchars($slot['custom_ad_code'] ?? '') ?></textarea>
      </div>

      <?php if ($def[2] === 'video' && $key === 'preroll'): ?>
        <div class="row">
          <div class="field"><label>Skip button appears after (seconds)</label>
            <input type="number" name="slots[<?= $key ?>][skip_after_seconds]" value="<?= htmlspecialchars($slot['skip_after_seconds'] ?? '') ?>"></div>
          <div class="field"><label>Frequency</label>
            <select name="slots[<?= $key ?>][frequency]">
              <?php foreach (['every_video'=>'Every video','every_2nd'=>'Every 2nd video','every_3rd'=>'Every 3rd video'] as $val=>$label): ?>
                <option value="<?= $val ?>" <?= ($slot['frequency'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php elseif ($key === 'midroll'): ?>
        <div class="field"><label>Trigger at</label>
          <input type="text" name="slots[<?= $key ?>][trigger_at]" value="<?= htmlspecialchars($slot['trigger_at'] ?? '') ?>" placeholder="e.g. 50% or 3:00"></div>
      <?php elseif ($key === 'homepage_banner'): ?>
        <div class="field"><label>Placement on page</label>
          <select name="slots[<?= $key ?>][placement]">
            <?php foreach (['top'=>'Top banner','sidebar'=>'Sidebar','between_rows'=>'Between video rows'] as $val=>$label): ?>
              <option value="<?= $val ?>" <?= ($slot['placement'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="actions-row"><button type="submit" class="btn btn-primary">Save all ad settings</button></div>
</form>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
