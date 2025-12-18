<?php

declare(strict_types=1);

$shortCode = $_GET['code'] ?? '';

if (empty($shortCode)) {
    jsonResponse(['error' => 'Missing required parameter: code'], 400);
}

if (!preg_match('/^[a-zA-Z0-9]{6,10}$/', $shortCode)) {
    jsonResponse(['error' => 'Invalid short code format'], 400);
}

$db = getDb();
$redis = getRedis();

$stmt = $db->prepare('SELECT * FROM urls WHERE short_code = ?');
$stmt->execute([$shortCode]);
$url = $stmt->fetch();

if (!$url) {
    jsonResponse(['error' => 'URL not found'], 404);
}

$redisClicks = (int) $redis->get("clicks:{$shortCode}");
$totalClicks = $url['click_count'] + $redisClicks;

jsonResponse([
    'short_code' => $shortCode,
    'original_url' => $url['original_url'],
    'click_count' => $totalClicks,
    'created_at' => $url['created_at'],
    'expires_at' => $url['expires_at'],
]);
