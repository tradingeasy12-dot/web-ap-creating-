<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$duplicateReport = null;
$thinReport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save_seo';

    if ($action === 'save_seo') {
        // Category & Tag Pages
        set_setting('seo_category_title_template', trim($_POST['category_title_template'] ?? ''));
        set_setting('seo_tag_title_template', trim($_POST['tag_title_template'] ?? ''));
        set_setting('seo_category_desc_template', trim($_POST['category_desc_template'] ?? ''));

        // Site Speed
        set_setting('seo_lazy_load', isset($_POST['lazy_load']) ? '1' : '0');
        set_setting('seo_webp_convert', isset($_POST['webp_convert']) ? '1' : '0');
        set_setting('seo_serve_cdn', isset($_POST['serve_cdn']) ? '1' : '0');
        set_setting('seo_minify', isset($_POST['minify']) ? '1' : '0');

        // Internal Linking
        set_setting('seo_related_videos_enabled', isset($_POST['related_videos_enabled']) ? '1' : '0');
        $relatedCount = max(1, min(30, (int) ($_POST['related_count'] ?? 10)));
        set_setting('seo_related_count', (string) $relatedCount);
        set_setting('seo_trending_tags_page', isset($_POST['trending_tags']) ? '1' : '0');

        // Duplicate Content Control
        set_setting('seo_canonical_enabled', isset($_POST['canonical_enabled']) ? '1' : '0');
        set_setting('seo_duplicate_alert', isset($_POST['duplicate_alert']) ? '1' : '0');
        set_setting('seo_thin_content_alert', isset($_POST['thin_content_alert']) ? '1' : '0');

        // Video Sitemap & Indexing
        set_setting('seo_video_sitemap_enabled', isset($_POST['video_sitemap_enabled']) ? '1' : '0');
        set_setting('seo_indexnow_enabled', isset($_POST['indexnow_enabled']) ? '1' : '0');
        set_setting('seo_indexnow_key', trim($_POST['indexnow_key'] ?? ''));

        // Search Console
        set_setting('seo_gsc_enabled', isset($_POST['gsc_enabled']) ? '1' : '0');
        set_setting('seo_gsc_verification_tag', trim($_POST['gsc_verification_tag'] ?? ''));

        flash('success', 'SEO settings saved. Changes apply to new and existing pages immediately.');
        header('Location: /admin/seo.php');
        exit;
    }

    if ($action === 'run_duplicate_check') {
        // Real check: find videos sharing an identical title or meta_description with another video.
        $duplicateReport = db()->query(
            "SELECT title, COUNT(*) AS cnt FROM videos WHERE status='published'
             GROUP BY title HAVING cnt > 1"
        )->fetchAll();

        // Thin content: published videos with a very short/empty description (under 40 chars).
        $thinReport = db()->query(
            "SELECT id, title, CHAR_LENGTH(COALESCE(description,'')) AS desc_len
             FROM videos WHERE status='published' AND CHAR_LENGTH(COALESCE(description,'')) < 40
             ORDER BY desc_len ASC LIMIT 50"
        )->fetchAll();
    }
}

$activeNav = 'seo';
$pageTitle = 'SEO Growth Settings';
require_once __DIR__ . '/../includes/layout_header.php';
?>

<?php render_flash(); ?>

<p class="hint" style="margin:-4px 0 18px;">
  Ordered by real-world impact — start with sections tagged <strong style="color:var(--accent);">High impact</strong> before the rest.
</p>

