<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;
use Redis;
use App\Security\RateLimiter;
use App\Security\BotDetector;
use App\Security\UrlSafetyChecker;

class BootstrapHelpersTest extends TestCase
{
    #[Test]
    public function getDb_returnsPdoInstance(): void
    {
        $pdo = getDb();

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    #[Test]
    public function getDb_returnsSameInstance(): void
    {
        $pdo1 = getDb();
        $pdo2 = getDb();

        $this->assertSame($pdo1, $pdo2);
    }

    #[Test]
    public function getDb_configuresErrorMode(): void
    {
        $pdo = getDb();

        $this->assertEquals(
            PDO::ERRMODE_EXCEPTION,
            $pdo->getAttribute(PDO::ATTR_ERRMODE)
        );
    }

    #[Test]
    public function getDb_configuresFetchMode(): void
    {
        $pdo = getDb();

        $this->assertEquals(
            PDO::FETCH_ASSOC,
            $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE)
        );
    }

    #[Test]
    public function getRedis_returnsRedisInstance(): void
    {
        $redis = getRedis();

        $this->assertInstanceOf(Redis::class, $redis);
    }

    #[Test]
    public function getRedis_returnsSameInstance(): void
    {
        $redis1 = getRedis();
        $redis2 = getRedis();

        $this->assertSame($redis1, $redis2);
    }

    #[Test]
    public function getRedis_isConnected(): void
    {
        $redis = getRedis();

        $this->assertTrue($redis->ping() === true || $redis->ping() === '+PONG');
    }

    #[Test]
    public function getRateLimiter_returnsInstance(): void
    {
        $limiter = getRateLimiter();

        $this->assertInstanceOf(RateLimiter::class, $limiter);
    }

    #[Test]
    public function getRateLimiter_returnsSameInstance(): void
    {
        $limiter1 = getRateLimiter();
        $limiter2 = getRateLimiter();

        $this->assertSame($limiter1, $limiter2);
    }

    #[Test]
    public function getBotDetector_returnsInstance(): void
    {
        $detector = getBotDetector();

        $this->assertInstanceOf(BotDetector::class, $detector);
    }

    #[Test]
    public function getBotDetector_returnsSameInstance(): void
    {
        $detector1 = getBotDetector();
        $detector2 = getBotDetector();

        $this->assertSame($detector1, $detector2);
    }

    #[Test]
    public function getUrlSafetyChecker_returnsInstance(): void
    {
        $checker = getUrlSafetyChecker();

        $this->assertInstanceOf(UrlSafetyChecker::class, $checker);
    }

    #[Test]
    public function getUrlSafetyChecker_returnsSameInstance(): void
    {
        $checker1 = getUrlSafetyChecker();
        $checker2 = getUrlSafetyChecker();

        $this->assertSame($checker1, $checker2);
    }

    #[Test]
    public function generateShortCode_returnsCorrectLength(): void
    {
        $code = generateShortCode();

        $this->assertEquals(7, strlen($code));
    }

    #[Test]
    public function generateShortCode_acceptsCustomLength(): void
    {
        $code = generateShortCode(10);

        $this->assertEquals(10, strlen($code));
    }

    #[Test]
    public function generateShortCode_containsOnlyAlphanumeric(): void
    {
        $code = generateShortCode();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $code);
    }

    #[Test]
    public function generateShortCode_generatesUniqueValues(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = generateShortCode();
        }

        // All codes should be unique
        $this->assertEquals(count($codes), count(array_unique($codes)));
    }

    #[Test]
    #[DataProvider('normalizeUrlProvider')]
    public function normalizeUrl_normalizesCorrectly(string $input, string $expected): void
    {
        $result = normalizeUrl($input);

        $this->assertEquals($expected, $result);
    }

    public static function normalizeUrlProvider(): array
    {
        return [
            'lowercase host' => [
                'https://EXAMPLE.COM/path',
                'https://example.com/path'
            ],
            'lowercase scheme' => [
                'HTTPS://example.com/path',
                'https://example.com/path'
            ],
            'remove trailing slash' => [
                'https://example.com/path/',
                'https://example.com/path'
            ],
            'keep root slash' => [
                'https://example.com/',
                'https://example.com/'
            ],
            'add root path' => [
                'https://example.com',
                'https://example.com/'
            ],
            'remove default http port' => [
                'http://example.com:80/path',
                'http://example.com/path'
            ],
            'remove default https port' => [
                'https://example.com:443/path',
                'https://example.com/path'
            ],
            'keep non-default port' => [
                'https://example.com:8443/path',
                'https://example.com:8443/path'
            ],
            'sort query params' => [
                'https://example.com/search?z=3&a=1&m=2',
                'https://example.com/search?a=1&m=2&z=3'
            ],
            'preserve fragment' => [
                'https://example.com/page#section',
                'https://example.com/page#section'
            ],
            'complex url normalization' => [
                'HTTPS://EXAMPLE.COM:443/Path/?b=2&a=1#Top',
                'https://example.com/Path?a=1&b=2#Top'
            ],
        ];
    }

    #[Test]
    public function normalizeUrl_handlesEmptyPath(): void
    {
        $result = normalizeUrl('https://example.com');

        $this->assertStringEndsWith('/', $result);
    }

    #[Test]
    public function normalizeUrl_preservesQueryValues(): void
    {
        $result = normalizeUrl('https://example.com/search?q=hello+world&lang=en');

        $this->assertStringContainsString('q=hello', $result);
        $this->assertStringContainsString('lang=en', $result);
    }

    #[Test]
    public function getClientIp_returnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $ip = getClientIp();

        $this->assertEquals('192.168.1.100', $ip);
    }

    #[Test]
    public function getClientIp_prefersXForwardedFor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18';

        $ip = getClientIp();

        $this->assertEquals('203.0.113.50', $ip);
    }

    #[Test]
    public function getClientIp_usesXRealIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.60';

        $ip = getClientIp();

        $this->assertEquals('203.0.113.60', $ip);
    }

    #[Test]
    public function getClientIp_ignoresPrivateForwardedIps(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';

        $ip = getClientIp();

        // Should fall back to REMOTE_ADDR since forwarded IP is private
        $this->assertEquals('10.0.0.1', $ip);
    }

    #[Test]
    public function getClientIp_usesClientIpHeader(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.70';

        $ip = getClientIp();

        $this->assertEquals('203.0.113.70', $ip);
    }

    #[Test]
    public function getClientIp_returnsDefaultOnMissing(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $ip = getClientIp();

        $this->assertEquals('0.0.0.0', $ip);
    }

    #[Test]
    public function jsonResponse_setsCorrectStatusCode(): void
    {
        // We can't test exit behavior directly, but we can test that
        // the function exists and accepts the right parameters
        $this->assertTrue(function_exists('jsonResponse'));

        // Verify function signature by reflection
        $reflection = new \ReflectionFunction('jsonResponse');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('data', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertEquals(200, $params[1]->getDefaultValue());
    }

    protected function tearDown(): void
    {
        // Clean up $_SERVER modifications
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
    }
}
