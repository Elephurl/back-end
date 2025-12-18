<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application;

// Error reporting for local dev
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'local' ? '1' : '0');

$app = new Application();
$app->run();
