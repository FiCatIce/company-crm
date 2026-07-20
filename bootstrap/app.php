<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a tunnel/proxy (VS Code port forwarding, ngrok, …) honour the
        // X-Forwarded-* headers so the app knows it is served over HTTPS on the
        // public host — otherwise redirects/cookies use http and login/CSRF break.
        // Trusting all proxies is fine for a dev tunnel; narrow it in production.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
            // H7b: a deactivation must bite immediately, not at session expiry —
            // this runs before the page is composed and signs the account out.
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