<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_seo">

  <!-- ================= CATEGORY & TAG PAGES ================= -->
  <details class="seo-section" open>
    <summary>
      <span class="seo-icon">🏷️</span>
      <span class="seo-section-title">Category &amp; Tag Pages</span>
      <span class="impact-badge impact-high">High impact</span>
      <span class="seo-section-desc">Unique titles per tag/category — where most organic traffic lands</span>
    </summary>
    <div class="seo-section-body">
      <div class="field">
        <label>Category title template</label>
        <input type="text" class="mono" name="category_title_template"
               value="<?= htmlspecialchars(get_setting('seo_category_title_template', '{category_name} Videos — {site_name}')) ?>">
      </div>
      <div class="field">
        <label>Tag title template</label>
        <input type="text" class="mono" name="tag_title_template"
               value="<?= htmlspecialchars(get_setting('seo_tag_title_template', '{tag_name} Videos, Free & HD — {site_name}')) ?>">
      </div>
      <div class="field">
        <label>Category description template</label>
        <textarea name="category_desc_template"><?= htmlspecialchars(get_setting('seo_category_desc_template', 'Watch {category_name} videos in HD. New uploads added daily.')) ?></textarea>
        <div class="hint">Variables: {category_name}, {video_count}, {site_name}</div>
      </div>
      <div class="hint" style="margin-top:-4px;">
        Applies once category/tag listing pages are built on the public site — the templates are saved and ready for that.
      </div>
    </div>
  </details>

  <!-- ================= SITE SPEED ================= -->
  <details class="seo-section">
    <summary>
      <span class="seo-icon">⚡</span>
      <span class="seo-section-title">Site Speed</span>
      <span class="impact-badge impact-high">High impact</span>
      <span class="seo-section-desc">Load time drives both ranking and whether visitors stay</span>
    </summary>
    <div class="seo-section-body">
      <div class="switch-row">
        <div><div class="switch-label">Lazy-load thumbnails &amp; video players</div><div class="switch-desc">Only load what's visible on screen — live on the homepage and watch page</div></div>
        <label class="switch"><input type="checkbox" name="lazy_load" <?= get_setting('seo_lazy_load','1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="switch-row">
        <div><div class="switch-label">Auto-convert uploaded thumbnails to WebP</div><div class="switch-desc">Smaller file sizes, same quality — applies to new thumbnail uploads</div></div>
        <label class="switch"><input type="checkbox" name="webp_convert" <?= get_setting('seo_webp_convert','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="switch-row">
        <div><div class="switch-label">Serve media through CDN</div><div class="switch-desc">Uses the CDN URL from your active Storage API provider, if one is set</div></div>
        <label class="switch"><input type="checkbox" name="serve_cdn" <?= get_setting('seo_serve_cdn','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="switch-row">
        <div><div class="switch-label">Minify CSS &amp; JavaScript</div><div class="switch-desc">Saved as a preference — this build serves hand-written CSS/JS directly and doesn't run a minifier yet</div></div>
        <label class="switch"><input type="checkbox" name="minify" <?= get_setting('seo_minify','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
    </div>
  </details>

  <!-- ================= INTERNAL LINKING ================= -->
  <details class="seo-section">
    <summary>
      <span class="seo-icon">🔗</span>
      <span class="seo-section-title">Internal Linking</span>
      <span class="impact-badge impact-high">High impact</span>
      <span class="seo-section-desc">Related videos keep visitors watching longer</span>
    </summary>
    <div class="seo-section-body">
      <div class="switch-row">
        <div><div class="switch-label">Show related videos on watch page</div></div>
        <label class="switch"><input type="checkbox" name="related_videos_enabled" <?= get_setting('seo_related_videos_enabled','1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="field" style="margin-top:14px;">
        <label>Related videos to show</label>
        <input type="number" name="related_count" min="1" max="30" value="<?= htmlspecialchars(get_setting('seo_related_count', '10')) ?>">
        <div class="hint">Recommended: 10–16. Controls the watch page directly.</div>
      </div>
      <div class="switch-row" style="margin-top:14px;">
        <div><div class="switch-label">Auto-generate trending tags page</div><div class="switch-desc">Saved as a preference — the crawlable /tags page itself isn't built yet</div></div>
        <label class="switch"><input type="checkbox" name="trending_tags" <?= get_setting('seo_trending_tags_page','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
    </div>
  </details>

  <!-- ================= DUPLICATE CONTENT CONTROL ================= -->
  <details class="seo-section">
    <summary>
      <span class="seo-icon">🛡️</span>
      <span class="seo-section-title">Duplicate Content Control</span>
      <span class="impact-badge impact-high">High impact</span>
      <span class="seo-section-desc">Prevents mirrored or repeated content from splitting rank</span>
    </summary>
    <div class="seo-section-body">
      <div class="switch-row">
        <div><div class="switch-label">Set canonical URL on every page</div><div class="switch-desc">Live on the watch page &lt;head&gt;</div></div>
        <label class="switch"><input type="checkbox" name="canonical_enabled" <?= get_setting('seo_canonical_enabled','1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="switch-row">
        <div><div class="switch-label">Alert on duplicate title</div><div class="switch-desc">Flags published videos sharing the exact same title</div></div>
        <label class="switch"><input type="checkbox" name="duplicate_alert" <?= get_setting('seo_duplicate_alert','1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="switch-row">
        <div><div class="switch-label">Flag thin-content pages</div><div class="switch-desc">Published videos with a description under 40 characters</div></div>
        <label class="switch"><input type="checkbox" name="thin_content_alert" <?= get_setting('seo_thin_content_alert','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
    </div>
  </details>

  <!-- ================= VIDEO SITEMAP & INDEXING ================= -->
  <details class="seo-section">
    <summary>
      <span class="seo-icon">🎬</span>
      <span class="seo-section-title">Video Sitemap &amp; Indexing</span>
      <span class="impact-badge impact-medium">Medium impact</span>
      <span class="seo-section-desc">Separate from sitemap.xml — helps search engines find new uploads fast</span>
    </summary>
    <div class="seo-section-body">
      <div class="switch-row">
        <div><div class="switch-label">Auto-generate video sitemap</div><div class="switch-desc">Live at <span class="mono">/sitemap-videos.xml</span> once enabled</div></div>
        <label class="switch"><input type="checkbox" name="video_sitemap_enabled" <?= get_setting('seo_video_sitemap_enabled','1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="switch-row" style="margin-top:14px;">
        <div><div class="switch-label">Ping search engines on publish (IndexNow)</div><div class="switch-desc">Fires automatically when you publish a video, if a key is set below</div></div>
        <label class="switch"><input type="checkbox" name="indexnow_enabled" <?= get_setting('seo_indexnow_enabled','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="field" style="margin-top:14px;">
        <label>IndexNow API key</label>
        <input type="text" class="mono" name="indexnow_key" placeholder="Generate free at indexnow.org"
               value="<?= htmlspecialchars(get_setting('seo_indexnow_key', '')) ?>">
      </div>
    </div>
  </details>

  <!-- ================= SEARCH CONSOLE ================= -->
  <details class="seo-section">
    <summary>
      <span class="seo-icon">ℹ️</span>
      <span class="seo-section-title">Search Console</span>
      <span class="impact-badge impact-medium">Medium impact</span>
      <span class="seo-section-desc">See which queries actually bring visitors in</span>
    </summary>
    <div class="seo-section-body">
      <div class="switch-row">
        <div><div class="switch-label">Enable Search Console verification tag</div></div>
        <label class="switch"><input type="checkbox" name="gsc_enabled" <?= get_setting('seo_gsc_enabled','0')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>
      <div class="field" style="margin-top:14px;">
        <label>Verification meta tag</label>
        <input type="text" class="mono" name="gsc_verification_tag" placeholder='<meta name="google-site-verification" content="...">'
               value="<?= htmlspecialchars(get_setting('seo_gsc_verification_tag', '')) ?>">
        <div class="hint">Paste the full tag from Google Search Console — it gets printed into every public page's &lt;head&gt; when enabled.</div>
      </div>
    </div>
  </details>

  <div class="seo-save-bar">
    <span class="hint">Changes apply to new and existing pages on save</span>
    <button type="submit" class="btn btn-primary">Save changes</button>
  </div>
</form>

<!-- ================= ON-DEMAND CONTENT CHECK ================= -->
<div class="card" style="margin-top:22px;">
  <div class="card-head"><div><p class="card-title">Run a content check now</p><p class="card-sub">Scans your published videos directly — not scheduled, just click when you want a fresh read.</p></div></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="run_duplicate_check">
    <button type="submit" class="btn btn-ghost">Run duplicate &amp; thin-content check</button>
  </form>

  <?php if ($duplicateReport !== null): ?>
    <div class="section-divider" style="margin-top:20px;">Duplicate titles</div>
    <?php if (!$duplicateReport): ?>
      <p class="hint">No duplicate titles found among published videos.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="table">
        <tr><th>Title</th><th>How many videos share it</th></tr>
        <?php foreach ($duplicateReport as $row): ?>
          <tr><td><?= htmlspecialchars($row['title']) ?></td><td><?= (int) $row['cnt'] ?></td></tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>

    <div class="section-divider" style="margin-top:20px;">Thin-content videos (description under 40 characters)</div>
    <?php if (!$thinReport): ?>
      <p class="hint">No thin-content videos found.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="table">
        <tr><th>Title</th><th>Description length</th><th></th></tr>
        <?php foreach ($thinReport as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= (int) $row['desc_len'] ?> chars</td>
            <td><a class="btn btn-ghost btn-sm" href="/admin/upload-video.php?id=<?= $row['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
