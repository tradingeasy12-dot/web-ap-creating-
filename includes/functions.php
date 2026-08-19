<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
}

/**
 * Outputs a display/banner ad for the given slot (e.g. 'sidebar_banner', 'footer_banner'),
 * if that slot is enabled and has an ad code resolved (from the ad library or a
 * one-off custom code). Prints nothing at all if the slot is off or has no code set —
 * so an unused slot never leaves behind an empty wrapper div.
 */
function render_ad_slot(string $slotKey): void
{
    static $cache = [];

    if (!array_key_exists($slotKey, $cache)) {
        $stmt = db()->prepare(
            "SELECT s.custom_ad_code, a.ad_code
             FROM ad_slots s LEFT JOIN ad_library a ON a.id = s.ad_library_id AND a.status = 'active'
             WHERE s.slot_key = ? AND s.is_enabled = 1"
        );
        $stmt->execute([$slotKey]);
        $row = $stmt->fetch();
        $cache[$slotKey] = $row ? trim($row['custom_ad_code'] ?: ($row['ad_code'] ?? '')) : '';
    }

    if ($cache[$slotKey] !== '') {
        echo '<div class="ad-slot ad-slot-' . htmlspecialchars($slotKey) . '">'
           . '<div class="ad-slot-label">Advertisement</div>'
           . $cache[$slotKey]
           . '</div>';
    }
}

/** Renders a grid of video cards — shared by the homepage and anywhere else that lists videos. */
function render_video_grid(array $videos): void
{
?>
  <div class="video-grid">
    <?php foreach ($videos as $v): ?>
      <a class="vcard" href="/watch.php?slug=<?= urlencode($v['slug']) ?>">
        <div class="vthumb">
          <?php if ($v['thumbnail_path']): ?><img src="<?= htmlspecialchars(asset_url($v['thumbnail_path'])) ?>" alt=""<?= lazy_attr() ?>><?php endif; ?>
          <?php if ($v['duration_seconds']): ?><span class="dur"><?= gmdate($v['duration_seconds'] >= 3600 ? 'H:i:s' : 'i:s', $v['duration_seconds']) ?></span><?php endif; ?>
        </div>
        <div class="vinfo">
          <div class="vtitle"><?= htmlspecialchars($v['title']) ?></div>
          <div class="vmeta"><?= number_format($v['views_count']) ?> views</div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(400);
        die('Invalid form submission (CSRF check failed). Go back and try again.');
    }
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text ?: ('item-' . substr(md5((string) microtime(true)), 0, 8));
}

/** Guarantees a unique slug in $table by appending -2, -3, ... if needed. Pass $excludeId when editing, so a row doesn't collide with its own slug. */
function unique_slug(string $table, string $base, ?int $excludeId = null): string
{
    $slug = slugify($base);
    $original = $slug;
    $i = 2;
    $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = ?" . ($excludeId ? " AND id != ?" : "");
    $stmt = db()->prepare($sql);
    while (true) {
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $original . '-' . $i;
        $i++;
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Reads one row from the site_settings key-value table. */
function get_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

/** Writes/updates one row in the site_settings key-value table. */
function set_setting(string $key, string $value): void
{
    db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?'
    )->execute([$key, $value, $value]);
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function render_flash(): void
{
    $f = get_flash();
    if ($f) {
        $class = $f['type'] === 'error' ? 'alert-error' : 'alert-success';
        echo '<div class="alert ' . $class . '">' . htmlspecialchars($f['message']) . '</div>';
    }
}

/** Whether this server can run ffmpeg (shell_exec enabled + binary present). Some shared hosting blocks this. */
function ffmpeg_available(): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }
    if (!function_exists('shell_exec')) {
        return $available = false;
    }
    $path = @shell_exec('which ffmpeg 2>/dev/null');
    return $available = !empty(trim((string) $path));
}

