<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';
        return new self(json_encode($data), $statusCode, $headers);
    }

    public static function html(string $content, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=UTF-8';
        return new self($content, $statusCode, $headers);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        $response = new self('', $statusCode, [
            'Location' => $url,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
        return $response;
    }

    public static function notFound(string $message = '404 - Not Found'): self
    {
        return new self($message, 404);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->content;
    }
}
