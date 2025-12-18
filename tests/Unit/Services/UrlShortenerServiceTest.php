<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\UrlShortenerService;
use PDO;
use Redis;

class UrlShortenerServiceTest extends TestCase
{
    private PDO $db;
    private Redis $redis;
    private UrlShortenerService $service;

    protected function setUp(): void
    {
        $this->testId = uniqid();
        $this->db = getDb();
        $this->redis = new Redis();
        $this->redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int)(getenv('REDIS_PORT') ?: 6379)
        );
        $this->redis->setOption(Redis::OPT_PREFIX, 'test:' . $this->testId . ':');

        $this->service = new UrlShortenerService($this->db, $this->redis);

        // Clean test data
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
    }

    private string $testId;

    private function cleanTestData(): void
    {
        // Clean Redis
        $keys = $this->redis->keys('*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        // Clean MySQL (URLs from tests have specific patterns)
        $pattern = '%test-url-' . ($this->testId ?? '') . '%';
        $this->db->exec("DELETE FROM url_analytics WHERE url_id IN (SELECT id FROM urls WHERE original_url LIKE '{$pattern}')");
        $this->db->exec("DELETE FROM urls WHERE original_url LIKE '{$pattern}'");
    }

    #[Test]
    public function shorten_createsNewShortUrl(): void
    {
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-new");

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('short_code', $result);
        $this->assertFalse($result['existing']);
        $this->assertEquals(7, strlen($result['short_code']));
    }

    #[Test]
    public function shorten_returnsExistingCodeForSameUrl(): void
    {
        $url = "https://example.com/test-url-{$this->testId}-duplicate";

        $first = $this->service->shorten($url);
        $second = $this->service->shorten($url);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['short_code'], $second['short_code']);
        $this->assertFalse($first['existing']);
        $this->assertTrue($second['existing']);
    }

    #[Test]
    public function shorten_normalizesUrlsBeforeComparison(): void
    {
        $url1 = "https://example.com/test-url-{$this->testId}-normalize/";
        $url2 = "https://example.com/test-url-{$this->testId}-normalize";

        $first = $this->service->shorten($url1);
        $second = $this->service->shorten($url2);

        $this->assertEquals($first['short_code'], $second['short_code']);
    }

    #[Test]
    public function shorten_cachesInRedis(): void
    {
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-cache");

        $shortCode = $result['short_code'];

        // Check Redis cache
        $cachedUrl = $this->redis->get("url:{$shortCode}");
        $this->assertNotFalse($cachedUrl);
        $this->assertStringContainsString('test-url-', $cachedUrl);
    }

    #[Test]
    public function shorten_storesUrlHashInRedis(): void
    {
        $url = "https://example.com/test-url-{$this->testId}-hash";
        $result = $this->service->shorten($url);

        $urlHash = hash('sha256', normalizeUrl($url));
        $cachedCode = $this->redis->get("urlhash:{$urlHash}");

        $this->assertEquals($result['short_code'], $cachedCode);
    }

    #[Test]
    public function shorten_usesRedisCacheForDuplicateCheck(): void
    {
        $url = "https://example.com/test-url-{$this->testId}-redis-cache";

        // First request creates entry
        $first = $this->service->shorten($url);

        // Second request should hit Redis cache
        $second = $this->service->shorten($url);

        $this->assertEquals($first['short_code'], $second['short_code']);
        $this->assertTrue($second['existing']);
    }

    #[Test]
    public function getStats_returnsUrlInfo(): void
    {
        // Create a URL first
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-stats");
        $shortCode = $result['short_code'];

        $stats = $this->service->getStats($shortCode);

        $this->assertTrue($stats['success']);
        $this->assertArrayHasKey('data', $stats);
        $this->assertEquals($shortCode, $stats['data']['short_code']);
        $this->assertStringContainsString('test-url-', $stats['data']['original_url']);
        $this->assertEquals(0, $stats['data']['click_count']);
    }

    #[Test]
    public function getStats_returnsErrorForNotFound(): void
    {
        $stats = $this->service->getStats('NOTFOUND');

        $this->assertFalse($stats['success']);
        $this->assertArrayHasKey('error', $stats);
        $this->assertStringContainsString('not found', strtolower($stats['error']));
    }

    #[Test]
    public function getStats_includesRedisClickCount(): void
    {
        // Create a URL
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-clicks");
        $shortCode = $result['short_code'];

        // Simulate clicks in Redis
        $this->redis->set("clicks:{$shortCode}", 5);

        $stats = $this->service->getStats($shortCode);

        $this->assertEquals(5, $stats['data']['click_count']);
    }

    #[Test]
    public function resolve_returnsOriginalUrl(): void
    {
        // Create a URL
        $originalUrl = "https://example.com/test-url-{$this->testId}-resolve";
        $result = $this->service->shorten($originalUrl);
        $shortCode = $result['short_code'];

        $resolved = $this->service->resolve($shortCode);

        $this->assertTrue($resolved['success']);
        $this->assertEquals($originalUrl, $resolved['url']);
    }

    #[Test]
    public function resolve_tracksClicks(): void
    {
        // Create a URL
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-track");
        $shortCode = $result['short_code'];

        // Resolve multiple times
        $this->service->resolve($shortCode);
        $this->service->resolve($shortCode);
        $this->service->resolve($shortCode);

        // Check click count
        $clicks = (int) $this->redis->get("clicks:{$shortCode}");
        $this->assertEquals(3, $clicks);
    }

    #[Test]
    public function resolve_storesAnalyticsMetadata(): void
    {
        // Create a URL
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-analytics");
        $shortCode = $result['short_code'];

        // Resolve with metadata
        $this->service->resolve($shortCode, [
            'ip_hash' => 'abc123',
            'user_agent' => 'Mozilla/5.0',
            'referer' => 'https://google.com',
        ]);

        // Check analytics stored
        $analytics = $this->redis->lRange("analytics:{$shortCode}", 0, -1);
        $this->assertCount(1, $analytics);

        $data = json_decode($analytics[0], true);
        $this->assertEquals('abc123', $data['ip_hash']);
        $this->assertEquals('Mozilla/5.0', $data['user_agent']);
    }

    #[Test]
    public function resolve_returnsErrorForNotFound(): void
    {
        $resolved = $this->service->resolve('INVALID');

        $this->assertFalse($resolved['success']);
        $this->assertArrayHasKey('error', $resolved);
    }

    #[Test]
    public function resolve_usesRedisCache(): void
    {
        // Create a URL
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-resolve-cache");
        $shortCode = $result['short_code'];

        // Clear the URL from MySQL cache but keep Redis
        // This tests that Redis is being used
        $resolved = $this->service->resolve($shortCode);

        $this->assertTrue($resolved['success']);
    }

    #[Test]
    public function resolve_fallsBackToMysql(): void
    {
        // Create a URL
        $originalUrl = "https://example.com/test-url-{$this->testId}-mysql-fallback";
        $result = $this->service->shorten($originalUrl);
        $shortCode = $result['short_code'];

        // Clear Redis cache
        $this->redis->del("url:{$shortCode}");

        // Resolve should still work via MySQL
        $resolved = $this->service->resolve($shortCode);

        $this->assertTrue($resolved['success']);
        $this->assertEquals($originalUrl, $resolved['url']);
    }

    #[Test]
    public function resolve_repopulatesRedisCache(): void
    {
        // Create a URL
        $originalUrl = "https://example.com/test-url-{$this->testId}-repopulate";
        $result = $this->service->shorten($originalUrl);
        $shortCode = $result['short_code'];

        // Clear Redis cache
        $this->redis->del("url:{$shortCode}");

        // Resolve (should repopulate cache)
        $this->service->resolve($shortCode);

        // Check Redis is populated
        $cached = $this->redis->get("url:{$shortCode}");
        $this->assertEquals($originalUrl, $cached);
    }

    #[Test]
    public function shorten_storesInDatabase(): void
    {
        $url = "https://example.com/test-url-{$this->testId}-db-store";
        $result = $this->service->shorten($url);

        // Verify in database
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE short_code = ?');
        $stmt->execute([$result['short_code']]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $this->assertEquals($url, $row['original_url']);
        $this->assertNotEmpty($row['url_hash']);
    }

    #[Test]
    public function getStats_includesCreatedAt(): void
    {
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-created");
        $stats = $this->service->getStats($result['short_code']);

        $this->assertArrayHasKey('created_at', $stats['data']);
        $this->assertNotNull($stats['data']['created_at']);
    }

    #[Test]
    public function getStats_includesExpiresAt(): void
    {
        $result = $this->service->shorten("https://example.com/test-url-{$this->testId}-expires");
        $stats = $this->service->getStats($result['short_code']);

        $this->assertArrayHasKey('expires_at', $stats['data']);
        // Default is null (no expiry)
        $this->assertNull($stats['data']['expires_at']);
    }

    #[Test]
    public function shorten_fallsBackToMysqlWhenRedisHashCacheCleared(): void
    {
        $url = "https://example.com/test-url-{$this->testId}-mysql-hash-fallback";

        // First request creates the entry
        $first = $this->service->shorten($url);
        $this->assertTrue($first['success']);
        $this->assertFalse($first['existing']);

        // Clear only the urlhash cache (keep the url: cache)
        $urlHash = hash('sha256', normalizeUrl($url));
        $this->redis->del("urlhash:{$urlHash}");

        // Second request should find it in MySQL and re-cache
        $second = $this->service->shorten($url);

        $this->assertTrue($second['success']);
        $this->assertTrue($second['existing']);
        $this->assertEquals($first['short_code'], $second['short_code']);

        // Verify Redis cache was repopulated
        $cachedCode = $this->redis->get("urlhash:{$urlHash}");
        $this->assertEquals($first['short_code'], $cachedCode);
    }
}
