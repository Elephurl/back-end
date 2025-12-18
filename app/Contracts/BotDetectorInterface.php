<?php

declare(strict_types=1);

namespace App\Contracts;

interface BotDetectorInterface
{
    public function generateFormToken(): string;

    public function validateSubmission(array $input, ?string $userAgent = null): array;

    public static function getHoneypotFieldsHtml(): string;
}
