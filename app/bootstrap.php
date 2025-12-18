<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'local' ? '1' : '0');

require_once __DIR__ . '/Security/RateLimiter.php';
require_once __DIR__ . '/Security/BotDetector.php';
require_once __DIR__ . '/Security/UrlSafetyChecker.php';

require_once __DIR__ . '/Services/UrlShortenerService.php';

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('MYSQL_HOST') ?: 'mysql',
            getenv('MYSQL_DATABASE') ?: 'elephurl'
        );
        $pdo = new PDO($dsn, getenv('MYSQL_USER') ?: 'elephurl', getenv('MYSQL_PASSWORD') ?: 'elephurl_secret', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function getRedis(): Redis
{
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int)(getenv('REDIS_PORT') ?: 6379)
        );
    }
    return $redis;
}

function getRateLimiter(): RateLimiter
{
    static $limiter = null;
    if ($limiter === null) {
        $limiter = new RateLimiter(getRedis());
    }
    return $limiter;
}

function getBotDetector(): BotDetector
{
    static $detector = null;
    if ($detector === null) {
        $detector = new BotDetector(getRedis());
    }
    return $detector;
}

function getUrlSafetyChecker(): UrlSafetyChecker
{
    static $checker = null;
    if ($checker === null) {
        $checker = new UrlSafetyChecker();
    }
    return $checker;
}

function getClientIp(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function generateShortCode(int $length = 7): string
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function normalizeUrl(string $url): string
{
    $parts = parse_url($url);

    $scheme = strtolower($parts['scheme'] ?? 'http');
    $host = strtolower($parts['host'] ?? '');

    $port = $parts['port'] ?? null;
    if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
        $port = null;
    }

    $path = $parts['path'] ?? '/';
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    if ($path === '') {
        $path = '/';
    }

    $query = '';
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $params);
        ksort($params);
        $query = '?' . http_build_query($params);
    }

    $normalized = $scheme . '://' . $host;
    if ($port) {
        $normalized .= ':' . $port;
    }
    $normalized .= $path . $query;

    if (!empty($parts['fragment'])) {
        $normalized .= '#' . $parts['fragment'];
    }

    return $normalized;
}
