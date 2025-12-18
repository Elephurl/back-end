<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Http\Request;

class RequestTest extends TestCase
{
    private array $originalServer;
    private array $originalGet;
    private array $originalPost;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
    }

    #[Test]
    public function method_returnsRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();

        $this->assertEquals('POST', $request->method());
    }

    #[Test]
    public function method_defaultsToGet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $request = new Request();

        $this->assertEquals('GET', $request->method());
    }

    #[Test]
    public function path_parsesSimplePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/shorten';

        $request = new Request();

        $this->assertEquals('shorten', $request->path());
    }

    #[Test]
    public function path_parsesPathWithQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/stats?code=abc123';

        $request = new Request();

        $this->assertEquals('api/stats', $request->path());
    }

    #[Test]
    public function path_returnsSlashForRoot(): void
    {
        $_SERVER['REQUEST_URI'] = '/';

        $request = new Request();

        $this->assertEquals('/', $request->path());
    }

    #[Test]
    public function path_returnsSlashForEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = '';

        $request = new Request();

        $this->assertEquals('/', $request->path());
    }

    #[Test]
    public function path_defaultsToSlash(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $request = new Request();

        $this->assertEquals('/', $request->path());
    }

    #[Test]
    public function uri_returnsFullUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/stats?code=abc123';

        $request = new Request();

        $this->assertEquals('/api/stats?code=abc123', $request->uri());
    }

    #[Test]
    public function query_returnsQueryParameter(): void
    {
        $_GET = ['code' => 'abc123', 'page' => '1'];

        $request = new Request();

        $this->assertEquals('abc123', $request->query('code'));
        $this->assertEquals('1', $request->query('page'));
    }

    #[Test]
    public function query_returnsDefaultForMissingKey(): void
    {
        $_GET = [];

        $request = new Request();

        $this->assertNull($request->query('missing'));
        $this->assertEquals('default', $request->query('missing', 'default'));
    }

    #[Test]
    public function post_returnsPostParameter(): void
    {
        $_POST = ['url' => 'https://example.com'];

        $request = new Request();

        $this->assertEquals('https://example.com', $request->post('url'));
    }

    #[Test]
    public function post_returnsDefaultForMissingKey(): void
    {
        $_POST = [];

        $request = new Request();

        $this->assertNull($request->post('missing'));
        $this->assertEquals('fallback', $request->post('missing', 'fallback'));
    }

    #[Test]
    public function input_returnsPostOverQuery(): void
    {
        $_POST = ['key' => 'post-value'];
        $_GET = ['key' => 'query-value'];

        $request = new Request();

        $this->assertEquals('post-value', $request->input('key'));
    }

    #[Test]
    public function input_fallsBackToQuery(): void
    {
        $_POST = [];
        $_GET = ['key' => 'query-value'];

        $request = new Request();

        $this->assertEquals('query-value', $request->input('key'));
    }

    #[Test]
    public function input_returnsDefaultWhenNotFound(): void
    {
        $_POST = [];
        $_GET = [];

        $request = new Request();

        $this->assertEquals('default', $request->input('missing', 'default'));
    }

    #[Test]
    public function all_mergesAllSources(): void
    {
        $_GET = ['query_key' => 'query_value'];
        $_POST = ['post_key' => 'post_value'];

        $request = new Request();
        $all = $request->all();

        $this->assertArrayHasKey('query_key', $all);
        $this->assertArrayHasKey('post_key', $all);
    }

    #[Test]
    public function header_returnsHttpHeader(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';

        $request = new Request();

        $this->assertEquals('en-US,en;q=0.9', $request->header('Accept-Language'));
    }

    #[Test]
    public function header_convertsHeaderNameFormat(): void
    {
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

        $request = new Request();

        $this->assertEquals('application/json', $request->header('Content-Type'));
    }

    #[Test]
    public function header_returnsDefaultWhenNotFound(): void
    {
        $request = new Request();

        $this->assertNull($request->header('X-Missing-Header'));
        $this->assertEquals('default', $request->header('X-Missing-Header', 'default'));
    }

    #[Test]
    public function userAgent_returnsUserAgentString(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';

        $request = new Request();

        $this->assertEquals('Mozilla/5.0 Test Browser', $request->userAgent());
    }

    #[Test]
    public function userAgent_returnsEmptyStringWhenNotSet(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);

        $request = new Request();

        $this->assertEquals('', $request->userAgent());
    }

    #[Test]
    public function ip_returnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $request = new Request();

        $this->assertEquals('192.168.1.100', $request->ip());
    }

    #[Test]
    public function ip_prefersXForwardedFor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18';

        $request = new Request();

        $this->assertEquals('203.0.113.50', $request->ip());
    }

    #[Test]
    public function ip_usesXRealIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.60';

        $request = new Request();

        $this->assertEquals('203.0.113.60', $request->ip());
    }

    #[Test]
    public function ip_usesClientIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['HTTP_CLIENT_IP'] = '203.0.113.70';

        $request = new Request();

        $this->assertEquals('203.0.113.70', $request->ip());
    }

    #[Test]
    public function ip_ignoresPrivateForwardedIps(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';

        $request = new Request();

        $this->assertEquals('10.0.0.1', $request->ip());
    }

    #[Test]
    public function ip_returnsDefaultWhenAllMissing(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);

        $request = new Request();

        $this->assertEquals('0.0.0.0', $request->ip());
    }

    #[Test]
    public function isMethod_matchesMethodCaseInsensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();

        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post'));
        $this->assertTrue($request->isMethod('Post'));
        $this->assertFalse($request->isMethod('GET'));
    }

    #[Test]
    public function baseUrl_constructsUrlWithoutHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        unset($_SERVER['HTTPS']);

        $request = new Request();

        $this->assertEquals('http://example.com', $request->baseUrl());
    }

    #[Test]
    public function baseUrl_constructsUrlWithHttps(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';

        $request = new Request();

        $this->assertEquals('https://example.com', $request->baseUrl());
    }

    #[Test]
    public function baseUrl_defaultsToLocalhost(): void
    {
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);

        $request = new Request();

        $this->assertEquals('http://localhost', $request->baseUrl());
    }

    #[Test]
    public function json_returnsEmptyArrayForNoInput(): void
    {
        $request = new Request();

        $json = $request->json();

        $this->assertIsArray($json);
        $this->assertEmpty($json);
    }

    #[Test]
    public function json_cachesParsedResult(): void
    {
        $request = new Request();

        $first = $request->json();
        $second = $request->json();

        $this->assertSame($first, $second);
    }
}
