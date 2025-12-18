<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\BotDetectorInterface;
use App\Http\Request;
use App\Http\Response;

class TokenController extends Controller
{
    private BotDetectorInterface $botDetector;

    public function __construct(Request $request, BotDetectorInterface $botDetector)
    {
        parent::__construct($request);
        $this->botDetector = $botDetector;
    }

    public function __invoke(): Response
    {
        if (!$this->request->isMethod('GET')) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }

        $token = $this->botDetector->generateFormToken();

        return $this->json([
            'token' => $token,
        ]);
    }
}
