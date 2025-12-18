<?php

declare(strict_types=1);

namespace App\Contracts;

interface UrlSafetyCheckerInterface
{
    public function checkUrl(string $url): array;
}
