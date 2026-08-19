<?php
$footerPages = db()->query('SELECT * FROM footer_pages WHERE is_visible = 1 ORDER BY sort_order')->fetchAll();

// Resolve the popunder URL, if the slot is enabled and an ad is actually configured for it.
$popunderUrl = null;
$popStmt = db()->prepare(
    "SELECT s.custom_ad_code, a.ad_code
     FROM ad_slots s LEFT JOIN ad_library a ON a.id = s.ad_library_id AND a.status = 'active'
     WHERE s.slot_key = 'popunder' AND s.is_enabled = 1"
);
$popStmt->execute();
if ($pop = $popStmt->fetch()) {
    $popunderUrl = trim($pop['custom_ad_code'] ?: ($pop['ad_code'] ?? '')) ?: null;
}
?>
<?php render_ad_slot('footer_banner'); ?>
<footer>
  <div class="footer-inner">
    <div class="footer-links">
      <?php foreach ($footerPages as $fp): ?>
        <span onclick="openFooterPopup('<?= htmlspecialchars(addslashes($fp['button_text'])) ?>','<?= htmlspecialchars(addslashes($fp['popup_content'])) ?>')"><?= htmlspecialchars($fp['button_text']) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="footer-note">All performers depicted were verified as 18 years of age or older at the time of production. Record-keeping information available upon request.</div>
  </div>
</footer>

<div class="pv-overlay" id="footerPvOverlay" onclick="if(event.target===this)closeFooterPopup()">
  <div class="pv-modal">
    <div class="pv-close" onclick="closeFooterPopup()">✕</div>
    <h3 id="footerPvTitle"></h3>
    <p id="footerPvBody"></p>
  </div>
</div>

<script>
function openFooterPopup(title, body){
  document.getElementById('footerPvTitle').textContent = title;
  document.getElementById('footerPvBody').textContent = body;
  document.getElementById('footerPvOverlay').classList.add('open');
}
function closeFooterPopup(){
  document.getElementById('footerPvOverlay').classList.remove('open');
}
</script>

<?php if ($popunderUrl): ?>
<script>
// Popunder / Direct Link ad: opens once per visitor per day, on their first click anywhere on the site.
(function(){
  var POPUNDER_URL = <?= json_encode($popunderUrl) ?>;
  var STORAGE_KEY = 'popunder_last_shown';
  var today = new Date().toISOString().slice(0, 10);

  function alreadyShownToday(){
    try { return localStorage.getItem(STORAGE_KEY) === today; } catch(e) { return false; }
  }
  function markShownToday(){
    try { localStorage.setItem(STORAGE_KEY, today); } catch(e) {}
  }

  if (alreadyShownToday()) return;

  document.addEventListener('click', function handler(){
    document.removeEventListener('click', handler);
    markShownToday();
    window.open(POPUNDER_URL, '_blank');
  }, { once: true });
})();
</script>
<?php endif; ?>
</body>
</html>
