<?php

declare(strict_types=1);

namespace App;

use App\Contracts\BotDetectorInterface;
use App\Contracts\RateLimiterInterface;
use App\Contracts\UrlSafetyCheckerInterface;
use App\Contracts\UrlShortenerInterface;
use App\Controllers\RedirectController;
use App\Controllers\ShortenController;
use App\Controllers\StatsController;
use App\Controllers\TokenController;
use App\Http\Request;
use App\Http\Response;
use App\Security\BotDetector;
use App\Security\RateLimiter;
use App\Security\UrlSafetyChecker;
use App\Services\UrlShortenerService;
use PDO;
use Redis;

class Application
{
    private Container $container;
    private Request $request;

    private array $allowedOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000'];

    public function __construct()
    {
        $this->container = new Container();
        $this->request = new Request();
        $this->registerServices();

        $customOrigins = getenv('CORS_ORIGINS');
        if ($customOrigins) {
            $this->allowedOrigins = array_merge(
                $this->allowedOrigins,
                array_map('trim', explode(',', $customOrigins))
            );
        }
    }

    private function registerServices(): void
    {
        $this->container->instance(Request::class, $this->request);

        $this->container->singleton(PDO::class, function () {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                getenv('MYSQL_HOST') ?: 'mysql',
                getenv('MYSQL_DATABASE') ?: 'elephurl'
            );
            return new PDO(
                $dsn,
                getenv('MYSQL_USER') ?: 'elephurl',
                getenv('MYSQL_PASSWORD') ?: 'elephurl_secret',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        });

        $this->container->singleton(Redis::class, function () {
            $redis = new Redis();
            $redis->connect(
                getenv('REDIS_HOST') ?: 'redis',
                (int)(getenv('REDIS_PORT') ?: 6379)
            );
            return $redis;
        });

        $this->container->singleton(RateLimiterInterface::class, function ($c) {
            return new RateLimiter($c->make(Redis::class));
        });

        $this->container->singleton(BotDetectorInterface::class, function ($c) {
            return new BotDetector($c->make(Redis::class));
        });

        $this->container->singleton(UrlSafetyCheckerInterface::class, function () {
            return new UrlSafetyChecker();
        });

        $this->container->singleton(UrlShortenerInterface::class, function ($c) {
            return new UrlShortenerService(
                $c->make(PDO::class),
                $c->make(Redis::class)
            );
        });
    }

    public function run(): void
    {
        $response = $this->handleRequest();
        $response = $this->addCorsHeaders($response);
        $response->send();
    }

    private function addCorsHeaders(Response $response): Response
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $this->allowedOrigins, true)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Accept-Language')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function handleRequest(): Response
    {
        $path = $this->request->path();

        if ($this->request->isMethod('OPTIONS')) {
            return new Response('', 204);
        }

        if ($path === '/' || $path === 'index.php') {
            return Response::json([
                'name' => 'Elephurl API',
                'version' => '1.0.0',
                'endpoints' => [
                    'POST /shorten' => 'Shorten a URL',
                    'GET /api/stats?code=xxx' => 'Get URL statistics',
                    'GET /api/token' => 'Get a form token for bot protection',
                    'GET /{shortCode}' => 'Redirect to original URL',
                ]
            ]);
        }

        if ($path === 'shorten') {
            return $this->handleShorten();
        }

        if ($path === 'api/stats') {
            return $this->handleStats();
        }

        if ($path === 'api/token') {
            return $this->handleToken();
        }

        if ($path === 'health') {
            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        if (preg_match('/^[a-zA-Z0-9]{6,10}$/', $path)) {
            return $this->handleRedirect($path);
        }

        return Response::notFound();
    }

    private function handleShorten(): Response
    {
        $controller = new ShortenController(
            $this->request,
            $this->container->make(RateLimiterInterface::class),
            $this->container->make(BotDetectorInterface::class),
            $this->container->make(UrlSafetyCheckerInterface::class),
            $this->container->make(UrlShortenerInterface::class)
        );
        return $controller();
    }

    private function handleStats(): Response
    {
        $controller = new StatsController(
            $this->request,
            $this->container->make(UrlShortenerInterface::class)
        );
        return $controller();
    }

    private function handleToken(): Response
    {
        $controller = new TokenController(
            $this->request,
            $this->container->make(BotDetectorInterface::class)
        );
        return $controller();
    }

    private function handleRedirect(string $shortCode): Response
    {
        $controller = new RedirectController(
            $this->request,
            $this->container->make(UrlShortenerInterface::class)
        );
        return $controller($shortCode);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
