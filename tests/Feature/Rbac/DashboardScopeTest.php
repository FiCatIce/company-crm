<?php

use App\Enums\InteractionType;
use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Transaction;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B6 — dashboard finalised per role (DESIGN_RBAC.md §4.4). The call feed is scoped
 * per viewer (Sales sees only their own customers' calls); the org-wide detail
 * widgets are hidden from Sales (view.own) entirely — their book lives on the
 * personal "Ringkasan Saya" band.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Call-log isolation — the headline B6 requirement
// ---------------------------------------------------------------------------

it('scopes the dashboard call feed to a sales user\'s own customers', function () {
    $salesA = userWithRole('sales');
    $salesB = userWithRole('sales');

    $mine = Customer::factory()->createdBy($salesA)->create(['name' => 'Customer A']);
    $theirs = Customer::factory()->createdBy($salesB)->create(['name' => 'Customer B']);

    Interaction::factory()->forCustomer($mine)->create(['type' => InteractionType::Call]);
    Interaction::factory()->forCustomer($theirs)->create(['type' => InteractionType::Call]);

    $this->actingAs($salesA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentCalls', 1)                        // only their own customer's call
            ->where('recentCalls.0.customer.id', $mine->id));
});

it('shows managers the whole org call feed', function () {
    $salesB = userWithRole('sales');
    $a = Customer::factory()->create();
    $b = Customer::factory()->createdBy($salesB)->create();

    Interaction::factory()->forCustomer($a)->create(['type' => InteractionType::Call]);
    Interaction::factory()->forCustomer($b)->create(['type' => InteractionType::Call]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('recentCalls', 2));
});

it('still gives cs the full org call feed (interaction.view.all)', function () {
    $customer = Customer::factory()->create();
    Interaction::factory()->forCustomer($customer)->create(['type' => InteractionType::Call]);

    $this->actingAs(userWithRole('cs'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('recentCalls', 1));
});

// ---------------------------------------------------------------------------
// Per-role widget matrix
// ---------------------------------------------------------------------------

it('hides the org-wide detail widgets from a sales user but keeps their call feed', function () {
    $sales = userWithRole('sales');
    $mine = Customer::factory()->createdBy($sales)->create();
    Transaction::factory()->forCustomer($mine)->create(['amount' => 250_000]);
    Interaction::factory()->forCustomer($mine)->create(['type' => InteractionType::Call]);

    $this->actingAs($sales)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Personal band + own (scoped) call feed are present...
            ->has('me')
            ->has('recentCalls')
            // ...but the ENTIRE org-wide aggregate band is gone — a view.own viewer
            // must never read global totals (no Total Customer/Transaksi/Garansi).
            ->missing('stats')
            ->missing('trend')
            ->missing('warrantyBreakdown')
            ->missing('recentTransactions')
            ->missing('expiringSoon')
            ->missing('topResellers')
            ->missing('topResellersByRevenue'));
});

it('gives the manager every widget including revenue', function () {
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 250_000]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTransactions')
            ->has('expiringSoon')
            ->has('topResellers')
            ->has('stats.revenue')
            ->has('topResellersByRevenue')
            ->has('recentCalls'));
});

// ---------------------------------------------------------------------------
// Personal band = OWNERSHIP (created_by OR assigned_to), not assigned_to alone.
// Regression guard for the bug where a Sales rep's dashboard read 0 for customers
// they entered (created_by) but were never assigned.
// ---------------------------------------------------------------------------

it('counts a sales rep\'s created-by customers in the personal band even when unassigned', function () {
    $sales = userWithRole('sales');

    // The exact shape CustomerController::store produces for a Sales rep: entered
    // by them (created_by), never "assigned" (assigned_to null).
    Customer::factory()->count(3)->createdBy($sales)->create(['assigned_to' => null]);
    // Assigned to them but entered by someone else — also counts.
    Customer::factory()->create(['assigned_to' => $sales->id]);
    // Neither created nor assigned to them — must NOT count.
    Customer::factory()->create();

    $this->actingAs($sales)
        ->get(route('dashboard'))
        ->assertOk()
        // 3 created-by + 1 assigned = 4. Pre-fix (assigned_to only) this read 1.
        ->assertInertia(fn (Assert $page) => $page->where('me.myCustomers', 4));
});

it('keeps a manager\'s personal band to their own book, not the whole org', function () {
    $manager = userWithRole('supervisor');

    Customer::factory()->count(5)->create();                        // org customers, not theirs
    Customer::factory()->count(2)->createdBy($manager)->create();   // their own book

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('me.myCustomers', 2)     // personal band = own only (ownership, not visibility)
            ->where('stats.customers', 7));  // org aggregate still counts all 7
});

// ---------------------------------------------------------------------------
// P2 — revenue is org money: revenue.view alone (without transaction view) must
// never surface it.
// ---------------------------------------------------------------------------

it('never shows revenue to a user holding revenue.view but no transaction view', function () {
    // A permission combo no preset produces — revenue.view without any transaction
    // view. Assigned directly to prove the gate, not the preset.
    $user = userWithRole('supervisor');
    $user->syncPermissions([
        PermissionName::DashboardView->value,
        PermissionName::DashboardStatsAggregate->value,
        PermissionName::CustomerViewAll->value,
        PermissionName::RevenueView->value, // money perm, but NO transaction.view.*
    ]);

    Customer::factory()->create();
    Transaction::factory()->forCustomer(Customer::factory()->create())->create(['amount' => 500_000]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stats')                    // still an aggregate viewer...
            ->missing('stats.revenue')        // ...but revenue is gated off
            ->missing('topResellersByRevenue'));
});
