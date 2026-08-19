<?php
/**
 * Daily traffic rollup.
 *
 * Reads yesterday's raw rows from `video_views` and writes summarized totals
 * into `traffic_daily` (per video + per country, plus a site-wide total row).
 * This is what makes the Dashboard and the "today's views" column in
 * All Videos fast — instead of counting millions of raw rows on every page
 * load, those pages just read this small pre-computed table.
 *
 * This file is meant to run once a day via cron — NOT to be opened in a browser.
 * See /cron/README.txt for the one-time cPanel setup step.
 */

// Guard: only allow this to run from the command line (cron), never from a browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line / cron.');
}

require_once __DIR__ . '/../includes/db.php';

$targetDate = date('Y-m-d', strtotime('yesterday'));

echo "[rollup-traffic] Rolling up traffic for {$targetDate}...\n";

try {
    db()->beginTransaction();

    // Clear any existing rollup for that date first, so re-running this script
    // manually (e.g. to backfill) doesn't create duplicate/doubled totals.
    db()->prepare('DELETE FROM traffic_daily WHERE stat_date = ?')->execute([$targetDate]);

    // 1) Per video + per country breakdown
    $stmt = db()->prepare(
        "INSERT INTO traffic_daily (stat_date, video_id, country_code, views)
         SELECT ?, video_id, country_code, COUNT(*)
         FROM video_views
         WHERE DATE(viewed_at) = ?
         GROUP BY video_id, country_code"
    );
    $stmt->execute([$targetDate, $targetDate]);
    $perVideoRows = $stmt->rowCount();

    // 2) Site-wide total for that day (video_id = NULL, country_code = NULL)
    $stmt = db()->prepare(
        "INSERT INTO traffic_daily (stat_date, video_id, country_code, views)
         SELECT ?, NULL, NULL, COUNT(*)
         FROM video_views
         WHERE DATE(viewed_at) = ?"
    );
    $stmt->execute([$targetDate, $targetDate]);

    db()->commit();

    echo "[rollup-traffic] Done. {$perVideoRows} video/country rows written for {$targetDate}.\n";
} catch (Throwable $e) {
    db()->rollBack();
    fwrite(STDERR, "[rollup-traffic] FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
