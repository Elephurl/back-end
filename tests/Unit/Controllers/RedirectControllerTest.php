<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Controllers\RedirectController;
use App\Http\Request;
use App\Contracts\UrlShortenerInterface;

class RedirectControllerTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/abc1234';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function invoke_redirectsToOriginalUrl(): void
    {
        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->method('resolve')->willReturn([
            'success' => true,
            'url' => 'https://example.com/original-page',
        ]);

        $controller = new RedirectController($request, $urlShortener);
        $response = $controller('abc1234');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com/original-page', $response->getHeaders()['Location']);
    }

    #[Test]
    public function invoke_returnsNotFoundForMissingUrl(): void
    {
        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->method('resolve')->willReturn([
            'success' => false,
        ]);

        $controller = new RedirectController($request, $urlShortener);
        $response = $controller('notfound');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('URL not found', $response->getContent());
    }

    #[Test]
    public function invoke_passesMetadataToResolve(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://google.com';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->expects($this->once())
            ->method('resolve')
            ->with(
                'abc1234',
                $this->callback(function ($metadata) {
                    return isset($metadata['ip_hash'])
                        && isset($metadata['user_agent'])
                        && isset($metadata['referer'])
                        && $metadata['user_agent'] === 'Test Agent'
                        && $metadata['referer'] === 'https://google.com';
                })
            )
            ->willReturn(['success' => true, 'url' => 'https://example.com']);

        $controller = new RedirectController($request, $urlShortener);
        $controller('abc1234');
    }

    #[Test]
    public function invoke_hashesIpAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->expects($this->once())
            ->method('resolve')
            ->with(
                'abc1234',
                $this->callback(function ($metadata) {
                    // IP should be hashed, not raw
                    $expectedHash = hash('sha256', '192.168.1.100');
                    return $metadata['ip_hash'] === $expectedHash;
                })
            )
            ->willReturn(['success' => true, 'url' => 'https://example.com']);

        $controller = new RedirectController($request, $urlShortener);
        $controller('abc1234');
    }

    #[Test]
    public function invoke_handlesEmptyReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);

        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->expects($this->once())
            ->method('resolve')
            ->with(
                'abc1234',
                $this->callback(function ($metadata) {
                    return $metadata['referer'] === '';
                })
            )
            ->willReturn(['success' => true, 'url' => 'https://example.com']);

        $controller = new RedirectController($request, $urlShortener);
        $controller('abc1234');
    }

    #[Test]
    public function invoke_passesCorrectShortCode(): void
    {
        $request = new Request();
        $urlShortener = $this->createMock(UrlShortenerInterface::class);
        $urlShortener->expects($this->once())
            ->method('resolve')
            ->with('mycode123', $this->anything())
            ->willReturn(['success' => true, 'url' => 'https://example.com']);

        $controller = new RedirectController($request, $urlShortener);
        $controller('mycode123');
    }
}
