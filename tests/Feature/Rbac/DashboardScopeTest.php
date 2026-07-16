<?php

use App\Enums\InteractionType;
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
