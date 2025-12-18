<?php

declare(strict_types=1);

namespace App\Helpers;

class UrlHelper
{
    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = strtolower($parts['host'] ?? '');

        $port = $parts['port'] ?? null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $path = $parts['path'] ?? '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            $path = '/';
        }

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
            ksort($params);
            $query = '?' . http_build_query($params);
        }

        $normalized = $scheme . '://' . $host;
        if ($port) {
            $normalized .= ':' . $port;
        }
        $normalized .= $path . $query;

        if (!empty($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }

    public static function generateShortCode(int $length = 7): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
