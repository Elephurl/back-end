<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;

/**
 * Concrete implementation for testing abstract Controller
 */
class TestableController extends Controller
{
    public function testJson(array $data, int $status = 200): Response
    {
        return $this->json($data, $status);
    }

    public function testHtml(string $content, int $status = 200): Response
    {
        return $this->html($content, $status);
    }

    public function testRedirect(string $url, int $status = 302): Response
    {
        return $this->redirect($url, $status);
    }

    public function testNotFound(string $message = '404 - Not Found'): Response
    {
        return $this->notFound($message);
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}

class ControllerTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function constructor_storesRequest(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $this->assertSame($request, $controller->getRequest());
    }

    #[Test]
    public function json_returnsJsonResponse(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testJson(['key' => 'value']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertEquals('{"key":"value"}', $response->getContent());
    }

    #[Test]
    public function json_acceptsCustomStatus(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testJson(['error' => 'Not found'], 404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function html_returnsHtmlResponse(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testHtml('<h1>Hello</h1>');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertEquals('<h1>Hello</h1>', $response->getContent());
    }

    #[Test]
    public function html_acceptsCustomStatus(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testHtml('<h1>Error</h1>', 500);

        $this->assertEquals(500, $response->getStatusCode());
    }

    #[Test]
    public function redirect_returnsRedirectResponse(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testRedirect('https://example.com');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeaders()['Location']);
    }

    #[Test]
    public function redirect_acceptsCustomStatus(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testRedirect('https://example.com', 301);

        $this->assertEquals(301, $response->getStatusCode());
    }

    #[Test]
    public function notFound_returns404Response(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testNotFound();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('404 - Not Found', $response->getContent());
    }

    #[Test]
    public function notFound_acceptsCustomMessage(): void
    {
        $request = new Request();
        $controller = new TestableController($request);

        $response = $controller->testNotFound('Page not found');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Page not found', $response->getContent());
    }
}
