<?php

declare(strict_types=1);

namespace App\Security;

use App\Contracts\BotDetectorInterface;
use Redis;

class BotDetector implements BotDetectorInterface
{
    private Redis $redis;

    public const HONEYPOT_FIELDS = ['website', 'url_confirm', 'email_address'];

    private const MIN_SUBMISSION_TIME_MS = 1500;

    private const MAX_SUBMISSION_TIME_MS = 3600000;

    private const BOT_USER_AGENTS = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python-requests',
        'go-http-client', 'java/', 'libwww', 'httpclient', 'okhttp', 'axios',
    ];

    private const SUSPICIOUS_PATTERNS = [
        'headless', 'phantom', 'selenium', 'webdriver', 'puppeteer', 'playwright',
    ];

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function generateFormToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $this->redis->setex("formtoken:{$token}", (int)(self::MAX_SUBMISSION_TIME_MS / 1000) + 60, (string)(microtime(true) * 1000));
        return $token;
    }

    public function validateSubmission(array $input, ?string $userAgent = null): array
    {
        $issues = [];

        foreach (self::HONEYPOT_FIELDS as $field) {
            if (!empty($input[$field])) {
                $issues[] = [
                    'type' => 'honeypot',
                    'field' => $field,
                    'severity' => 'critical',
                ];
            }
        }

        $formToken = $input['_token'] ?? null;
        if ($formToken) {
            $timingResult = $this->validateTiming($formToken);
            if ($timingResult !== null) {
                $issues[] = $timingResult;
            }
        }

        if ($userAgent) {
            $uaResult = $this->analyzeUserAgent($userAgent);
            if ($uaResult !== null) {
                $issues[] = $uaResult;
            }
        }

        if ($this->isMissingBrowserHeaders()) {
            $issues[] = [
                'type' => 'headers',
                'severity' => 'medium',
                'reason' => 'missing_browser_headers',
            ];
        }

        $score = $this->calculateBotScore($issues);

        return [
            'is_bot' => $score >= 70,
            'bot_score' => $score,
            'issues' => $issues,
            'action' => $this->determineAction($score),
        ];
    }

    private function validateTiming(string $token): ?array
    {
        $key = "formtoken:{$token}";
        $startTime = $this->redis->get($key);

        if ($startTime === false) {
            return [
                'type' => 'timing',
                'severity' => 'medium',
                'reason' => 'invalid_token',
            ];
        }

        $this->redis->del($key);

        $elapsed = (microtime(true) * 1000) - (float)$startTime;

        if ($elapsed < self::MIN_SUBMISSION_TIME_MS) {
            return [
                'type' => 'timing',
                'severity' => 'high',
                'reason' => 'too_fast',
                'elapsed_ms' => $elapsed,
                'min_ms' => self::MIN_SUBMISSION_TIME_MS,
            ];
        }

        if ($elapsed > self::MAX_SUBMISSION_TIME_MS) {
            return [
                'type' => 'timing',
                'severity' => 'low',
                'reason' => 'token_expired',
                'elapsed_ms' => $elapsed,
            ];
        }

        return null;
    }

    private function analyzeUserAgent(string $ua): ?array
    {
        $uaLower = strtolower($ua);

        foreach (self::BOT_USER_AGENTS as $bot) {
            if (str_contains($uaLower, $bot)) {
                return [
                    'type' => 'user_agent',
                    'severity' => 'critical',
                    'reason' => 'known_bot',
                    'matched' => $bot,
                ];
            }
        }

        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (str_contains($uaLower, $pattern)) {
                return [
                    'type' => 'user_agent',
                    'severity' => 'critical',
                    'reason' => 'automation_tool',
                    'matched' => $pattern,
                ];
            }
        }

        if (strlen($ua) < 10) {
            return [
                'type' => 'user_agent',
                'severity' => 'medium',
                'reason' => 'suspicious_ua',
            ];
        }

        return null;
    }

    private function isMissingBrowserHeaders(): bool
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return true;
        }

        if (empty($_SERVER['HTTP_ACCEPT'])) {
            return true;
        }

        return false;
    }

    private function calculateBotScore(array $issues): int
    {
        $score = 0;

        foreach ($issues as $issue) {
            switch ($issue['severity']) {
                case 'critical':
                    $score += 100;
                    break;
                case 'high':
                    $score += 40;
                    break;
                case 'medium':
                    $score += 20;
                    break;
                case 'low':
                    $score += 10;
                    break;
            }
        }

        return min(100, $score);
    }

    private function determineAction(int $score): string
    {
        if ($score >= 70) {
            return 'block';
        }
        if ($score >= 40) {
            return 'challenge';
        }
        return 'allow';
    }

    public static function getHoneypotFieldsHtml(): string
    {
        $html = '';
        foreach (self::HONEYPOT_FIELDS as $field) {
            $html .= sprintf(
                '<div style="position:absolute;left:-9999px;"><input type="text" name="%s" value="" tabindex="-1" autocomplete="off"></div>',
                htmlspecialchars($field)
            );
        }
        return $html;
    }
}
