<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;

class ApiIntegrationTest extends TestCase
{
    private string $baseUrl = 'http://nginx';
    private \Redis $redis;

    protected function setUp(): void
    {
        $this->redis = new \Redis();
        $this->redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int)(getenv('REDIS_PORT') ?: 6379)
        );
        // Clear rate limiter keys to allow tests to run
        $this->clearRateLimitKeys();
    }

    private function clearRateLimitKeys(): void
    {
        $keys = $this->redis->keys('ratelimit:*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }

    private function makeRequest(
        string $method,
        string $path,
        ?array $data = null,
        array $headers = []
    ): array {
        $url = $this->baseUrl . $path;

        $defaultHeaders = [
            'Accept-Language: en-US,en;q=0.9',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) PHPUnit Test',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                    $defaultHeaders,
                    $headers,
                    ['Content-Type: application/json']
                ));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->fail("cURL error: $error");
        }

        return [
            'status' => $httpCode,
            'body' => $response,
            'json' => json_decode($response, true),
        ];
    }

    #[Test]
    public function shortenApi_createsShortUrl(): array
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://example.com/integration-test-' . uniqid(),
        ]);

        $this->assertEquals(201, $response['status']);
        $this->assertTrue($response['json']['success']);
        $this->assertArrayHasKey('short_code', $response['json']);
        $this->assertArrayHasKey('short_url', $response['json']);
        $this->assertFalse($response['json']['existing']);

        return $response['json'];
    }

    #[Test]
    #[Depends('shortenApi_createsShortUrl')]
    public function shortenApi_returnsSameCodeForDuplicate(array $first): void
    {
        // Use unique URL to ensure first request creates a new entry
        $uniqueUrl = 'https://example.com/integration-test-duplicate-' . uniqid();

        $response = $this->makeRequest('POST', '/shorten', [
            'url' => $uniqueUrl,
        ]);
        $this->assertEquals(201, $response['status']);

        // Submit same URL again
        $response2 = $this->makeRequest('POST', '/shorten', [
            'url' => $uniqueUrl,
        ]);

        $this->assertEquals(200, $response2['status']);
        $this->assertTrue($response2['json']['existing']);
        $this->assertEquals($response['json']['short_code'], $response2['json']['short_code']);
    }

    #[Test]
    public function shortenApi_rejectsInvalidUrl(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'not-a-valid-url',
        ]);

        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['json']);
    }

    #[Test]
    public function shortenApi_rejectsMissingUrl(): void
    {
        $response = $this->makeRequest('POST', '/shorten', []);

        $this->assertEquals(400, $response['status']);
    }

    #[Test]
    public function shortenApi_blocksUrlShorteners(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://bit.ly/test123',
        ]);

        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('shortener', strtolower($response['json']['error']));
    }

    #[Test]
    public function shortenApi_blocksPrivateIps(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'http://192.168.1.1/admin',
        ]);

        $this->assertEquals(400, $response['status']);
    }

    #[Test]
    public function shortenApi_blocksLocalhost(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'http://localhost/secret',
        ]);

        $this->assertEquals(400, $response['status']);
    }

    #[Test]
    public function shortenApi_blocksPhishingDomains(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://paypal-login.com/verify',
        ]);

        $this->assertEquals(400, $response['status']);
    }

    #[Test]
    public function shortenApi_blocksBotUserAgents(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://example.com/bot-test',
        ], [
            'User-Agent: python-requests/2.28.0',
        ]);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('blocked', strtolower($response['json']['error']));
    }

    #[Test]
    public function shortenApi_blocksHoneypotFilled(): void
    {
        $response = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://example.com/honeypot-test',
            'website' => 'spam-value',
        ]);

        $this->assertEquals(403, $response['status']);
    }

    #[Test]
    public function statsApi_returnsUrlStats(): void
    {
        // Create a URL first
        $createResponse = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://example.com/stats-test-' . uniqid(),
        ]);
        $shortCode = $createResponse['json']['short_code'];

        // Get stats
        $response = $this->makeRequest('GET', "/api/stats?code={$shortCode}");

        $this->assertEquals(200, $response['status']);
        $this->assertEquals($shortCode, $response['json']['short_code']);
        $this->assertArrayHasKey('original_url', $response['json']);
        $this->assertArrayHasKey('click_count', $response['json']);
        $this->assertArrayHasKey('created_at', $response['json']);
    }

    #[Test]
    public function statsApi_returnsErrorForInvalidCode(): void
    {
        $response = $this->makeRequest('GET', '/api/stats?code=INVALID123');

        $this->assertEquals(404, $response['status']);
        $this->assertArrayHasKey('error', $response['json']);
    }

    #[Test]
    public function statsApi_returnsErrorForMissingCode(): void
    {
        $response = $this->makeRequest('GET', '/api/stats');

        $this->assertEquals(400, $response['status']);
    }

    #[Test]
    public function redirectEndpoint_redirectsToOriginalUrl(): void
    {
        // Create a URL
        $originalUrl = 'https://example.com/redirect-test-' . uniqid();
        $createResponse = $this->makeRequest('POST', '/shorten', [
            'url' => $originalUrl,
        ]);
        $shortCode = $createResponse['json']['short_code'];

        // Access redirect endpoint
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/' . $shortCode,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (X11; Linux x86_64) Test',
                'Accept-Language: en-US',
                'Accept: text/html',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $this->assertEquals(302, $httpCode);
        $this->assertEquals($originalUrl, $redirectUrl);
    }

    #[Test]
    public function redirectEndpoint_incrementsClickCount(): void
    {
        // Create a URL
        $createResponse = $this->makeRequest('POST', '/shorten', [
            'url' => 'https://example.com/click-count-test-' . uniqid(),
        ]);
        $shortCode = $createResponse['json']['short_code'];

        // Access the redirect endpoint multiple times
        for ($i = 0; $i < 3; $i++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/' . $shortCode,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (X11; Linux x86_64) Test',
                    'Accept-Language: en-US',
                    'Accept: text/html',
                ],
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        // Check stats
        $statsResponse = $this->makeRequest('GET', "/api/stats?code={$shortCode}");

        $this->assertEquals(3, $statsResponse['json']['click_count']);
    }

    #[Test]
    public function redirectEndpoint_returns404ForInvalidCode(): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/INVALIDCODE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 Test',
                'Accept-Language: en-US',
                'Accept: text/html',
            ],
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(404, $httpCode);
    }

    #[Test]
    public function homePage_returnsApiInfo(): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 Test',
                'Accept-Language: en-US',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $httpCode);
        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertEquals('Elephurl API', $data['name']);
        $this->assertArrayHasKey('endpoints', $data);
    }

    #[Test]
    public function tokenEndpoint_returnsToken(): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 Test',
                'Accept-Language: en-US',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $httpCode);
        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token', $data);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['token']);
    }

    #[Test]
    public function tokenEndpoint_returns405ForPost(): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/api/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 Test',
                'Accept-Language: en-US',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(405, $httpCode);
    }

    #[Test]
    public function healthEndpoint_returnsOk(): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/health',
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $httpCode);
        $this->assertEquals('OK', $response);
    }
}
