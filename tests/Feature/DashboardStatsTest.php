<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('shows summary stats derived from the domain data', function () {
    $activeReseller = Reseller::factory()->create();
    Reseller::factory()->create(); // inactive: no customers or transactions

    $customers = Customer::factory()->count(3)->create(['reseller_id' => $activeReseller->id]);

    $under = Product::factory()->create(['warranty_months' => 12]);
    $expired = Product::factory()->create(['warranty_months' => 6]);

    Transaction::factory()->create([
        'customer_id' => $customers[0]->id,
        'reseller_id' => $activeReseller->id,
        'product_id' => $under->id,
        'purchased_at' => now()->subMonths(3),
    ]);
    Transaction::factory()->create([
        'customer_id' => $customers[1]->id,
        'reseller_id' => $activeReseller->id,
        'product_id' => $expired->id,
        'purchased_at' => now()->subYear(),
    ]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.customers', 3)
            ->where('stats.transactions', 2)
            ->where('stats.activeWarranties', 1)
            ->where('stats.activeResellers', 1));
});

it('builds a 12-month transaction trend and counts the current month', function () {
    Transaction::factory()->create(['purchased_at' => now()]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('trend', 12)
            ->where('trend.11.count', 1)
            ->has('trend.0', fn (Assert $point) => $point->hasAll(['month', 'label', 'count'])));
});
