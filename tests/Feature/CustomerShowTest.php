<?php

use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
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

it('renders the customer 360 page with the expected props', function () {
    $owner = User::factory()->create(['name' => 'Agen Satu']);
    $reseller = Reseller::factory()->create(['name' => 'Reseller A']);
    $customer = Customer::factory()->create([
        'reseller_id' => $reseller->id,
        'assigned_to' => $owner->id,
        'name' => 'Budi',
    ]);

    Interaction::factory()->forCustomer($customer)->count(3)->create();

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Show')
            ->where('customer.id', $customer->id)
            ->where('customer.name', 'Budi')
            ->where('customer.reseller.name', 'Reseller A')
            ->where('customer.owner.name', 'Agen Satu')
            ->where('stats.interactionsCount', 3)
            ->has('timeline.data', 3)
            ->has('can', fn (Assert $can) => $can->hasAll(['update', 'delete', 'logInteraction'])));
});

it('computes the warranty summary for this customer only', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();

    $warrantied = Product::factory()->create(['warranty_months' => 12]);
    $noWarranty = Product::factory()->create(['warranty_months' => 0]);

    Transaction::factory()->forCustomer($customer)->create(['product_id' => $warrantied->id, 'purchased_at' => now()->subMonths(3)]);
    Transaction::factory()->forCustomer($customer)->create(['product_id' => $warrantied->id, 'purchased_at' => now()->subYears(2)]);
    Transaction::factory()->forCustomer($customer)->create(['product_id' => $noWarranty->id, 'purchased_at' => now()->subMonth()]);
    // Another customer's transaction must not leak in.
    Transaction::factory()->forCustomer($other)->create(['product_id' => $warrantied->id, 'purchased_at' => now()->subMonths(3)]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('warrantySummary.active', 1)
            ->where('warrantySummary.expired', 1)
            ->where('warrantySummary.none', 1)
            ->where('stats.transactionsCount', 3));
});

it('marks manual interactions editable but CTI logs immutable per row', function () {
    $customer = Customer::factory()->create();

    Interaction::factory()->forCustomer($customer)->create([
        'source' => InteractionSource::Manual,
        'occurred_at' => now(),
    ]);
    Interaction::factory()->forCustomer($customer)->create([
        'source' => InteractionSource::Cti,
        'type' => InteractionType::Call,
        'occurred_at' => now()->subHour(),
    ]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline.data.0.source', 'manual')
            ->where('timeline.data.0.can_edit', true)
            ->where('timeline.data.0.can_delete', true)
            ->where('timeline.data.1.source', 'cti')
            ->where('timeline.data.1.can_edit', false)
            ->where('timeline.data.1.can_delete', false));
});

it('lets cs manage their own manual interaction but not the customer', function () {
    $cs = userWithRole('cs');
    $customer = Customer::factory()->create();
    Interaction::factory()->forCustomer($customer)->create([
        'source' => InteractionSource::Manual,
        'user_id' => $cs->id,
    ]);

    $this->actingAs($cs)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.logInteraction', true)
            ->where('can.delete', false)                 // cs cannot delete the customer
            ->where('timeline.data.0.can_edit', true)    // author edits own manual
            ->where('timeline.data.0.can_delete', true)); // author deletes own manual
});

it('denies users without a CRM role', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('customers.show', $customer))
        ->assertForbidden();
});

it('returns 404 for a missing customer', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->get('/customers/999999')
        ->assertNotFound();
});
