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
    $maintenance = userWithRole('maintenance'); // view customers, no transaction perms
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 500_000]);

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
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 500_000]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.0.amount', '500000.00')
            ->where('stats.totalSpend', fn ($value) => (float) $value === 500000.0));
});

// ---------------------------------------------------------------------------
// Dashboard revenue gating
// ---------------------------------------------------------------------------

it('shows dashboard revenue only to users with revenue.view', function (string $role, bool $canSeeRevenue) {
    Transaction::factory()->create(['amount' => 1_000_000]);

    $assertion = $this->actingAs(userWithRole($role))
        ->get(route('dashboard'))
        ->assertOk();

    $assertion->assertInertia(function (Assert $page) use ($canSeeRevenue) {
        $canSeeRevenue
            ? $page->has('stats.revenue')->has('topResellersByRevenue')
            : $page->missing('stats.revenue')->missing('topResellersByRevenue');
    });
})->with([
    ['supervisor', true],
    ['cs', false],   // B3 removed revenue.view from cs (money hidden)
    ['admin', true], // still full in B2 (locked down in B4)
    ['sales', false],
    ['maintenance', false],
]);
