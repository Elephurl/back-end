<?php

declare(strict_types=1);

namespace App\Security;

use App\Contracts\RateLimiterInterface;
use Redis;

class RateLimiter implements RateLimiterInterface
{
    private Redis $redis;

    private const IP_LIMIT_CREATES = 10;
    private const IP_LIMIT_CLICKS = 100;
    private const IP_WINDOW_SECONDS = 3600;

    private const GLOBAL_LIMIT_CREATES = 1000;
    private const GLOBAL_LIMIT_CLICKS = 50000;
    private const GLOBAL_WINDOW_SECONDS = 60;

    private const BURST_LIMIT = 5;
    private const BURST_WINDOW_SECONDS = 10;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function isRateLimited(string $ip, string $action = 'create'): array
    {
        $now = microtime(true);

        $burstResult = $this->checkSlidingWindow(
            "ratelimit:burst:{$action}:{$ip}",
            self::BURST_LIMIT,
            self::BURST_WINDOW_SECONDS,
            $now
        );
        if ($burstResult['limited']) {
            return [
                'limited' => true,
                'reason' => 'burst',
                'retry_after' => $burstResult['retry_after'],
                'message' => 'Too many requests. Please slow down.',
            ];
        }

        $ipLimit = $action === 'create' ? self::IP_LIMIT_CREATES : self::IP_LIMIT_CLICKS;
        $ipResult = $this->checkSlidingWindow(
            "ratelimit:ip:{$action}:{$ip}",
            $ipLimit,
            self::IP_WINDOW_SECONDS,
            $now
        );
        if ($ipResult['limited']) {
            return [
                'limited' => true,
                'reason' => 'ip_limit',
                'retry_after' => $ipResult['retry_after'],
                'message' => "Rate limit exceeded. Max {$ipLimit} {$action}s per hour.",
            ];
        }

        $globalLimit = $action === 'create' ? self::GLOBAL_LIMIT_CREATES : self::GLOBAL_LIMIT_CLICKS;
        $globalResult = $this->checkSlidingWindow(
            "ratelimit:global:{$action}",
            $globalLimit,
            self::GLOBAL_WINDOW_SECONDS,
            $now
        );
        if ($globalResult['limited']) {
            return [
                'limited' => true,
                'reason' => 'global_limit',
                'retry_after' => $globalResult['retry_after'],
                'message' => 'Service is busy. Please try again shortly.',
            ];
        }

        return ['limited' => false];
    }

    public function recordRequest(string $ip, string $action = 'create'): void
    {
        $now = microtime(true);
        $member = $now . ':' . bin2hex(random_bytes(4));

        $keys = [
            "ratelimit:burst:{$action}:{$ip}" => self::BURST_WINDOW_SECONDS,
            "ratelimit:ip:{$action}:{$ip}" => self::IP_WINDOW_SECONDS,
            "ratelimit:global:{$action}" => self::GLOBAL_WINDOW_SECONDS,
        ];

        foreach ($keys as $key => $ttl) {
            $this->redis->zAdd($key, $now, $member);
            $this->redis->expire($key, $ttl + 1);
        }
    }

    private function checkSlidingWindow(string $key, int $limit, int $windowSeconds, float $now): array
    {
        $windowStart = $now - $windowSeconds;

        $this->redis->zRemRangeByScore($key, '-inf', (string)$windowStart);

        $count = $this->redis->zCard($key);

        if ($count >= $limit) {
            $oldest = $this->redis->zRange($key, 0, 0, true);
            if (!empty($oldest)) {
                $oldestTime = (float)array_values($oldest)[0];
                $retryAfter = ceil($oldestTime + $windowSeconds - $now);
            } else {
                $retryAfter = $windowSeconds;
            }

            return [
                'limited' => true,
                'retry_after' => max(1, (int)$retryAfter),
                'count' => $count,
                'limit' => $limit,
            ];
        }

        return [
            'limited' => false,
            'count' => $count,
            'limit' => $limit,
            'remaining' => $limit - $count,
        ];
    }

    public function getStatus(string $ip, string $action = 'create'): array
    {
        $now = microtime(true);

        $burstKey = "ratelimit:burst:{$action}:{$ip}";
        $ipKey = "ratelimit:ip:{$action}:{$ip}";

        $this->redis->zRemRangeByScore($burstKey, '-inf', (string)($now - self::BURST_WINDOW_SECONDS));
        $this->redis->zRemRangeByScore($ipKey, '-inf', (string)($now - self::IP_WINDOW_SECONDS));

        return [
            'burst' => [
                'used' => $this->redis->zCard($burstKey),
                'limit' => self::BURST_LIMIT,
                'window_seconds' => self::BURST_WINDOW_SECONDS,
            ],
            'hourly' => [
                'used' => $this->redis->zCard($ipKey),
                'limit' => $action === 'create' ? self::IP_LIMIT_CREATES : self::IP_LIMIT_CLICKS,
                'window_seconds' => self::IP_WINDOW_SECONDS,
            ],
        ];
    }
}
