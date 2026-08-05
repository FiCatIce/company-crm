<?php

namespace Tests;

use App\Support\TeamRoleLabels;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Vite state must be deterministic for every feature test: without
        // this, a lingering public/hot (= `npm run dev` alive) switches the
        // @vite tags to the dev server mid-suite. The Inertia SSR gateway
        // reads public/hot on its own, so SSR is additionally forced off via
        // INERTIA_SSR_ENABLED in phpunit.xml — BrandingTest pins both.
        $this->withoutVite();

        // TeamRoleLabels memoizes the override map in a STATIC for the request;
        // statics outlive the per-test app, so without this reset one test's
        // labels would leak into the next (RefreshDatabase can't help here).
        TeamRoleLabels::flush();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
