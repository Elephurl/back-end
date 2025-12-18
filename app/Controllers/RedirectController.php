<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\UrlShortenerInterface;
use App\Http\Request;
use App\Http\Response;

class RedirectController extends Controller
{
    private UrlShortenerInterface $urlShortener;

    public function __construct(Request $request, UrlShortenerInterface $urlShortener)
    {
        parent::__construct($request);
        $this->urlShortener = $urlShortener;
    }

    public function __invoke(string $shortCode): Response
    {
        $result = $this->urlShortener->resolve($shortCode, [
            'ip_hash' => hash('sha256', $this->request->ip()),
            'user_agent' => $this->request->userAgent(),
            'referer' => $this->request->header('Referer', ''),
        ]);

        if (!$result['success']) {
            return $this->notFound('URL not found');
        }

        return $this->redirect($result['url']);
    }
}
