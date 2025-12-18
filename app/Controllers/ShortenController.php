<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\RateLimiterInterface;
use App\Contracts\BotDetectorInterface;
use App\Contracts\UrlSafetyCheckerInterface;
use App\Contracts\UrlShortenerInterface;
use App\Http\Request;
use App\Http\Response;

class ShortenController extends Controller
{
    private RateLimiterInterface $rateLimiter;
    private BotDetectorInterface $botDetector;
    private UrlSafetyCheckerInterface $safetyChecker;
    private UrlShortenerInterface $urlShortener;

    public function __construct(
        Request $request,
        RateLimiterInterface $rateLimiter,
        BotDetectorInterface $botDetector,
        UrlSafetyCheckerInterface $safetyChecker,
        UrlShortenerInterface $urlShortener
    ) {
        parent::__construct($request);
        $this->rateLimiter = $rateLimiter;
        $this->botDetector = $botDetector;
        $this->safetyChecker = $safetyChecker;
        $this->urlShortener = $urlShortener;
    }

    public function __invoke(): Response
    {
        if (!$this->request->isMethod('POST')) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }

        $clientIp = $this->request->ip();
        $userAgent = $this->request->userAgent();

        $rateResult = $this->rateLimiter->isRateLimited($clientIp, 'create');
        if ($rateResult['limited']) {
            return $this->json([
                'error' => $rateResult['message'],
                'retry_after' => $rateResult['retry_after'],
            ], 429)->withHeader('Retry-After', (string)$rateResult['retry_after']);
        }

        $input = $this->request->json() ?? [];

        $botResult = $this->botDetector->validateSubmission($input, $userAgent);
        if ($botResult['is_bot']) {
            return $this->json([
                'error' => 'Request blocked.',
                'reason' => 'suspicious_activity',
            ], 403);
        }

        $originalUrl = $input['url'] ?? null;

        if (!$originalUrl || !filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            return $this->json(['error' => 'Invalid URL provided'], 400);
        }

        $safetyResult = $this->safetyChecker->checkUrl($originalUrl);
        if (!$safetyResult['safe']) {
            return $this->json([
                'error' => $safetyResult['message'],
                'reason' => $safetyResult['reason'],
            ], 400);
        }

        $this->rateLimiter->recordRequest($clientIp, 'create');

        $result = $this->urlShortener->shorten($originalUrl);

        if (!$result['success']) {
            return $this->json(['error' => $result['error'] ?? 'Failed to shorten URL'], 500);
        }

        return $this->json([
            'success' => true,
            'short_url' => $this->request->baseUrl() . '/' . $result['short_code'],
            'short_code' => $result['short_code'],
            'existing' => $result['existing'],
        ], $result['existing'] ? 200 : 201);
    }
}
