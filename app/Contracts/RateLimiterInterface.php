<?php

declare(strict_types=1);

namespace App\Contracts;

interface RateLimiterInterface
{
    public function isRateLimited(string $ip, string $action = 'create'): array;

    public function recordRequest(string $ip, string $action = 'create'): void;

    public function getStatus(string $ip, string $action = 'create'): array;
}
