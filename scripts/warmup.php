<?php

/*
| Warm-up hit for the dev-share server (launched detached by serve-tunnel.php).
| Waits for the port to open, then requests the health route once so the
| long-lived server process compiles the framework before the first real
| visitor arrives (opcache is off under the CLI SAPI, so a cold process would
| otherwise recompile the whole vendor tree on that first request).
|
| Kept as its own file rather than an inline `php -r` string: Windows
| escapeshellarg() mangles inner double quotes, which corrupts inline code.
*/

$port = 8000;

for ($i = 0; $i < 90; $i++) {
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if ($conn !== false) {
        fclose($conn);
        @file_get_contents("http://127.0.0.1:{$port}/up");
        break;
    }
    sleep(1);
}
