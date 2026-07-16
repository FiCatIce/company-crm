<?php

/*
|--------------------------------------------------------------------------
| Dev-share tunnel server launcher
|--------------------------------------------------------------------------
|
| Runs the built-in server for `composer share:tunnel`. Two jobs beyond a
| plain `artisan serve`:
|
|  1. Exports SHARE_TUNNEL=true so the app forces the https scheme (asset URLs
|     stay https on the tunnel — see AppServiceProvider) and lifts the request
|     time limit (public/index.php).
|  2. Fires a detached warm-up hit once the port is open. opcache is off under
|     the CLI SAPI, so the FIRST request in a fresh server process recompiles
|     the entire vendor tree (~20s on a slow box); every later request reuses
|     the long-lived process (~0.5s). Warming it locally means the first
|     *remote* visitor never eats that cold compile.
|
| PHP_CLI_SERVER_WORKERS is set for completeness but is a no-op on Windows
| (the built-in server can't fork there); it only helps from Linux/WSL/mac.
|
*/

putenv('SHARE_TUNNEL=true');
putenv('PHP_CLI_SERVER_WORKERS=4');

$php = PHP_BINARY;
$host = '0.0.0.0';
$port = 8000;

// Detached warm-up: warmup.php waits for the port, then hits the health route
// (which boots the full framework, providers included) so everything compiles
// in-process. Kept in its own file so no PHP code travels through the shell
// (Windows escapeshellarg mangles inner double quotes).
$warmCmd = escapeshellarg($php).' '.escapeshellarg(__DIR__.'/warmup.php');

if (stripos(PHP_OS, 'WIN') === 0) {
    // `start /B` detaches without opening a window; empty "" is the title arg.
    pclose(popen('start /B "" '.$warmCmd, 'r'));
} else {
    exec($warmCmd.' > /dev/null 2>&1 &');
}

echo PHP_EOL.'>> Warming the server in the background — first remote load will be fast.'.PHP_EOL.PHP_EOL;

// Foreground: the server itself. Blocks until Ctrl+C. --no-reload is required
// for PHP_CLI_SERVER_WORKERS to be honoured (where forking is supported).
passthru(escapeshellarg($php)." artisan serve --host={$host} --port={$port} --no-reload");
