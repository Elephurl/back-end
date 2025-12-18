<?php

declare(strict_types=1);

namespace App\Security;

use App\Contracts\UrlSafetyCheckerInterface;

class UrlSafetyChecker implements UrlSafetyCheckerInterface
{
    private const URL_SHORTENERS = [
        'bit.ly', 'bitly.com', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly',
        'is.gd', 'v.gd', 'buff.ly', 'j.mp', 'short.io', 'rebrand.ly',
        'tiny.cc', 'cutt.ly', 'shorturl.at', 'rb.gy', 't.ly', 'surl.li',
        'qr.ae', 'adf.ly', 'bc.vc', 'po.st', 'mcaf.ee', 'su.pr',
        'yourls.org', 'bl.ink', 'clck.ru', 'shortcm.li', '1url.com',
    ];

    private const BLOCKED_DOMAINS = [
        'login-', '-login', 'signin-', '-signin', 'account-', '-account',
        'secure-', '-secure', 'verify-', '-verify', 'update-', '-update',
        'confirm-', '-confirm', 'banking-', '-banking', 'paypal-', '-paypal',
        '.tk', '.ml', '.ga', '.cf', '.gq',
        'example-phishing.com',
    ];

    private const SUSPICIOUS_PATTERNS = [
        '/^https?:\/\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',
        '/^https?:\/\/([^\/]+\.){4,}[^\/]+/',
        '/^data:/i',
        '/^javascript:/i',
        '/[а-яА-Я].*[a-zA-Z]|[a-zA-Z].*[а-яА-Я]/',
        '/^.{2048,}$/',
        '/^https?:\/\/[^:]+:[^@]+@/',
    ];

    private const SUSPICIOUS_EXTENSIONS = [
        '.exe', '.msi', '.bat', '.cmd', '.ps1', '.vbs', '.js', '.jar',
        '.scr', '.pif', '.com', '.hta', '.wsf', '.wsh',
    ];

    public function checkUrl(string $url): array
    {
        $issues = [];

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return [
                'safe' => false,
                'reason' => 'invalid_url',
                'message' => 'Invalid URL format.',
            ];
        }

        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '';

        if ($this->isUrlShortener($host)) {
            $issues[] = [
                'type' => 'shortener_chain',
                'severity' => 'high',
                'host' => $host,
            ];
        }

        $blockedResult = $this->checkBlockedDomains($host);
        if ($blockedResult !== null) {
            $issues[] = $blockedResult;
        }

        foreach ($this->checkSuspiciousPatterns($url) as $pattern) {
            $issues[] = $pattern;
        }

        $extResult = $this->checkFileExtension($path);
        if ($extResult !== null) {
            $issues[] = $extResult;
        }

        if ($this->isPrivateOrLocalhost($host)) {
            $issues[] = [
                'type' => 'private_ip',
                'severity' => 'high',
                'host' => $host,
            ];
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            $issues[] = [
                'type' => 'invalid_scheme',
                'severity' => 'high',
                'scheme' => $scheme,
            ];
        }

        $hasHighSeverity = false;
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'high') {
                $hasHighSeverity = true;
                break;
            }
        }

        if ($hasHighSeverity) {
            return [
                'safe' => false,
                'reason' => $issues[0]['type'],
                'message' => $this->getErrorMessage($issues[0]),
                'issues' => $issues,
            ];
        }

        return [
            'safe' => true,
            'warnings' => $issues,
        ];
    }

    private function isUrlShortener(string $host): bool
    {
        foreach (self::URL_SHORTENERS as $shortener) {
            if ($host === $shortener || str_ends_with($host, '.' . $shortener)) {
                return true;
            }
        }
        return false;
    }

    private function checkBlockedDomains(string $host): ?array
    {
        foreach (self::BLOCKED_DOMAINS as $blocked) {
            if (str_starts_with($blocked, '.') && str_ends_with($host, $blocked)) {
                return [
                    'type' => 'blocked_tld',
                    'severity' => 'high',
                    'matched' => $blocked,
                ];
            }

            if (str_contains($host, $blocked)) {
                return [
                    'type' => 'blocked_domain',
                    'severity' => 'high',
                    'matched' => $blocked,
                ];
            }
        }
        return null;
    }

    private function checkSuspiciousPatterns(string $url): array
    {
        $issues = [];

        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                $issues[] = [
                    'type' => 'suspicious_pattern',
                    'severity' => 'high',
                    'pattern' => $pattern,
                ];
            }
        }

        return $issues;
    }

    private function checkFileExtension(string $path): ?array
    {
        $pathLower = strtolower($path);

        foreach (self::SUSPICIOUS_EXTENSIONS as $ext) {
            if (str_ends_with($pathLower, $ext)) {
                return [
                    'type' => 'suspicious_extension',
                    'severity' => 'medium',
                    'extension' => $ext,
                ];
            }
        }

        return null;
    }

    private function isPrivateOrLocalhost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        if (str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        return false;
    }

    private function getErrorMessage(array $issue): string
    {
        return match ($issue['type']) {
            'shortener_chain' => 'Shortening other URL shorteners is not allowed.',
            'blocked_tld' => 'This domain type is not allowed.',
            'blocked_domain' => 'This domain is not allowed.',
            'suspicious_pattern' => 'This URL contains suspicious patterns.',
            'suspicious_extension' => 'URLs pointing to executable files are not allowed.',
            'private_ip' => 'URLs pointing to private/local addresses are not allowed.',
            'invalid_scheme' => 'Only http and https URLs are allowed.',
            default => 'This URL is not allowed.',
        };
    }

    public function blockDomain(\Redis $redis, string $domain, int $ttlSeconds = 86400): void
    {
        $redis->setex("blocked_domain:" . strtolower($domain), $ttlSeconds, '1');
    }

    public function isDynamicallyBlocked(\Redis $redis, string $host): bool
    {
        return (bool) $redis->get("blocked_domain:" . strtolower($host));
    }
}
