<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the application name is branded as Company CRM', function () {
    expect(config('app.name'))->toBe('Company CRM');
});

test('the root document title reflects the Company CRM brand', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertOk()
        ->assertSee('<title>Company CRM</title>', false);
});

test('the shared Inertia name prop is Company CRM', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('name', 'Company CRM'));
});

test('the starter-kit branding is gone from the sidebar logo', function () {
    $logo = file_get_contents(resource_path('js/components/AppLogo.vue'));

    expect($logo)
        ->toContain('Company CRM')
        ->not->toContain('Laravel Starter Kit');
});
