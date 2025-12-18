<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\UrlShortenerInterface;
use App\Http\Request;
use App\Http\Response;

class StatsController extends Controller
{
    private UrlShortenerInterface $urlShortener;

    public function __construct(Request $request, UrlShortenerInterface $urlShortener)
    {
        parent::__construct($request);
        $this->urlShortener = $urlShortener;
    }

    public function __invoke(): Response
    {
        $shortCode = $this->request->query('code', '');

        if (empty($shortCode)) {
            return $this->json(['error' => 'Missing required parameter: code'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9]{6,10}$/', $shortCode)) {
            return $this->json(['error' => 'Invalid short code format'], 400);
        }

        $result = $this->urlShortener->getStats($shortCode);

        if (!$result['success']) {
            return $this->json(['error' => $result['error']], 404);
        }

        return $this->json($result['data']);
    }
}
