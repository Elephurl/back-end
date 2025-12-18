<?php

declare(strict_types=1);

namespace App\Http;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $post;
    private array $server;
    private ?array $json = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = $this->parsePath($this->uri);
        $this->query = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
    }

    private function parsePath(string $uri): string
    {
        $path = trim(explode('?', $uri)[0], '/');
        return $path === '' ? '/' : $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json()[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?? []);
    }

    public function json(): ?array
    {
        if ($this->json === null) {
            $content = file_get_contents('php://input');
            if ($content) {
                $this->json = json_decode($content, true) ?? [];
            } else {
                $this->json = [];
            }
        }
        return $this->json;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$serverKey] ?? $default;
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function ip(): string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
        foreach ($headers as $header) {
            if (!empty($this->server[$header])) {
                $ips = explode(',', $this->server[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    public function baseUrl(): string
    {
        $scheme = (isset($this->server['HTTPS']) && $this->server['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}";
    }
}
