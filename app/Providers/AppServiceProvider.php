<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        // When shared through a tunnel (see `composer share:tunnel`) the public
        // origin is HTTPS but the dev server speaks plain HTTP. If the tunnel
        // omits X-Forwarded-Proto, asset URLs would render http:// on an https://
        // page and get blocked as mixed content — a blank page. Force https so
        // injected asset URLs stay secure. But the SAME server also answers on
        // http://localhost:8000 (direct, no TLS): forcing https there would point
        // assets at https://localhost, which has no listener → white screen. So
        // decide per request by host, not globally: force https only for the
        // public tunnel host, leave loopback on http.
        // getenv (not env()) so it reads the OS var the serve wrapper exports and
        // is safe when the config cache is warm — matches public/index.php.
        if (getenv('SHARE_TUNNEL') && ! $this->app->runningInConsole()) {
            $host = request()->getHost();

            if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                URL::forceScheme('https');
            }
        }
    }

    /**
     * Named rate limiters. The CTI ingest endpoint is keyed by the token's
     * integration user (falling back to IP) — headroom for call-event bursts.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('cti', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
