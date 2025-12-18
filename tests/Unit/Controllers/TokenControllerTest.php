<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Controllers\TokenController;
use App\Http\Request;
use App\Contracts\BotDetectorInterface;

class TokenControllerTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/token';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function invoke_returnsJsonResponse(): void
    {
        $request = new Request();
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('generateFormToken')->willReturn('test-token-123');

        $controller = new TokenController($request, $botDetector);
        $response = $controller();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function invoke_returnsTokenInResponse(): void
    {
        $request = new Request();
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->method('generateFormToken')->willReturn('test-token-456');

        $controller = new TokenController($request, $botDetector);
        $response = $controller();

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $content);
        $this->assertEquals('test-token-456', $content['token']);
    }

    #[Test]
    public function invoke_callsGenerateFormToken(): void
    {
        $request = new Request();
        $botDetector = $this->createMock(BotDetectorInterface::class);
        $botDetector->expects($this->once())
            ->method('generateFormToken')
            ->willReturn('token');

        $controller = new TokenController($request, $botDetector);
        $controller();
    }

    #[Test]
    public function invoke_returns405ForPostRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $botDetector = $this->createMock(BotDetectorInterface::class);

        $controller = new TokenController($request, $botDetector);
        $response = $controller();

        $this->assertEquals(405, $response->getStatusCode());
    }
}
