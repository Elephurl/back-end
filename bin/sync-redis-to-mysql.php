#!/usr/bin/env php
<?php
/**
 * Sync Redis click counts and analytics to MySQL
 * This simulates the EventBridge-triggered Lambda in production
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Redis to MySQL sync...\n";

$db = getDb();
$redis = getRedis();

// Get all click count keys
$clickKeys = $redis->keys('clicks:*');
$synced = 0;

foreach ($clickKeys as $key) {
    $shortCode = str_replace('clicks:', '', $key);
    $clicks = (int) $redis->get($key);

    if ($clicks > 0) {
        // Update MySQL click count
        $stmt = $db->prepare('UPDATE urls SET click_count = click_count + ? WHERE short_code = ?');
        $stmt->execute([$clicks, $shortCode]);

        // Reset Redis counter
        $redis->del($key);

        // Sync analytics data
        $analyticsKey = "analytics:{$shortCode}";
        $analytics = $redis->lRange($analyticsKey, 0, -1);

        if (!empty($analytics)) {
            // Get URL ID
            $stmt = $db->prepare('SELECT id FROM urls WHERE short_code = ?');
            $stmt->execute([$shortCode]);
            $url = $stmt->fetch();

            if ($url) {
                $insertStmt = $db->prepare(
                    'INSERT INTO url_analytics (url_id, clicked_at, ip_hash, user_agent, referer) VALUES (?, ?, ?, ?, ?)'
                );

                foreach ($analytics as $entry) {
                    $data = json_decode($entry, true);
                    $insertStmt->execute([
                        $url['id'],
                        date('Y-m-d H:i:s', $data['time']),
                        $data['ip_hash'],
                        $data['user_agent'],
                        $data['referer'],
                    ]);
                }
            }

            $redis->del($analyticsKey);
        }

        $synced++;
        echo "  Synced {$shortCode}: {$clicks} clicks\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Sync complete. Processed {$synced} URLs.\n";
