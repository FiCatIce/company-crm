<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Guards that each index page exposes summary stats derived from real domain
 * data (the numbers shown in the header stat cards). Stats reflect the whole
 * dataset and are independent of the page's search/filter query.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('exposes customer summary stats independent of the search filter', function () {
    $resellerA = Reseller::factory()->create();
    Reseller::factory()->count(2)->create(); // three resellers total

    $customers = Customer::factory()->count(3)->create(['reseller_id' => $resellerA->id]);

    $product = Product::factory()->create(['warranty_months' => 12]);

    // customer[0]: two active-warranty transactions — must still count once.
    Transaction::factory()->count(2)->create([
        'customer_id' => $customers[0]->id,
        'reseller_id' => $resellerA->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subMonths(2),
    ]);
    // customer[1]: warranty already expired.
    Transaction::factory()->create([
        'customer_id' => $customers[1]->id,
        'reseller_id' => $resellerA->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subYears(2),
    ]);
    // customer[2]: no transactions.

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.index', ['search' => 'zzz-no-match']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->where('stats.total', 3)
            ->where('stats.underWarranty', 1)
            ->where('stats.resellers', 3));
});

it('exposes product summary stats including average warranty', function () {
    Product::factory()->create(['warranty_months' => 0]);
    Product::factory()->create(['warranty_months' => 12]);
    Product::factory()->create(['warranty_months' => 24]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->where('stats.total', 3)
            ->where('stats.withWarranty', 2)
            ->where('stats.avgWarrantyMonths', 12)); // round((0+12+24)/3)
});

it('exposes reseller summary stats for total, active, and top-level', function () {
    $root = Reseller::factory()->create(['parent_id' => null]);
    $child = Reseller::factory()->create(['parent_id' => $root->id]);
    Reseller::factory()->create(['parent_id' => null]); // second root, inactive

    // Only the child gains a customer, so only it counts as "active".
    Customer::factory()->create(['reseller_id' => $child->id]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('resellers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Resellers/Index')
            ->where('stats.total', 3)
            ->where('stats.active', 1)
            ->where('stats.topLevel', 2));
});

it('exposes transaction summary stats with an active/expired warranty split', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);

    $warrantied = Product::factory()->create(['warranty_months' => 12]);
    $noWarranty = Product::factory()->create(['warranty_months' => 0]);

    // Active warranty.
    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $warrantied->id,
        'purchased_at' => now()->subMonths(3),
    ]);
    // Expired warranty (had one, now past).
    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $warrantied->id,
        'purchased_at' => now()->subYears(2),
    ]);
    // Product sold without warranty — neither active nor expired.
    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $noWarranty->id,
        'purchased_at' => now()->subYear(),
    ]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions/Index')
            ->where('stats.total', 3)
            ->where('stats.underWarranty', 1)
            ->where('stats.expired', 1));
});
