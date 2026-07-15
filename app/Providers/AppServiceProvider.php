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
        // origin is HTTPS but the local dev server speaks plain HTTP. If the
        // tunnel omits X-Forwarded-Proto, asset URLs would render as http:// on
        // an https:// page and get blocked as mixed content — a blank hang.
        // Force https so injected asset URLs are always secure. Gated behind the
        // flag so plain `composer dev` on http://localhost stays untouched.
        if (env('SHARE_TUNNEL')) {
            URL::forceScheme('https');
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
