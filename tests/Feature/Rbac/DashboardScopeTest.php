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

it('rolls a manager\'s call feed up to their team, not other teams', function () {
    $salesA = userWithRole('sales');
    $manager = managerOverTeamOf($salesA);
    $mine = Customer::factory()->createdBy($salesA)->create();
    $offTeam = Customer::factory()->createdBy(userWithRole('sales'))->create();

    Interaction::factory()->forCustomer($mine)->create(['type' => InteractionType::Call]);
    Interaction::factory()->forCustomer($offTeam)->create(['type' => InteractionType::Call]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentCalls', 1)                         // only the team's call
            ->where('recentCalls.0.customer.id', $mine->id));
});

it('scopes cs\'s call feed to the sales who assigned them', function () {
    $sales = userWithRole('sales');
    $seen = Customer::factory()->createdBy($sales)->create();
    $cs = userWithRole('cs');
    $sales->assignees()->attach($cs->id);
    $offCustomer = Customer::factory()->create(); // owned by nobody cs is assigned to

    Interaction::factory()->forCustomer($seen)->create(['type' => InteractionType::Call]);
    Interaction::factory()->forCustomer($offCustomer)->create(['type' => InteractionType::Call]);

    $this->actingAs($cs)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentCalls', 1)
            ->where('recentCalls.0.customer.id', $seen->id));
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

it('gives a global viewer every widget including revenue', function () {
    // Post-H3 the org aggregate band + revenue serve a GLOBAL viewer (view.all),
    // not the now-team-scoped manager.
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 250_000]);

    $this->actingAs(userWithGlobalView())
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

it('rolls a manager\'s personal band up to their whole team, never the org', function () {
    $salesA = userWithRole('sales');
    $manager = managerOverTeamOf($salesA);

    Customer::factory()->count(5)->create();                       // off-team, invisible
    Customer::factory()->count(2)->createdBy($salesA)->create();   // a team member's book
    Customer::factory()->createdBy($manager)->create();            // the manager's own

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            // Team roll-up: 2 (sales A) + 1 (own) = 3; the 5 off-team stay invisible.
            ->where('me.myCustomers', 3)
            ->missing('stats'));            // scoped viewer → NO org aggregate band
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
