<?php

declare(strict_types=1);

// Test bootstrap - loads application and sets up test environment

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Helper function aliases for backwards compatibility with existing tests
use App\Helpers\UrlHelper;

function normalizeUrl(string $url): string
{
    return UrlHelper::normalize($url);
}

function generateShortCode(int $length = 7): string
{
    return UrlHelper::generateShortCode($length);
}

// Database connection helper
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

// Redis connection helper
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

// Security service helpers for tests
function getRateLimiter(): App\Security\RateLimiter
{
    static $limiter = null;
    if ($limiter === null) {
        $limiter = new App\Security\RateLimiter(getRedis());
    }
    return $limiter;
}

function getBotDetector(): App\Security\BotDetector
{
    static $detector = null;
    if ($detector === null) {
        $detector = new App\Security\BotDetector(getRedis());
    }
    return $detector;
}

function getUrlSafetyChecker(): App\Security\UrlSafetyChecker
{
    static $checker = null;
    if ($checker === null) {
        $checker = new App\Security\UrlSafetyChecker();
    }
    return $checker;
}

// Get client IP helper
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

// JSON response helper (for backwards compatibility)
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
