<?php

use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Transaction;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B4 — the admin flip (DESIGN_RBAC.md §3.3/§4.4, decision D2). Admin is a system
 * role: user management + AGGREGATE dashboard stats + the call log, but NO access
 * to customer/transaction/product/reseller detail or money. Authorization is by
 * permission only — there is deliberately NO Gate::before admin bypass.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Data routes are off-limits to admin
// ---------------------------------------------------------------------------

it('forbids admin from every data area', function (string $routeName) {
    $this->actingAs(userWithRole('admin'))
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'customers.index',
    'transactions.index',
    'products.index',
    'resellers.index',
]);

it('forbids admin from opening a specific customer', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->get(route('customers.show', $customer))
        ->assertForbidden();
});

// The manager is unaffected — proves the lockdown is admin-specific, not global.
it('still lets the manager reach the customer index', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.index'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// System access is retained
// ---------------------------------------------------------------------------

it('still lets admin manage users', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Users/Index'));
});

// ---------------------------------------------------------------------------
// Dashboard: aggregates + call log only, every detail widget hidden
// ---------------------------------------------------------------------------

it('gives admin only the customer-count aggregate + call log, never transaction numbers', function () {
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 500_000]);
    Interaction::factory()->forCustomer($customer)->create(['type' => InteractionType::Call]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            // The aggregate band (customer count + active-sales headcount) + the
            // call log are present...
            ->has('stats.customers')
            ->has('stats.activeSales')
            ->has('recentCalls')
            // ...never transaction/warranty numbers (admin holds no transaction/
            // customer data permission), no trend, no detail widgets, no money, and
            // no per-Sales widgets (admin has no spanning customer view).
            ->missing('stats.transactions')
            ->missing('warrantyBreakdown')
            ->missing('trend')
            ->missing('recentTransactions')
            ->missing('expiringSoon')
            ->missing('topSales')
            ->missing('salesScope')
            ->missing('stats.revenue')
            ->missing('revenueBySales'));
});

// ---------------------------------------------------------------------------
// No Gate::before admin bypass — meta permissions never imply data access
// ---------------------------------------------------------------------------

it('does not let admin permissions imply any data access (no bypass)', function () {
    $admin = userWithRole('admin');

    // Admin holds the powerful meta grants...
    expect($admin->can('permission.assign'))->toBeTrue()
        ->and($admin->can('role.assign'))->toBeTrue()
        // ...yet they grant ZERO data access — authorization is per-permission.
        ->and($admin->can('viewAny', Customer::class))->toBeFalse()
        ->and($admin->can('viewAny', Transaction::class))->toBeFalse()
        ->and($admin->can('customer.view.all'))->toBeFalse()
        ->and($admin->can('revenue.view'))->toBeFalse();
});
