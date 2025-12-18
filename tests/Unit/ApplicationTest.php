<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Application;
use App\Container;
use App\Http\Request;
use App\Http\Response;
use App\Contracts\RateLimiterInterface;
use App\Contracts\BotDetectorInterface;
use App\Contracts\UrlSafetyCheckerInterface;
use App\Contracts\UrlShortenerInterface;
use PDO;
use Redis;
use ReflectionClass;

class ApplicationTest extends TestCase
{
    private array $originalServer;
    private array $originalGet;
    private array $originalPost;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;

        // Default server setup
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';
        $_SERVER['HTTP_ACCEPT'] = '*/*';
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
    }

    /**
     * Helper to invoke private method via reflection
     */
    private function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    #[Test]
    public function constructor_createsContainer(): void
    {
        $app = new Application();

        $this->assertInstanceOf(Container::class, $app->getContainer());
    }

    #[Test]
    public function getContainer_returnsContainer(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertInstanceOf(Container::class, $container);
    }

    #[Test]
    public function container_hasRequestRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(Request::class));
        $this->assertInstanceOf(Request::class, $container->make(Request::class));
    }

    #[Test]
    public function container_hasPdoRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(PDO::class));
    }

    #[Test]
    public function container_hasRedisRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(Redis::class));
    }

    #[Test]
    public function container_hasRateLimiterRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(RateLimiterInterface::class));
    }

    #[Test]
    public function container_hasBotDetectorRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(BotDetectorInterface::class));
    }

    #[Test]
    public function container_hasUrlSafetyCheckerRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(UrlSafetyCheckerInterface::class));
    }

    #[Test]
    public function container_hasUrlShortenerRegistered(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $this->assertTrue($container->has(UrlShortenerInterface::class));
    }

    #[Test]
    public function container_pdoIsSingleton(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $first = $container->make(PDO::class);
        $second = $container->make(PDO::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function container_redisIsSingleton(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $first = $container->make(Redis::class);
        $second = $container->make(Redis::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function container_rateLimiterIsSingleton(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $first = $container->make(RateLimiterInterface::class);
        $second = $container->make(RateLimiterInterface::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function container_botDetectorIsSingleton(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $first = $container->make(BotDetectorInterface::class);
        $second = $container->make(BotDetectorInterface::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function container_urlSafetyCheckerIsSingleton(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $first = $container->make(UrlSafetyCheckerInterface::class);
        $second = $container->make(UrlSafetyCheckerInterface::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function container_urlShortenerIsSingleton(): void
    {
        $app = new Application();
        $container = $app->getContainer();

        $first = $container->make(UrlShortenerInterface::class);
        $second = $container->make(UrlShortenerInterface::class);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function handleRequest_routesToHomeForRoot(): void
    {
        $_SERVER['REQUEST_URI'] = '/';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('Elephurl API', $response->getContent());
    }

    #[Test]
    public function handleRequest_routesToHomeForIndexPhp(): void
    {
        $_SERVER['REQUEST_URI'] = '/index.php';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function handleRequest_routesToShortenForShortenPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/shorten';
        $_SERVER['REQUEST_METHOD'] = 'GET';  // Should return 405

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(405, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_routesToStatsForApiStatsPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/stats';
        $_GET = [];

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertEquals(400, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_routesToHealthCheck(): void
    {
        $_SERVER['REQUEST_URI'] = '/health';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals('text/plain', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function handleRequest_routesToRedirectForShortCode(): void
    {
        $_SERVER['REQUEST_URI'] = '/abc1234';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        // Will return 404 since this short code doesn't exist
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_returns404ForUnknownPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/unknown/path/here';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_returns404ForShortPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/ab';  // Too short for short code

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_returns404ForLongPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/abcdefghijk';  // 11 chars, too long for short code

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_returns404ForSpecialCharsInPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/abc-123';  // Contains special char

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function handleRequest_routesToTokenForApiTokenPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/token';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRequest');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function handleToken_returnsJsonWithToken(): void
    {
        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleToken');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $content);
    }

    #[Test]
    public function handleShorten_returnsJsonResponse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleShorten');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function handleStats_returnsJsonResponse(): void
    {
        $_GET = ['code' => 'abc1234'];

        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleStats');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function handleRedirect_returnsResponse(): void
    {
        $app = new Application();
        $response = $this->invokePrivateMethod($app, 'handleRedirect', ['notfound']);

        $this->assertInstanceOf(Response::class, $response);
        // Will be 404 for non-existent code
        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function run_sendsResponse(): void
    {
        $_SERVER['REQUEST_URI'] = '/health';

        $app = new Application();

        ob_start();
        @$app->run();
        $output = ob_get_clean();

        $this->assertEquals('OK', $output);
    }
}
