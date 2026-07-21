<?php

use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The sidebar hides any nav item whose required permission the user lacks
 * (AppSidebar.vue filters client-side on auth.permissions). These assert the
 * permission CONTRACT that drives it: an item shows iff the user holds ANY of
 * its required permissions. Customers/Transactions OR over the .all/.own scope
 * variants; Users/Roles need the meta-permissions.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// Nav item → permission(s) that reveal it (mirror of AppSidebar.vue's allNavItems).
function navVisibleFor(string $role): array
{
    $contract = [
        'Dashboard' => ['dashboard.view'],
        'Customers' => ['customer.view.all', 'customer.view.team', 'customer.view.own', 'customer.view.assigned'],
        'Products' => ['product.view'],
        // L2-B: the Resellers nav item is gone (reseller UI deprecated).
        'Transactions' => ['transaction.view.all', 'transaction.view.own'],
        // H6: the read-only hierarchy overview — every role WITH a team position
        // holds team.view; admin (no team) does not.
        'Tim Saya' => ['team.view'],
        // H5: the support-assignment area — user.assign is held by sales alone.
        'Support Saya' => ['user.assign'],
        'Roles' => ['role.manage'],
        'Users' => ['user.view'],
    ];

    $held = userWithRole($role)->getAllPermissions()->pluck('name');

    return collect($contract)
        ->filter(fn (array $needed) => collect($needed)->some(fn ($p) => $held->contains($p)))
        ->keys()
        ->all();
}

it('shows admin only Dashboard, Roles and Users (no data nav)', function () {
    expect(navVisibleFor('admin'))->toBe(['Dashboard', 'Roles', 'Users']);
});

it('shows sales the data nav plus team + support assignment, but hides Users and Roles', function () {
    expect(navVisibleFor('sales'))
        ->toBe(['Dashboard', 'Customers', 'Products', 'Transactions', 'Tim Saya', 'Support Saya']);
});

it('hides Users and Roles from the manager (no user/role meta perms)', function () {
    expect(navVisibleFor('supervisor'))
        ->toBe(['Dashboard', 'Customers', 'Products', 'Transactions', 'Tim Saya']);
});

it('shows cs customers and team but no Transactions, Users or Roles', function () {
    expect(navVisibleFor('cs'))
        ->toBe(['Dashboard', 'Customers', 'Products', 'Tim Saya']);
});

it('no longer lists a Resellers nav item in the sidebar (L2-B)', function () {
    // Source guard so the contract mirror above can never silently drift back:
    // the reseller UI is gone, so the sidebar must not offer the route.
    expect(file_get_contents(resource_path('js/components/AppSidebar.vue')))
        ->not->toContain('/resellers')
        ->not->toContain("title: 'Resellers'");
});

it('shares the effective permission list to the frontend for nav gating', function () {
    $sales = userWithRole('sales');

    $this->actingAs($sales)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('customer.view.own')
                && ! collect($permissions)->contains('user.view')
                && ! collect($permissions)->contains('role.manage')));
});
