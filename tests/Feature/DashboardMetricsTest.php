<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Phase 1 of the dashboard redesign: the extra DashboardController props
 * (month deltas, warranty breakdown, recent activity, expiring watchlist,
 * top resellers). Mirrors DashboardStatsTest — seed roles, act as admin,
 * assert the derived props via Inertia.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('counts new customers and transactions in the current month', function () {
    $reseller = Reseller::factory()->create();
    $product = Product::factory()->create();

    // Two customers created this month, one created last month.
    $current = Customer::factory()->count(2)->create(['reseller_id' => $reseller->id]);
    Customer::factory()->create([
        'reseller_id' => $reseller->id,
        'created_at' => now()->subMonthNoOverflow(),
    ]);

    // Two transactions purchased this month, one purchased last month.
    Transaction::factory()->count(2)->create([
        'customer_id' => $current[0]->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => now(),
    ]);
    Transaction::factory()->create([
        'customer_id' => $current[0]->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subMonthNoOverflow(),
    ]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.customers', 3)
            ->where('stats.customersThisMonth', 2)
            ->where('stats.transactions', 3)
            ->where('stats.transactionsThisMonth', 2));
});

it('splits transactions into active, expired, and no-warranty buckets', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);

    $warrantied = Product::factory()->create(['warranty_months' => 12]);
    $noWarranty = Product::factory()->create(['warranty_months' => 0]);

    $make = fn (Product $product, $purchasedAt) => Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => $purchasedAt,
    ]);

    $make($warrantied, now()->subMonths(3));  // active
    $make($warrantied, now()->subYears(2));   // expired
    $make($noWarranty, now()->subYear());     // none

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('warrantyBreakdown.active', 1)
            ->where('warrantyBreakdown.expired', 1)
            ->where('warrantyBreakdown.none', 1)
            ->where('stats.productsUnderWarranty', 1)); // reuses the active bucket
});

it('lists the six most recent transactions, newest first', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);
    $product = Product::factory()->create(['warranty_months' => 12]);

    // Seven transactions on ascending dates; the newest must lead and only six show.
    foreach (range(1, 7) as $i) {
        Transaction::factory()->create([
            'customer_id' => $customer->id,
            'reseller_id' => $reseller->id,
            'product_id' => $product->id,
            'purchased_at' => now()->subDays(10 - $i)->toDateString(),
        ]);
    }

    $newest = now()->subDays(3)->toDateString();

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTransactions', 6)
            ->where('recentTransactions.0.purchased_at', $newest)
            ->has('recentTransactions.0', fn (Assert $row) => $row
                ->hasAll([
                    'id', 'customer', 'product', 'reseller',
                    'purchased_at', 'warranty_expires_at',
                    'is_under_warranty', 'warranty_months',
                ])));
});

it('surfaces active warranties expiring within 30 days, soonest first', function () {
    $reseller = Reseller::factory()->create();
    $product = Product::factory()->create(['warranty_months' => 12]);

    $soon = Customer::factory()->create(['reseller_id' => $reseller->id, 'name' => 'Segera Berakhir']);
    $later = Customer::factory()->create(['reseller_id' => $reseller->id, 'name' => 'Menyusul']);
    $far = Customer::factory()->create(['reseller_id' => $reseller->id, 'name' => 'Masih Lama']);

    // 12-month warranty expires ~= purchase + 12 months.
    Transaction::factory()->create([ // expires in ~6 days
        'customer_id' => $soon->id, 'reseller_id' => $reseller->id, 'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12)->addDays(6),
    ]);
    Transaction::factory()->create([ // expires in ~25 days
        'customer_id' => $later->id, 'reseller_id' => $reseller->id, 'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12)->addDays(25),
    ]);
    Transaction::factory()->create([ // expires in ~9 months — excluded
        'customer_id' => $far->id, 'reseller_id' => $reseller->id, 'product_id' => $product->id,
        'purchased_at' => now()->subMonths(3),
    ]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expiringSoon', 2)
            ->where('expiringSoon.0.customer', 'Segera Berakhir') // soonest first
            ->where('expiringSoon.1.customer', 'Menyusul')
            ->has('expiringSoon.0', fn (Assert $row) => $row
                ->hasAll(['id', 'customer', 'product', 'warranty_expires_at', 'days_left'])
                ->where('days_left', fn (int $d) => $d >= 0 && $d <= 30)));
});

it('ranks the top resellers by customer count, excluding empty ones', function () {
    $big = Reseller::factory()->create(['name' => 'Reseller Besar']);
    $mid = Reseller::factory()->create(['name' => 'Reseller Sedang']);
    $small = Reseller::factory()->create(['name' => 'Reseller Kecil']);
    Reseller::factory()->create(['name' => 'Reseller Kosong']); // no customers → excluded

    Customer::factory()->count(5)->create(['reseller_id' => $big->id]);
    Customer::factory()->count(3)->create(['reseller_id' => $mid->id]);
    Customer::factory()->count(1)->create(['reseller_id' => $small->id]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('topResellers', 3) // the empty reseller is dropped
            ->where('topResellers.0.name', 'Reseller Besar')
            ->where('topResellers.0.customers_count', 5)
            ->where('topResellers.1.customers_count', 3)
            ->where('topResellers.2.customers_count', 1));
});
