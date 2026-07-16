<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// When shared through a tunnel (see `composer share:tunnel`) the dev server
// runs under the CLI SAPI with opcache disabled, so the first request in a
// fresh process recompiles the whole vendor tree — ~20s on a slow machine,
// which can trip the built-in server's 30s wall-clock limit (Windows counts
// real time) and fatal before the page renders. Lift the cap in tunnel mode
// only; production (no SHARE_TUNNEL) keeps its normal limits.
if (getenv('SHARE_TUNNEL')) {
    set_time_limit(0);
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
