<?php

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A valid set of foreign keys for a transaction payload. Since L2-A, reseller_id is
 * no longer part of it — new transactions carry no reseller.
 *
 * @return array{customer_id: int, product_id: int}
 */
function transactionLinks(): array
{
    return [
        'customer_id' => Customer::factory()->create()->id,
        'product_id' => Product::factory()->create()->id,
    ];
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

it('redirects guests from the transactions index to login', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});

it('forbids authenticated users without an allowed role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('transactions.index'))
        ->assertForbidden();
});

it('allows admin and supervisor to view the index', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Transactions/Index'));
})->with(['supervisor']);
// CS/maintenance denial (B3, money hidden) lives in Rbac/PartialVisibilityTest.

// ---------------------------------------------------------------------------
// Warranty status indicator (the headline requirement)
// ---------------------------------------------------------------------------

it('exposes an active warranty status from the accessors', function () {
    $purchased = now()->subMonths(6)->startOfDay();
    $product = Product::factory()->create(['warranty_months' => 12]);
    $customer = Customer::factory()->create();

    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $customer->reseller_id,
        'product_id' => $product->id,
        'purchased_at' => $purchased->toDateString(),
    ]);

    $this->actingAs(userWithGlobalView())
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Index')
            ->where('transactions.data.0.is_under_warranty', true)
            ->where('transactions.data.0.warranty_months', 12)
            ->where(
                'transactions.data.0.warranty_expires_at',
                $purchased->copy()->addMonths(12)->toDateString(),
            ));
});

it('exposes an expired warranty status from the accessors', function () {
    $product = Product::factory()->create(['warranty_months' => 6]);
    $customer = Customer::factory()->create();

    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $customer->reseller_id,
        'product_id' => $product->id,
        'purchased_at' => now()->subYear()->toDateString(),
    ]);

    $this->actingAs(userWithGlobalView())
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.data.0.is_under_warranty', false));
});

// ---------------------------------------------------------------------------
// Create / store
// ---------------------------------------------------------------------------

it('opens the create page with customer and product options', function () {
    Customer::factory()->create();
    Product::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Create')
            ->has('customers')
            ->has('products'));
});

it('stores a transaction and redirects with a success flash', function () {
    $links = transactionLinks();

    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            ...$links,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    // Stored without a reseller (L2-A) — the column lands null on new rows.
    $this->assertDatabaseHas('transactions', [...$links, 'reseller_id' => null]);
});

it('promotes a lead customer to active on their first transaction', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Lead]);

    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            'customer_id' => $customer->id,
            'product_id' => Product::factory()->create()->id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

it('does not change a non-lead customer status on a transaction', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Churned]);

    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            'customer_id' => $customer->id,
            'product_id' => Product::factory()->create()->id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    expect($customer->fresh()->status)->toBe(CustomerStatus::Churned);
});

it('validates that all links and the purchase date are required', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [])
        ->assertSessionHasErrors(['customer_id', 'product_id', 'purchased_at']);
    // reseller_id is no longer a required link (L2-A).
});

it('rejects non-existent linked records', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'customer_id' => 999999,
            'product_id' => 999999,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['customer_id', 'product_id']);
});

// The "reseller must own the customer" validation was retired with L2-A: reseller_id
// is no longer accepted, so there is nothing left to mismatch.

it('rejects a future purchase date', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            ...transactionLinks(),
            'purchased_at' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('purchased_at');
});

it('forbids users without a role from storing', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('transactions.store'), [
            ...transactionLinks(),
            'purchased_at' => now()->toDateString(),
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Edit / update
// ---------------------------------------------------------------------------

it('shows the edit page with the transaction loaded', function () {
    $transaction = Transaction::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Edit')
            ->where('transaction.id', $transaction->id)
            ->where('transaction.customer_id', $transaction->customer_id)
            ->has('products'));
});

it('updates a transaction and redirects with a success flash', function () {
    $transaction = Transaction::factory()->create();
    $newProduct = Product::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->put(route('transactions.update', $transaction), [
            'customer_id' => $transaction->customer_id,
            'product_id' => $newProduct->id,
            'reseller_id' => $transaction->reseller_id,
            'purchased_at' => now()->subMonth()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'product_id' => $newProduct->id,
    ]);
});

it('validates when updating', function () {
    $transaction = Transaction::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->from(route('transactions.edit', $transaction))
        ->put(route('transactions.update', $transaction), [
            'customer_id' => $transaction->customer_id,
            'product_id' => $transaction->product_id,
            'reseller_id' => $transaction->reseller_id,
            'purchased_at' => '',
        ])
        ->assertSessionHasErrors('purchased_at');
});

// ---------------------------------------------------------------------------
// Delete (authorization matrix)
// ---------------------------------------------------------------------------

it('lets admins and supervisors delete a transaction', function (string $role) {
    $transaction = Transaction::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
})->with(['supervisor']);

it('forbids cs from deleting a transaction', function () {
    $transaction = Transaction::factory()->create();

    $this->actingAs(userWithRole('cs'))
        ->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
});

// ---------------------------------------------------------------------------
// Search / pagination
// ---------------------------------------------------------------------------

it('filters the index by customer name', function () {
    $match = Customer::factory()->create(['name' => 'Zebra Buyer']);
    $other = Customer::factory()->create(['name' => 'Common Buyer']);
    Transaction::factory()->create(['customer_id' => $match->id, 'reseller_id' => $match->reseller_id]);
    Transaction::factory()->create(['customer_id' => $other->id, 'reseller_id' => $other->reseller_id]);

    $this->actingAs(userWithGlobalView())
        ->get(route('transactions.index', ['search' => 'Zebra']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.customer', 'Zebra Buyer'));
});

it('paginates the index at 10 per page', function () {
    Transaction::factory()->count(15)->create();

    $this->actingAs(userWithGlobalView())
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 10)
            ->where('transactions.total', 15));
});

// ---------------------------------------------------------------------------
// Amount / revenue value
// ---------------------------------------------------------------------------

it('stores the sale amount', function () {
    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            ...transactionLinks(),
            'purchased_at' => now()->toDateString(),
            'amount' => 1500000,
        ])
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('transactions', ['amount' => '1500000.00']);
});

it('stores a transaction without an amount (null)', function () {
    $links = transactionLinks();

    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            ...$links,
            'purchased_at' => now()->toDateString(),
            'amount' => null,
        ])
        ->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('transactions', [...$links, 'amount' => null]);
});

it('rejects a negative or non-numeric amount', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            ...transactionLinks(),
            'purchased_at' => now()->toDateString(),
            'amount' => -5,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs(userWithRole('supervisor'))
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            ...transactionLinks(),
            'purchased_at' => now()->toDateString(),
            'amount' => 'gratis',
        ])
        ->assertSessionHasErrors('amount');
});

it('updates and clears the amount', function () {
    $transaction = Transaction::factory()->create(['amount' => 2000000]);

    $this->actingAs(userWithGlobalView())
        ->put(route('transactions.update', $transaction), [
            'customer_id' => $transaction->customer_id,
            'product_id' => $transaction->product_id,
            'reseller_id' => $transaction->reseller_id,
            'purchased_at' => $transaction->purchased_at->toDateString(),
            'amount' => null,
        ])
        ->assertRedirect(route('transactions.index'));

    expect($transaction->fresh()->amount)->toBeNull();
});

it('exposes the amount on each index row', function () {
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create(['amount' => 750000]);

    $this->actingAs(userWithGlobalView())
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.data.0.amount', '750000.00'));
});
