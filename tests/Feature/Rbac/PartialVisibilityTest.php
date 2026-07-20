<?php

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B3 — partial visibility (DESIGN_RBAC.md §3.3/§4.3, batch B3). CS and Maintenance
 * see every customer's profile + which products they bought, manage the whole call
 * log, but NEVER money: no transaction module, no amount, no revenue. Maintenance
 * is additionally read-only on customers (D8); CS may still create/update them.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Purchased-products projection (product + warranty, no money)
// ---------------------------------------------------------------------------

it('shows the money-less roles a purchased-products projection without any price', function (string $role) {
    $product = Product::factory()->create(['name' => 'Router X1', 'warranty_months' => 12]);
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create([
        'product_id' => $product->id,
        'purchased_at' => now()->subMonth(),
        'amount' => 750_000,
    ]);

    // H3: a scoped role reaches the customer only through the hierarchy — assign it
    // to a sales rep who owns this customer.
    [$viewer] = supportAssignedToOwnerOf($customer, $role);

    $this->actingAs($viewer)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The projection carries product + date + warranty...
            ->where('purchasedProducts.0.product', 'Router X1')
            ->where('purchasedProducts.0.warranty_months', 12)
            ->where('purchasedProducts.0.is_under_warranty', true)
            ->has('purchasedProducts.0.warranty_expires_at')
            // ...but no price anywhere, and the money-carrying array is absent.
            ->missing('purchasedProducts.0.amount')
            ->missing('transactions')
            ->missing('stats.totalSpend'));
})->with(['cs', 'maintenance']);

it('gives money viewers the full transactions array with amount (not the projection)', function () {
    // A manager sees the customer via view.own (their own book) — the point here is
    // the money projection, not the hierarchy path.
    $supervisor = userWithRole('supervisor');
    $customer = Customer::factory()->create(['assigned_to' => $supervisor->id]);
    Transaction::factory()->forCustomer($customer)->create(['amount' => 500_000]);

    $this->actingAs($supervisor)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.0.amount', '500000.00')
            ->missing('purchasedProducts')
            ->where('stats.totalSpend', fn ($value) => (float) $value === 500000.0));
});

// ---------------------------------------------------------------------------
// Money surfaces are off-limits
// ---------------------------------------------------------------------------

it('forbids the money-less roles from the transaction index', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('transactions.index'))
        ->assertForbidden();
})->with(['cs', 'maintenance']);

it('hides dashboard revenue from the money-less roles', function (string $role) {
    Transaction::factory()->create(['amount' => 1_000_000]);

    $this->actingAs(userWithRole($role))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('stats.revenue')
            ->missing('topResellersByRevenue'));
})->with(['cs', 'maintenance']);

// ---------------------------------------------------------------------------
// Call log — CS and Maintenance see everyone's interactions (interaction.view.all)
// ---------------------------------------------------------------------------

it('lets the money-less roles see call logs authored by other agents', function (string $role) {
    $otherAgent = User::factory()->create(['name' => 'Agen Lain']);
    $customer = Customer::factory()->create();
    Interaction::factory()->forCustomer($customer)->create([
        'user_id' => $otherAgent->id,
        'subject' => 'Telepon follow-up',
    ]);

    // H3: reach the customer (and thus its call log) through the hierarchy.
    [$viewer] = supportAssignedToOwnerOf($customer, $role);

    $this->actingAs($viewer)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('timeline.data', 1)
            ->where('timeline.data.0.subject', 'Telepon follow-up'));
})->with(['cs', 'maintenance']);

// ---------------------------------------------------------------------------
// Write access: CS can edit customers, Maintenance is read-only (D8)
// ---------------------------------------------------------------------------

it('lets cs create and update customers', function () {
    $reseller = Reseller::factory()->create();
    // The SAME agent throughout: each userWithRole() call mints a new user, and
    // post-H7 a CS agent may only edit customers it can see — its own entries
    // included. Two different agents would (correctly) fail the second write.
    $cs = userWithRole('cs');

    $this->actingAs($cs)
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Pelanggan CS',
        ])
        ->assertRedirect(route('customers.index'));

    $customer = Customer::where('name', 'Pelanggan CS')->sole();

    $this->actingAs($cs)
        ->put(route('customers.update', $customer), [
            'reseller_id' => $reseller->id,
            'name' => 'Pelanggan CS (diperbarui)',
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Pelanggan CS (diperbarui)']);
});

it('keeps maintenance read-only on customers', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);
    // H3: maintenance reaches the customer via assignment to its owning sales.
    [$maintenance] = supportAssignedToOwnerOf($customer, 'maintenance');

    // Can view the 360 page...
    $this->actingAs($maintenance)
        ->get(route('customers.show', $customer))
        ->assertOk();

    // ...but cannot create, update, or delete.
    $this->actingAs($maintenance)
        ->post(route('customers.store'), ['reseller_id' => $reseller->id, 'name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($maintenance)
        ->put(route('customers.update', $customer), ['reseller_id' => $reseller->id, 'name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($maintenance)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();
});
