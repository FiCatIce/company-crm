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
