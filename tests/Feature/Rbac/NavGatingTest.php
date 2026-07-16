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
        'Customers' => ['customer.view.all', 'customer.view.own'],
        'Products' => ['product.view'],
        'Resellers' => ['reseller.view'],
        'Transactions' => ['transaction.view.all', 'transaction.view.own'],
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

it('shows sales the data nav but hides Users and Roles', function () {
    expect(navVisibleFor('sales'))
        ->toBe(['Dashboard', 'Customers', 'Products', 'Resellers', 'Transactions']);
});

it('hides Users and Roles from the manager (no user/role meta perms)', function () {
    expect(navVisibleFor('supervisor'))
        ->toBe(['Dashboard', 'Customers', 'Products', 'Resellers', 'Transactions']);
});

it('shows cs customers but no Transactions, Users or Roles', function () {
    expect(navVisibleFor('cs'))
        ->toBe(['Dashboard', 'Customers', 'Products', 'Resellers']);
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
