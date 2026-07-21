<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B2 — transaction/money scoping (DESIGN_RBAC.md §4.2/§4.3/§4.4). A Sales user
 * sees/edits only their own customers' transactions and their amounts; money is
 * OMITTED (not null) for users without a money permission; dashboard revenue is
 * gated by revenue.view.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * @return array{a: User, b: User, txA: Transaction, txB: Transaction, custA: Customer, custB: Customer}
 */
function scopedTransactions(): array
{
    $a = userWithRole('sales');
    $b = userWithRole('sales');
    $custA = Customer::factory()->createdBy($a)->create();
    $custB = Customer::factory()->createdBy($b)->create();

    return [
        'a' => $a,
        'b' => $b,
        'custA' => $custA,
        'custB' => $custB,
        'txA' => Transaction::factory()->forCustomer($custA)->create(['amount' => 750_000]),
        'txB' => Transaction::factory()->forCustomer($custB)->create(['amount' => 900_000]),
    ];
}

// ---------------------------------------------------------------------------
// Transaction isolation
// ---------------------------------------------------------------------------

it('lists only the sales user\'s own transactions', function () {
    ['a' => $a, 'txA' => $txA] = scopedTransactions();

    $this->actingAs($a)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $txA->id)
            ->where('transactions.data.0.amount', '750000.00')); // own amount visible
});

it('forbids a sales user from editing another rep\'s transaction (write IDOR)', function () {
    ['a' => $a, 'custB' => $custB, 'txA' => $txA, 'txB' => $txB] = scopedTransactions();

    $this->actingAs($a)
        ->put(route('transactions.update', $txB), [
            'customer_id' => $custB->id,
            'product_id' => $txB->product_id,
            'reseller_id' => $txB->reseller_id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertForbidden();

    // ...but may edit their own.
    $this->actingAs($a)
        ->put(route('transactions.update', $txA), [
            'customer_id' => $txA->customer_id,
            'product_id' => $txA->product_id,
            'reseller_id' => $txA->reseller_id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));
});

it('lets a sales user create a transaction only for their own customer', function () {
    ['a' => $a, 'custA' => $custA, 'custB' => $custB] = scopedTransactions();
    $product = Product::factory()->create();

    // Another rep's customer → rejected at validation.
    $this->actingAs($a)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'customer_id' => $custB->id,
            'product_id' => $product->id,
            'reseller_id' => $custB->reseller_id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('customer_id');

    // Their own customer → allowed.
    $this->actingAs($a)
        ->post(route('transactions.store'), [
            'customer_id' => $custA->id,
            'product_id' => $product->id,
            'reseller_id' => $custA->reseller_id,
            'purchased_at' => now()->toDateString(),
            'amount' => 1_000_000,
        ])
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('transactions', ['customer_id' => $custA->id, 'amount' => '1000000.00']);
});

it('scopes the customer dropdown on the create form to the sales user\'s book', function () {
    ['a' => $a, 'custA' => $custA] = scopedTransactions();

    $this->actingAs($a)
        ->get(route('transactions.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers', 1)
            ->where('customers.0.id', $custA->id));
});

// ---------------------------------------------------------------------------
// Money OMIT (not null) — partial visibility
// ---------------------------------------------------------------------------

it('omits amount and totalSpend entirely for a user without a money permission', function () {
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 500_000]);
    // H3: maintenance reaches the customer via assignment to its owning sales.
    [$maintenance] = supportAssignedToOwnerOf($customer, 'maintenance'); // no transaction perms

    // B3: money-less viewers get the `purchasedProducts` projection instead of
    // `transactions` — the amount-carrying array is absent entirely, so there is
    // nothing to peek (omitted, not null).
    $this->actingAs($maintenance)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('purchasedProducts.0')             // still sees the product line...
            ->missing('transactions')                // ...but never the money array...
            ->missing('purchasedProducts.0.amount')  // ...and no price on the projection.
            ->missing('stats.totalSpend'));
});

it('includes amount and totalSpend for a user with a money permission', function () {
    // A manager sees the customer via view.own here; the subject is the money view.
    $supervisor = userWithRole('supervisor');
    $customer = Customer::factory()->create(['assigned_to' => $supervisor->id]);
    Transaction::factory()->forCustomer($customer)->create(['amount' => 500_000]);

    $this->actingAs($supervisor)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.0.amount', '500000.00')
            ->where('stats.totalSpend', fn ($value) => (float) $value === 500000.0));
});

// ---------------------------------------------------------------------------
// Dashboard revenue gating
// ---------------------------------------------------------------------------

/**
 * H7d split these two apart, so they are asserted separately now:
 *
 *   $hasMoneyBand — the SCOPED revenue band (revenue.view + any transaction view
 *                   tier). Its figure is Transaction::visibleTo, so a manager gets
 *                   their team and a rep gets their own book — never the org.
 *   $hasSalesRevenue — the per-Sales revenue widget (L2-B, replaces the reseller
 *                   breakdown). It ranks MULTIPLE reps, so it needs a SPANNING view
 *                   (view.all or view.team) on top of the money gate: a manager gets
 *                   it (team), a lone rep does not, CS/maintenance/admin never do.
 */
it('gates the dashboard money band per tier, and the per-Sales revenue widget by span',
    function (string $role, bool $hasMoneyBand, bool $hasSalesRevenue) {
        Transaction::factory()->create(['amount' => 1_000_000]);

        $this->actingAs(userWithRole($role))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($hasMoneyBand, $hasSalesRevenue) {
                $hasMoneyBand ? $page->has('revenue.total') : $page->missing('revenue');
                $hasSalesRevenue
                    ? $page->has('revenueBySales')
                    : $page->missing('revenueBySales');
            });
    })->with([
        // Manager: team money band AND the team-scoped per-Sales widget (L2-B).
        ['supervisor', true, true],
        // Rep: own money band, but view.own is not a spanning view → no rep ranking.
        ['sales', true, false],
        ['cs', false, false],          // B3 removed money from cs entirely
        ['maintenance', false, false], // read-only, no transaction tier
        ['admin', false, false],       // B4 stripped all data/money access
    ]);

it('shows dashboard revenue to a global viewer holding revenue.view', function () {
    Transaction::factory()->create(['amount' => 1_000_000]);

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('revenue.total')->has('revenueBySales'));
});
