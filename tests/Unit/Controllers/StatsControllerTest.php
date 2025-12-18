<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Controllers\StatsController;
use App\Http\Request;
use App\Contracts\UrlShortenerInterface;

class StatsControllerTest extends TestCase
{
    private array $originalServer;
    private array $originalGet;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/stats';
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
    }

    #[Test]
    public function invoke_returnsMissingCodeError(): void
    {
        $_GET = [];

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);

        $controller = new StatsController($request, $urlShortener);
        $response = $controller();

        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertStringContainsString('code', $json['error']);
    }

    #[Test]
    public function invoke_returnsInvalidCodeFormatError(): void
    {
        $_GET = ['code' => 'ab'];  // Too short

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);

        $controller = new StatsController($request, $urlShortener);
        $response = $controller();

        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid', $json['error']);
    }

    #[Test]
    public function invoke_returnsInvalidCodeFormatForSpecialChars(): void
    {
        $_GET = ['code' => 'abc-123'];  // Contains hyphen

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);

        $controller = new StatsController($request, $urlShortener);
        $response = $controller();

        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function invoke_returnsInvalidCodeFormatForTooLong(): void
    {
        $_GET = ['code' => 'abcdefghijk'];  // 11 chars, too long

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);

        $controller = new StatsController($request, $urlShortener);
        $response = $controller();

        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function invoke_returnsNotFoundForMissingUrl(): void
    {
        $_GET = ['code' => 'abc1234'];

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->method('getStats')->willReturn([
            'success' => false,
            'error' => 'URL not found',
        ]);

        $controller = new StatsController($request, $urlShortener);
        $response = $controller();

        $this->assertEquals(404, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('URL not found', $json['error']);
    }

    #[Test]
    public function invoke_returnsStatsData(): void
    {
        $_GET = ['code' => 'abc1234'];

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->method('getStats')->willReturn([
            'success' => true,
            'data' => [
                'short_code' => 'abc1234',
                'original_url' => 'https://example.com',
                'click_count' => 42,
                'created_at' => '2024-01-01 00:00:00',
            ],
        ]);

        $controller = new StatsController($request, $urlShortener);
        $response = $controller();

        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('abc1234', $json['short_code']);
        $this->assertEquals('https://example.com', $json['original_url']);
        $this->assertEquals(42, $json['click_count']);
    }

    #[Test]
    public function invoke_callsGetStatsWithCode(): void
    {
        $_GET = ['code' => 'test123'];

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->expects($this->once())
            ->method('getStats')
            ->with('test123')
            ->willReturn(['success' => false, 'error' => 'Not found']);

        $controller = new StatsController($request, $urlShortener);
        $controller();
    }

    #[Test]
    public function invoke_acceptsValidCodeLengths(): void
    {
        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->method('getStats')->willReturn([
            'success' => true,
            'data' => ['short_code' => 'test'],
        ]);

        // Test 6 characters (minimum)
        $_GET = ['code' => 'abcdef'];
        $controller = new StatsController(new Request(), $urlShortener);
        $response = $controller();
        $this->assertEquals(200, $response->getStatusCode());

        // Test 10 characters (maximum)
        $_GET = ['code' => 'abcdefghij'];
        $controller = new StatsController(new Request(), $urlShortener);
        $response = $controller();
        $this->assertEquals(200, $response->getStatusCode());
    }
}
