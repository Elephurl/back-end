<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers\UrlHelper;

class UrlHelperTest extends TestCase
{
    #[Test]
    #[DataProvider('normalizeUrlProvider')]
    public function normalize_normalizesUrlsCorrectly(string $input, string $expected): void
    {
        $result = UrlHelper::normalize($input);

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
            'empty path gets slash' => [
                'https://example.com',
                'https://example.com/'
            ],
            'path only slash' => [
                'https://example.com/',
                'https://example.com/'
            ],
            'mixed case scheme' => [
                'HtTpS://example.com/test',
                'https://example.com/test'
            ],
        ];
    }

    #[Test]
    public function normalize_handlesUrlWithoutScheme(): void
    {
        // When parse_url can't determine scheme
        $result = UrlHelper::normalize('example.com/path');

        // Should handle gracefully (uses http default)
        $this->assertIsString($result);
    }

    #[Test]
    public function generateShortCode_returnsCorrectDefaultLength(): void
    {
        $code = UrlHelper::generateShortCode();

        $this->assertEquals(7, strlen($code));
    }

    #[Test]
    public function generateShortCode_returnsCustomLength(): void
    {
        $this->assertEquals(5, strlen(UrlHelper::generateShortCode(5)));
        $this->assertEquals(10, strlen(UrlHelper::generateShortCode(10)));
        $this->assertEquals(15, strlen(UrlHelper::generateShortCode(15)));
    }

    #[Test]
    public function generateShortCode_containsOnlyAlphanumeric(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = UrlHelper::generateShortCode();
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $code);
        }
    }

    #[Test]
    public function generateShortCode_generatesUniqueValues(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = UrlHelper::generateShortCode();
        }

        // All codes should be unique
        $this->assertEquals(count($codes), count(array_unique($codes)));
    }

    #[Test]
    public function generateShortCode_usesSecureRandom(): void
    {
        // Generate many codes and check distribution of first character
        $firstChars = [];
        for ($i = 0; $i < 1000; $i++) {
            $code = UrlHelper::generateShortCode(1);
            $firstChars[] = $code;
        }

        // Should have some variety (not always the same char)
        $unique = count(array_unique($firstChars));
        $this->assertGreaterThan(30, $unique, 'Should have reasonable character distribution');
    }

    #[Test]
    public function generateShortCode_handlesLengthOne(): void
    {
        $code = UrlHelper::generateShortCode(1);

        $this->assertEquals(1, strlen($code));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]$/', $code);
    }

    #[Test]
    public function generateShortCode_handlesLargeLengths(): void
    {
        $code = UrlHelper::generateShortCode(100);

        $this->assertEquals(100, strlen($code));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $code);
    }
}
