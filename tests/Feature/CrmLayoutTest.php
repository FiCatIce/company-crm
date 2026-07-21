<?php

use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Guards the corporate CRM shell redesign: a top bar (page title + user menu),
 * a decluttered sidebar, and a Customers index that still renders its data
 * contract. UI is presentational, so structure is asserted at the file layer
 * (no JS test runner) plus a render check for the page's Inertia props.
 */

test('the top bar shows the page title and a user menu', function () {
    $header = file_get_contents(
        resource_path('js/components/AppSidebarHeader.vue'),
    );

    expect($header)
        ->toContain('AppUserMenu') // user menu lives in the top bar
        ->toContain('pageTitle'); // derived page title
});

test('the app user menu exposes the account dropdown', function () {
    $menu = file_get_contents(resource_path('js/components/AppUserMenu.vue'));

    expect($menu)
        ->toContain('UserMenuContent')
        ->toContain('data-test="user-menu-button"');
});

test('the sidebar is decluttered of starter-kit chrome', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebar)
        ->not->toContain('NavFooter') // no bottom footer links
        ->not->toContain('NavUser') // user menu moved to the top bar
        ->not->toContain('vue-starter-kit') // Laravel "Repository" link
        ->not->toContain('laravel.com/docs'); // Laravel "Documentation" link
});

test('the replaced starter-kit nav components are removed', function () {
    expect(file_exists(resource_path('js/components/NavUser.vue')))->toBeFalse();
    expect(
        file_exists(resource_path('js/components/NavFooter.vue')),
    )->toBeFalse();
});

test('the customers index renders its data contract for an authorized user', function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(userWithRole('supervisor'));
    $this->withoutVite();

    $this->get('/customers')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Customers/Index')
                ->has('customers')
                ->has('customers.data')
                ->has('filters')
                ->has('can'),
        );
});