/** Video duration in seconds via ffprobe, or null if it can't be determined. */
function video_duration_seconds(string $absoluteVideoPath): ?float
{
    if (!ffmpeg_available()) {
        return null;
    }
    $safePath = escapeshellarg($absoluteVideoPath);
    $out = @shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 {$safePath} 2>/dev/null");
    $out = trim((string) $out);
    return $out !== '' ? (float) $out : null;
}

/**
 * Extracts a few frames from the video as thumbnail candidates.
 * Returns an array of paths relative to the site root (e.g. 'uploads/thumbnails/thumb_xxx.jpg'),
 * or an empty array if ffmpeg isn't available on this server.
 */
function generate_thumbnail_candidates(string $absoluteVideoPath, int $count = 3): array
{
    if (!ffmpeg_available()) {
        return [];
    }

    $duration = video_duration_seconds($absoluteVideoPath) ?? 20.0; // fallback guess if ffprobe fails
    $candidates = [];
    $thumbDir = UPLOAD_DIR . 'thumbnails/';

    for ($i = 1; $i <= $count; $i++) {
        // Spread picks across the video, avoiding the very first/last second (often black frames).
        $fraction = $i / ($count + 1);
        $seconds = max(1, round($duration * $fraction));

        $filename = uniqid('thumb_', true) . '.jpg';
        $destination = $thumbDir . $filename;

        $safeInput  = escapeshellarg($absoluteVideoPath);
        $safeOutput = escapeshellarg($destination);
        @shell_exec("ffmpeg -y -ss {$seconds} -i {$safeInput} -frames:v 1 -q:v 3 {$safeOutput} 2>/dev/null");

        if (file_exists($destination)) {
            $candidates[] = 'uploads/thumbnails/' . $filename;
        }
    }

    return $candidates;
}

/** Reads one row from the site_settings key-value table. Same as get_setting() — kept for public-page code that expects this name. */
function site_setting(string $key, string $default = ''): string
{
    return get_setting($key, $default);
}

/**
 * Builds the URL for a stored file (thumbnail/video), respecting the
 * "Serve media through CDN" SEO setting. Falls back to a local path
 * if CDN serving is off or no active storage provider has a CDN URL.
 */
function asset_url(?string $relativePath): string
{
    if (!$relativePath) {
        return '';
    }
    static $cdnBase = null;
    if ($cdnBase === null) {
        $cdnBase = '';
        if (get_setting('seo_serve_cdn', '0') === '1') {
            $stmt = db()->query("SELECT cdn_url FROM storage_providers WHERE is_active = 1 AND cdn_url IS NOT NULL AND cdn_url != '' LIMIT 1");
            $row = $stmt->fetch();
            if ($row && $row['cdn_url']) {
                $cdnBase = rtrim($row['cdn_url'], '/');
            }
        }
    }
    return $cdnBase !== '' ? $cdnBase . '/' . $relativePath : '/' . $relativePath;
}

/** Returns ' loading="lazy"' when the SEO lazy-load setting is on, else empty string. */
function lazy_attr(): string
{
    return get_setting('seo_lazy_load', '1') === '1' ? ' loading="lazy"' : '';
}

/** Pings IndexNow (if enabled + key set) so search engines pick up a new/updated URL fast. Fails silently — never blocks a save. */
function ping_indexnow(string $absoluteUrl): void
{
    if (get_setting('seo_indexnow_enabled', '0') !== '1') {
        return;
    }
    $key = trim(get_setting('seo_indexnow_key', ''));
    if ($key === '') {
        return;
    }
    $host = parse_url($absoluteUrl, PHP_URL_HOST);
    $payload = json_encode(['host' => $host, 'key' => $key, 'urlList' => [$absoluteUrl]]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);
    @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
}

/**
 * If the "Auto-convert uploaded thumbnails to WebP" SEO setting is on,
 * converts a just-saved JPG/PNG thumbnail to WebP and deletes the original.
 * Returns the relative path to use (WebP if converted, otherwise unchanged).
 * Safe to call unconditionally — does nothing if the setting is off, the
 * file isn't a JPG/PNG, or this server's GD build lacks WebP support.
 */
