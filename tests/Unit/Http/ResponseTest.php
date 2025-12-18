<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Http\Response;

class ResponseTest extends TestCase
{
    #[Test]
    public function constructor_setsDefaultValues(): void
    {
        $response = new Response();

        $this->assertEquals('', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaders());
    }

    #[Test]
    public function constructor_acceptsAllParameters(): void
    {
        $response = new Response('Hello World', 201, ['X-Custom' => 'value']);

        $this->assertEquals('Hello World', $response->getContent());
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['X-Custom' => 'value'], $response->getHeaders());
    }

    #[Test]
    public function json_createsJsonResponse(): void
    {
        $response = Response::json(['key' => 'value']);

        $this->assertEquals('{"key":"value"}', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Type', $response->getHeaders());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function json_acceptsCustomStatusCode(): void
    {
        $response = Response::json(['error' => 'Not found'], 404);

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function json_acceptsCustomHeaders(): void
    {
        $response = Response::json(['data' => 'test'], 200, ['X-Request-Id' => 'abc123']);

        $headers = $response->getHeaders();
        $this->assertEquals('abc123', $headers['X-Request-Id']);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }

    #[Test]
    public function json_encodesComplexData(): void
    {
        $data = [
            'nested' => ['a' => 1, 'b' => 2],
            'array' => [1, 2, 3],
            'string' => 'hello',
            'number' => 42,
            'boolean' => true,
            'null' => null,
        ];

        $response = Response::json($data);

        $this->assertEquals(json_encode($data), $response->getContent());
    }

    #[Test]
    public function html_createsHtmlResponse(): void
    {
        $response = Response::html('<h1>Hello</h1>');

        $this->assertEquals('<h1>Hello</h1>', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function html_acceptsCustomStatusCode(): void
    {
        $response = Response::html('<h1>Error</h1>', 500);

        $this->assertEquals(500, $response->getStatusCode());
    }

    #[Test]
    public function html_acceptsCustomHeaders(): void
    {
        $response = Response::html('<p>test</p>', 200, ['Cache-Control' => 'no-cache']);

        $headers = $response->getHeaders();
        $this->assertEquals('no-cache', $headers['Cache-Control']);
    }

    #[Test]
    public function redirect_createsRedirectResponse(): void
    {
        $response = Response::redirect('https://example.com');

        $this->assertEquals('', $response->getContent());
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeaders()['Location']);
    }

    #[Test]
    public function redirect_acceptsCustomStatusCode(): void
    {
        $response = Response::redirect('https://example.com', 301);

        $this->assertEquals(301, $response->getStatusCode());
    }

    #[Test]
    public function notFound_creates404Response(): void
    {
        $response = Response::notFound();

        $this->assertEquals('404 - Not Found', $response->getContent());
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function notFound_acceptsCustomMessage(): void
    {
        $response = Response::notFound('Page not found');

        $this->assertEquals('Page not found', $response->getContent());
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function withHeader_addsHeader(): void
    {
        $response = new Response();
        $response = $response->withHeader('X-Custom', 'value');

        $this->assertEquals('value', $response->getHeaders()['X-Custom']);
    }

    #[Test]
    public function withHeader_returnsSameInstance(): void
    {
        $response = new Response();
        $returned = $response->withHeader('X-Custom', 'value');

        $this->assertSame($response, $returned);
    }

    #[Test]
    public function withHeader_canBeChained(): void
    {
        $response = (new Response())
            ->withHeader('X-First', 'value1')
            ->withHeader('X-Second', 'value2');

        $headers = $response->getHeaders();
        $this->assertEquals('value1', $headers['X-First']);
        $this->assertEquals('value2', $headers['X-Second']);
    }

    #[Test]
    public function withStatus_setsStatusCode(): void
    {
        $response = new Response();
        $response = $response->withStatus(201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function withStatus_returnsSameInstance(): void
    {
        $response = new Response();
        $returned = $response->withStatus(201);

        $this->assertSame($response, $returned);
    }

    #[Test]
    public function withStatus_canBeChained(): void
    {
        $response = (new Response())
            ->withStatus(201)
            ->withHeader('Location', '/new-resource');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('/new-resource', $response->getHeaders()['Location']);
    }

    #[Test]
    public function getStatusCode_returnsStatusCode(): void
    {
        $response = new Response('', 418);

        $this->assertEquals(418, $response->getStatusCode());
    }

    #[Test]
    public function getHeaders_returnsAllHeaders(): void
    {
        $response = new Response('', 200, ['A' => '1', 'B' => '2']);

        $headers = $response->getHeaders();

        $this->assertCount(2, $headers);
        $this->assertEquals('1', $headers['A']);
        $this->assertEquals('2', $headers['B']);
    }

    #[Test]
    public function getContent_returnsContent(): void
    {
        $response = new Response('Test content');

        $this->assertEquals('Test content', $response->getContent());
    }

    #[Test]
    public function send_outputsContent(): void
    {
        $response = new Response('Test output', 200);

        ob_start();
        @$response->send(); // Suppress header warnings in CLI
        $output = ob_get_clean();

        $this->assertEquals('Test output', $output);
    }
}
