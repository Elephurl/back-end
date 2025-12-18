<?php

declare(strict_types=1);

$shortCode = trim($_SERVER['REQUEST_URI'], '/');
$redis = getRedis();

$cachedUrl = $redis->get("url:{$shortCode}");

if ($cachedUrl) {
    $redis->incr("clicks:{$shortCode}");
    $redis->rPush("analytics:{$shortCode}", json_encode([
        'time' => time(),
        'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'referer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
    ]));

    header("Location: {$cachedUrl}", true, 302);
    exit;
}

$db = getDb();
$stmt = $db->prepare('SELECT id, original_url FROM urls WHERE short_code = ? AND (expires_at IS NULL OR expires_at > NOW())');
$stmt->execute([$shortCode]);
$url = $stmt->fetch();

if (!$url) {
    http_response_code(404);
    echo '404 - Short URL not found or expired';
    exit;
}

$redis->setex("url:{$shortCode}", 86400, $url['original_url']);

$redis->incr("clicks:{$shortCode}");

header("Location: {$url['original_url']}", true, 302);