function maybe_convert_thumbnail_to_webp(?string $relativePath): ?string
{
    if (!$relativePath || get_setting('seo_webp_convert', '0') !== '1') {
        return $relativePath;
    }

    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return $relativePath; // already webp, or not an image type we convert
    }
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromjpeg')) {
        return $relativePath; // GD without WebP support on this server — skip silently
    }

    $absPath = __DIR__ . '/../' . $relativePath;
    if (!file_exists($absPath)) {
        return $relativePath;
    }

    $image = $ext === 'png' ? @imagecreatefrompng($absPath) : @imagecreatefromjpeg($absPath);
    if (!$image) {
        return $relativePath;
    }

    // PNG can have transparency — preserve it instead of a black background.
    if ($ext === 'png') {
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }

    $webpRelative = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $relativePath);
    $webpAbsolute = __DIR__ . '/../' . $webpRelative;

    $ok = imagewebp($image, $webpAbsolute, 82);
    imagedestroy($image);

    if ($ok && file_exists($webpAbsolute)) {
        @unlink($absPath);
        return $webpRelative;
    }

    return $relativePath; // conversion failed — keep the original rather than losing the thumbnail
}

/** Strips comments and collapses whitespace in CSS — a real (if simple) minifier, not just a saved preference. */
function minify_css(string $css): string
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css);        // remove /* comments */
    $css = preg_replace('/\s+/', ' ', $css);              // collapse whitespace
    $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css); // trim around punctuation
    $css = preg_replace('/;}/', '}', $css);               // drop trailing semicolon before }
    return trim($css);
}

/**
 * Returns the URL to use for a stylesheet, respecting the "Minify CSS & JavaScript"
 * SEO setting. When on, minifies the source file into a cached .min.css sitting next
 * to it (regenerated only when the source file's mtime changes) and returns that
 * instead. Falls back to the original file if minifying fails for any reason.
 */
function css_url(string $relativePath): string
{
    if (get_setting('seo_minify', '0') !== '1') {
        return $relativePath;
    }

    $sourceAbs = __DIR__ . '/../' . $relativePath;
    if (!file_exists($sourceAbs)) {
        return $relativePath;
    }

    $minRelative = preg_replace('/\.css$/', '.min.css', $relativePath);
    $minAbs      = __DIR__ . '/../' . $minRelative;

    $needsRebuild = !file_exists($minAbs) || filemtime($minAbs) < filemtime($sourceAbs);
    if ($needsRebuild) {
        $minified = minify_css(file_get_contents($sourceAbs));
        if (@file_put_contents($minAbs, $minified) === false) {
            return $relativePath; // couldn't write the cached file — serve the original, don't break the page
        }
    }

    return $minRelative;
}

/**
 * Resolves visitor country code using reverse proxy headers or a fast API fallback.
 */
function get_visitor_country(): ?string
{
    // 1) Check common reverse proxy headers first (instant, free, extremely reliable)
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        return strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
    }
    if (!empty($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'])) {
        return strtoupper($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']);
    }
    if (!empty($_SERVER['HTTP_X_COUNTRY_CODE'])) {
        return strtoupper($_SERVER['HTTP_X_COUNTRY_CODE']);
    }

    // 2) Fallback to a fast public API with low timeout and caching (or just silent fallback on failure)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
        // Simple cache in session to avoid redundant lookups for the same session
        if (!empty($_SESSION['visitor_country'])) {
            return $_SESSION['visitor_country'];
        }

        // We can try a free GeoIP API. Let's use freeipapi.com.
        // Let's set a 1 second timeout so we never block the visitor if the API is slow.
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 1.0,
                'header'  => "User-Agent: VideoPlatformGeoIP/1.0\r\n",
            ]
        ]);
        $response = @file_get_contents("https://freeipapi.com/api/json/" . urlencode($ip), false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['countryCode']) && strlen($data['countryCode']) === 2) {
                $_SESSION['visitor_country'] = strtoupper($data['countryCode']);
                return $_SESSION['visitor_country'];
            }
        }
    }

    return 'US'; // Default fallback country
}
