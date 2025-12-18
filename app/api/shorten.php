<?php

declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$clientIp = getClientIp();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$rateLimiter = getRateLimiter();
$rateResult = $rateLimiter->isRateLimited($clientIp, 'create');
if ($rateResult['limited']) {
    header('Retry-After: ' . $rateResult['retry_after']);
    jsonResponse([
        'error' => $rateResult['message'],
        'retry_after' => $rateResult['retry_after'],
    ], 429);
}

$input = json_decode(file_get_contents('php://input'), true);

$botDetector = getBotDetector();
$botResult = $botDetector->validateSubmission($input ?? [], $userAgent);
if ($botResult['is_bot']) {
    jsonResponse([
        'error' => 'Request blocked.',
        'reason' => 'suspicious_activity',
    ], 403);
}

$originalUrl = $input['url'] ?? null;

if (!$originalUrl || !filter_var($originalUrl, FILTER_VALIDATE_URL)) {
    jsonResponse(['error' => 'Invalid URL provided'], 400);
}

$safetyChecker = getUrlSafetyChecker();
$safetyResult = $safetyChecker->checkUrl($originalUrl);
if (!$safetyResult['safe']) {
    jsonResponse([
        'error' => $safetyResult['message'],
        'reason' => $safetyResult['reason'],
    ], 400);
}

$rateLimiter->recordRequest($clientIp, 'create');

$normalizedUrl = normalizeUrl($originalUrl);

$db = getDb();
$redis = getRedis();

$urlHash = hash('sha256', $normalizedUrl);
$existing = $redis->get("urlhash:{$urlHash}");

if ($existing) {
    $shortCode = $existing;
    $isExisting = true;
} else {
    $stmt = $db->prepare('SELECT short_code FROM urls WHERE url_hash = ? AND (expires_at IS NULL OR expires_at > NOW())');
    $stmt->execute([$urlHash]);
    $row = $stmt->fetch();

    if ($row) {
        $shortCode = $row['short_code'];
        $isExisting = true;
        $redis->setex("urlhash:{$urlHash}", 86400, $shortCode);
    } else {
        $isExisting = false;
        $maxAttempts = 10;
        $shortCode = null;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = generateShortCode();
            $stmt = $db->prepare('SELECT id FROM urls WHERE short_code = ?');
            $stmt->execute([$candidate]);
            if (!$stmt->fetch()) {
                $shortCode = $candidate;
                break;
            }
        }

        if (!$shortCode) {
            jsonResponse(['error' => 'Failed to generate unique code'], 500);
        }

        $stmt = $db->prepare('INSERT INTO urls (short_code, original_url, url_hash) VALUES (?, ?, ?)');
        $stmt->execute([$shortCode, $originalUrl, $urlHash]);

        $redis->setex("url:{$shortCode}", 86400, $originalUrl);
        $redis->setex("urlhash:{$urlHash}", 86400, $shortCode);
    }
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');

jsonResponse([
    'success' => true,
    'short_url' => "{$baseUrl}/{$shortCode}",
    'short_code' => $shortCode,
    'existing' => $isExisting,
], $isExisting ? 200 : 201);
