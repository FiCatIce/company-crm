<?php

use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the application name is branded as Company CRM', function () {
    expect(config('app.name'))->toBe('Company CRM');
});

test('the root document title reflects the Company CRM brand', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<title>Company CRM</title>', false);
});

test('the shared Inertia name prop is Company CRM', function () {
    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('name', 'Company CRM'));
});

test('a lingering Vite hot file cannot leak dev-server SSR into the suite', function () {
    /*
     * Regression pin. With `npm run dev` alive, public/hot exists and the
     * Inertia SSR gateway (which reads the hot file on its own — this is
     * NOT covered by withoutVite()) would reroute rendering to the dev
     * server's /__inertia_ssr, turning the <title> into the page-level
     * variant and making suite results depend on whether a dev server
     * happened to be running. Testing forces SSR off (INERTIA_SSR_ENABLED
     * in phpunit.xml) and fakes Vite (TestCase); this test plants a hot
     * file and proves the render stays CSR with zero outbound SSR calls.
     */
    Http::fake();

    $hot = public_path('hot');
    $created = ! file_exists($hot);

    if ($created) {
        file_put_contents($hot, 'http://localhost:5173');
    }

    try {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Company CRM</title>', false);

        Http::assertNothingSent(); // the SSR gateway must not even try
    } finally {
        if ($created) {
            unlink($hot);
        }
    }
});

test('the starter-kit branding is gone from the sidebar logo', function () {
    $logo = file_get_contents(resource_path('js/components/AppLogo.vue'));

    expect($logo)
        ->toContain('Company CRM')
        ->not->toContain('Laravel Starter Kit');
});
