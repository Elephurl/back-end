<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Security\RateLimiter;
use Redis;

class RateLimiterTest extends TestCase
{
    private Redis $redis;
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->redis = new Redis();
        $this->redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int)(getenv('REDIS_PORT') ?: 6379)
        );
        // Use a unique test prefix for each test run
        $this->redis->setOption(Redis::OPT_PREFIX, 'test:' . uniqid() . ':');
        $this->rateLimiter = new RateLimiter($this->redis);
    }

    protected function tearDown(): void
    {
        // Clean up test keys (with our unique prefix)
        $keys = $this->redis->keys('*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }

    #[Test]
    public function isRateLimited_allowsFirstRequest(): void
    {
        $result = $this->rateLimiter->isRateLimited('192.168.1.100', 'create');

        $this->assertFalse($result['limited']);
    }

    #[Test]
    public function isRateLimited_allowsRequestsUnderBurstLimit(): void
    {
        $ip = '192.168.1.101';

        // Make 4 requests (under burst limit of 5)
        for ($i = 0; $i < 4; $i++) {
            $result = $this->rateLimiter->isRateLimited($ip, 'create');
            $this->assertFalse($result['limited'], "Request $i should not be limited");
            $this->rateLimiter->recordRequest($ip, 'create');
        }
    }

    #[Test]
    public function isRateLimited_blocksAfterBurstLimit(): void
    {
        $ip = '192.168.1.102';

        // Make 5 requests to hit burst limit
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->isRateLimited($ip, 'create');
            $this->rateLimiter->recordRequest($ip, 'create');
        }

        // 6th request should be blocked
        $result = $this->rateLimiter->isRateLimited($ip, 'create');

        $this->assertTrue($result['limited']);
        $this->assertEquals('burst', $result['reason']);
        $this->assertArrayHasKey('retry_after', $result);
        $this->assertStringContainsString('slow down', strtolower($result['message']));
    }

    #[Test]
    public function isRateLimited_blocksAfterIpLimit(): void
    {
        $ip = '192.168.1.103';

        // Directly insert 10 entries into the IP limit sorted set
        // bypassing burst limit tracking
        $now = microtime(true);
        $ipKey = "ratelimit:ip:create:{$ip}";

        for ($i = 0; $i < 10; $i++) {
            $member = ($now - $i) . ':' . bin2hex(random_bytes(4));
            $this->redis->zAdd($ipKey, $now - $i, $member);
        }
        $this->redis->expire($ipKey, 3600);

        // Next request should be blocked by IP limit
        $result = $this->rateLimiter->isRateLimited($ip, 'create');

        $this->assertTrue($result['limited']);
        $this->assertEquals('ip_limit', $result['reason']);
        $this->assertStringContainsString('10', $result['message']);
    }

    #[Test]
    public function isRateLimited_differentActionsHaveSeparateLimits(): void
    {
        $ip = '192.168.1.104';

        // Hit burst limit for 'create'
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->recordRequest($ip, 'create');
        }

        // 'click' action should still be allowed
        $result = $this->rateLimiter->isRateLimited($ip, 'click');
        $this->assertFalse($result['limited']);
    }

    #[Test]
    public function recordRequest_addsEntryToAllWindows(): void
    {
        $ip = '192.168.1.105';

        // Get counts before
        $burstBefore = $this->redis->zCard("ratelimit:burst:create:{$ip}");
        $ipBefore = $this->redis->zCard("ratelimit:ip:create:{$ip}");
        $globalBefore = $this->redis->zCard("ratelimit:global:create");

        $this->rateLimiter->recordRequest($ip, 'create');

        // Check that entries increased by 1
        $burstAfter = $this->redis->zCard("ratelimit:burst:create:{$ip}");
        $ipAfter = $this->redis->zCard("ratelimit:ip:create:{$ip}");
        $globalAfter = $this->redis->zCard("ratelimit:global:create");

        $this->assertEquals($burstBefore + 1, $burstAfter);
        $this->assertEquals($ipBefore + 1, $ipAfter);
        $this->assertEquals($globalBefore + 1, $globalAfter);
    }

    #[Test]
    public function getStatus_returnsCurrentUsage(): void
    {
        $ip = '192.168.1.106';

        // Make 3 requests
        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->recordRequest($ip, 'create');
        }

        $status = $this->rateLimiter->getStatus($ip, 'create');

        $this->assertEquals(3, $status['burst']['used']);
        $this->assertEquals(5, $status['burst']['limit']);
        $this->assertEquals(3, $status['hourly']['used']);
        $this->assertEquals(10, $status['hourly']['limit']);
    }

    #[Test]
    public function isRateLimited_differentIpsHaveSeparateLimits(): void
    {
        $ip1 = '192.168.1.107';
        $ip2 = '192.168.1.108';

        // Hit burst limit for ip1
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->recordRequest($ip1, 'create');
        }

        // ip2 should still be allowed
        $result = $this->rateLimiter->isRateLimited($ip2, 'create');
        $this->assertFalse($result['limited']);
    }

    #[Test]
    public function isRateLimited_clickActionHasHigherLimit(): void
    {
        $ip = '192.168.1.109';

        $status = $this->rateLimiter->getStatus($ip, 'click');

        $this->assertEquals(100, $status['hourly']['limit']);
    }

    #[Test]
    public function isRateLimited_blocksAfterGlobalLimit(): void
    {
        $ip = '192.168.1.200';

        // Insert 1000 entries into the global limit sorted set
        $now = microtime(true);
        $globalKey = "ratelimit:global:create";

        for ($i = 0; $i < 1000; $i++) {
            $member = ($now - $i * 0.001) . ':' . bin2hex(random_bytes(4));
            $this->redis->zAdd($globalKey, $now - $i * 0.001, $member);
        }
        $this->redis->expire($globalKey, 60);

        // Next request should be blocked by global limit
        $result = $this->rateLimiter->isRateLimited($ip, 'create');

        $this->assertTrue($result['limited']);
        $this->assertEquals('global_limit', $result['reason']);
        $this->assertStringContainsString('busy', strtolower($result['message']));
    }

    #[Test]
    public function isRateLimited_returnsRetryAfterInSeconds(): void
    {
        $ip = '192.168.1.110';

        // Hit burst limit
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->recordRequest($ip, 'create');
        }

        $result = $this->rateLimiter->isRateLimited($ip, 'create');

        $this->assertTrue($result['limited']);
        $this->assertIsInt($result['retry_after']);
        $this->assertGreaterThan(0, $result['retry_after']);
        $this->assertLessThanOrEqual(10, $result['retry_after']); // Within burst window
    }
}
