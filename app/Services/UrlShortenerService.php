<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\UrlShortenerInterface;
use App\Helpers\UrlHelper;
use PDO;
use Redis;

class UrlShortenerService implements UrlShortenerInterface
{
    private PDO $db;
    private Redis $redis;

    public function __construct(PDO $db, Redis $redis)
    {
        $this->db = $db;
        $this->redis = $redis;
    }

    public function shorten(string $originalUrl): array
    {
        $normalizedUrl = UrlHelper::normalize($originalUrl);
        $urlHash = hash('sha256', $normalizedUrl);

        $existing = $this->redis->get("urlhash:{$urlHash}");

        if ($existing) {
            return [
                'success' => true,
                'short_code' => $existing,
                'existing' => true,
            ];
        }

        $stmt = $this->db->prepare('SELECT short_code FROM urls WHERE url_hash = ? AND (expires_at IS NULL OR expires_at > NOW())');
        $stmt->execute([$urlHash]);
        $row = $stmt->fetch();

        if ($row) {
            $shortCode = $row['short_code'];
            $this->redis->setex("urlhash:{$urlHash}", 86400, $shortCode);
            return [
                'success' => true,
                'short_code' => $shortCode,
                'existing' => true,
            ];
        }

        $shortCode = $this->generateUniqueCode();
        if ($shortCode === null) {
            return [
                'success' => false,
                'error' => 'Failed to generate unique code',
            ];
        }

        $stmt = $this->db->prepare('INSERT INTO urls (short_code, original_url, url_hash) VALUES (?, ?, ?)');
        $stmt->execute([$shortCode, $originalUrl, $urlHash]);

        $this->redis->setex("url:{$shortCode}", 86400, $originalUrl);
        $this->redis->setex("urlhash:{$urlHash}", 86400, $shortCode);

        return [
            'success' => true,
            'short_code' => $shortCode,
            'existing' => false,
        ];
    }

    public function getStats(string $shortCode): array
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE short_code = ?');
        $stmt->execute([$shortCode]);
        $url = $stmt->fetch();

        if (!$url) {
            return [
                'success' => false,
                'error' => 'URL not found',
            ];
        }

        $redisClicks = (int) $this->redis->get("clicks:{$shortCode}");
        $totalClicks = $url['click_count'] + $redisClicks;

        return [
            'success' => true,
            'data' => [
                'short_code' => $shortCode,
                'original_url' => $url['original_url'],
                'click_count' => $totalClicks,
                'created_at' => $url['created_at'],
                'expires_at' => $url['expires_at'],
            ],
        ];
    }

    public function resolve(string $shortCode, array $metadata = []): array
    {
        $cachedUrl = $this->redis->get("url:{$shortCode}");

        if ($cachedUrl) {
            $this->trackClick($shortCode, $metadata);
            return [
                'success' => true,
                'url' => $cachedUrl,
            ];
        }

        $stmt = $this->db->prepare('SELECT id, original_url FROM urls WHERE short_code = ? AND (expires_at IS NULL OR expires_at > NOW())');
        $stmt->execute([$shortCode]);
        $url = $stmt->fetch();

        if (!$url) {
            return [
                'success' => false,
                'error' => 'URL not found or expired',
            ];
        }

        $this->redis->setex("url:{$shortCode}", 86400, $url['original_url']);

        $this->trackClick($shortCode, $metadata);

        return [
            'success' => true,
            'url' => $url['original_url'],
        ];
    }

    private function trackClick(string $shortCode, array $metadata): void
    {
        $this->redis->incr("clicks:{$shortCode}");

        if (!empty($metadata)) {
            $this->redis->rPush("analytics:{$shortCode}", json_encode([
                'time' => time(),
                'ip_hash' => $metadata['ip_hash'] ?? '',
                'user_agent' => $metadata['user_agent'] ?? '',
                'referer' => $metadata['referer'] ?? '',
            ]));
        }
    }

    private function generateUniqueCode(int $maxAttempts = 10): ?string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = UrlHelper::generateShortCode();
            $stmt = $this->db->prepare('SELECT id FROM urls WHERE short_code = ?');
            $stmt->execute([$candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
        }
        return null;
    }
}
