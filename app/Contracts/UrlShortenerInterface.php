<?php

declare(strict_types=1);

namespace App\Contracts;

interface UrlShortenerInterface
{
    public function shorten(string $originalUrl): array;

    public function resolve(string $shortCode, array $metadata = []): array;

    public function getStats(string $shortCode): array;
}
