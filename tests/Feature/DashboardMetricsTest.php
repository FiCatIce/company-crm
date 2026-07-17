<?php

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
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

    $this->actingAs(userWithGlobalView())
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

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('warrantyBreakdown.active', 1)
            ->where('warrantyBreakdown.expired', 1)
            ->where('warrantyBreakdown.none', 1)
            ->where('stats.activeWarranties', 1)); // reuses the active bucket
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

    $this->actingAs(userWithGlobalView())
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

    $this->actingAs(userWithGlobalView())
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

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('topResellers', 3) // the empty reseller is dropped
            ->where('topResellers.0.name', 'Reseller Besar')
            ->where('topResellers.0.customers_count', 5)
            ->where('topResellers.1.customers_count', 3)
            ->where('topResellers.2.customers_count', 1));
});

it('keeps a warranty active through the end of its expiry day (endOfDay boundary)', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);
    $product = Product::factory()->create(['warranty_months' => 12]);

    // Purchased exactly 12 months ago → the 12-month warranty expires TODAY.
    $expiresToday = Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12),
    ]);

    // Purchased a year and a day ago → expired yesterday.
    $expiredYesterday = Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12)->subDay(),
    ]);

    expect($expiresToday->warranty_expires_at->toDateString())->toBe(now()->toDateString())
        ->and($expiresToday->is_under_warranty)->toBeTrue()   // active for all of the expiry day
        ->and($expiredYesterday->is_under_warranty)->toBeFalse();
});

it('sums revenue all-time and per month, ignoring null amounts', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);
    $product = Product::factory()->create();

    $tx = fn ($amount, $purchasedAt) => Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'amount' => $amount,
        'purchased_at' => $purchasedAt,
    ]);

    $tx(1_000_000, now());                                        // this month
    $tx(500_000, now());                                          // this month
    $tx(null, now());                                             // null → ignored by SUM
    $tx(2_000_000, now()->subMonthNoOverflow()->startOfMonth()); // last month

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue', 3500000)           // 1M + 500k + 2M (null skipped)
            ->where('stats.revenueThisMonth', 1500000)
            ->where('stats.revenueLastMonth', 2000000));
});

it('ranks the top resellers by revenue, excluding those with none', function () {
    $big = Reseller::factory()->create(['name' => 'Reseller Kaya']);
    $small = Reseller::factory()->create(['name' => 'Reseller Kecil']);
    $none = Reseller::factory()->create(['name' => 'Reseller Nihil']);

    $bigCust = Customer::factory()->create(['reseller_id' => $big->id]);
    $smallCust = Customer::factory()->create(['reseller_id' => $small->id]);
    $noneCust = Customer::factory()->create(['reseller_id' => $none->id]);

    Transaction::factory()->forCustomer($bigCust)->create(['amount' => 5_000_000]);
    Transaction::factory()->forCustomer($bigCust)->create(['amount' => 3_000_000]);
    Transaction::factory()->forCustomer($smallCust)->create(['amount' => 1_000_000]);
    Transaction::factory()->forCustomer($noneCust)->create(['amount' => null]); // no revenue → excluded

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('topResellersByRevenue', 2)
            ->where('topResellersByRevenue.0.name', 'Reseller Kaya')
            ->where('topResellersByRevenue.0.revenue', 8000000)
            ->where('topResellersByRevenue.1.name', 'Reseller Kecil')
            ->where('topResellersByRevenue.1.revenue', 1000000));
});

it('lists recent calls (any source) newest first, calls only, flagging CTI leads', function () {
    $reseller = Reseller::factory()->create();
    $agent = userWithRole('cs');

    $lead = Customer::factory()->create([
        'reseller_id' => null,
        'name' => 'Penelepon Baru',
        'status' => CustomerStatus::Lead,
        'source' => CustomerSource::Cti,
    ]);
    $known = Customer::factory()->create(['reseller_id' => $reseller->id, 'name' => 'Customer Lama']);

    // A non-call interaction must be excluded from the feed.
    Interaction::factory()->forCustomer($known)->create([
        'type' => InteractionType::Note,
        'occurred_at' => now(),
    ]);

    // Older manual call, handled by an agent.
    Interaction::factory()->forCustomer($known)->call()->create([
        'user_id' => $agent->id,
        'source' => InteractionSource::Manual,
        'occurred_at' => now()->subHour(),
    ]);

    // Newest: a CTI call on the auto-lead, no agent resolved.
    Interaction::factory()->forCustomer($lead)->call()->create([
        'user_id' => null,
        'source' => InteractionSource::Cti,
        'occurred_at' => now(),
    ]);

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentCalls', 2) // the note is excluded
            ->where('recentCalls.0.customer.name', 'Penelepon Baru') // newest first
            ->where('recentCalls.0.source', 'cti')
            ->where('recentCalls.0.is_cti_lead', true)
            ->where('recentCalls.0.user', null) // "oleh sistem"
            ->where('recentCalls.1.customer.name', 'Customer Lama')
            ->where('recentCalls.1.source', 'manual')
            ->where('recentCalls.1.is_cti_lead', false)
            ->where('recentCalls.1.user.id', $agent->id)
            ->has('recentCalls.0', fn (Assert $row) => $row->hasAll([
                'id', 'customer', 'direction', 'outcome', 'outcome_label',
                'duration_sec', 'occurred_at', 'source', 'user', 'is_cti_lead',
            ])));
});

it('caps the recent calls feed at ten', function () {
    $customer = Customer::factory()->create();
    Interaction::factory()->forCustomer($customer)->call()->count(12)->create();

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('recentCalls', 10));
});

it('counts a warranty expiring today as active and surfaces it in expiringSoon with zero days left', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id, 'name' => 'Kadaluarsa Hari Ini']);
    $product = Product::factory()->create(['warranty_months' => 12]);

    // Expires exactly today (endOfDay boundary) → must bucket as active, not expired.
    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12),
    ]);

    // Expired yesterday → must bucket as expired and stay out of the watchlist.
    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
        'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12)->subDay(),
    ]);

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('warrantyBreakdown.active', 1)
            ->where('warrantyBreakdown.expired', 1)
            ->where('stats.activeWarranties', 1)
            ->has('expiringSoon', 1)
            ->where('expiringSoon.0.customer', 'Kadaluarsa Hari Ini')
            ->where('expiringSoon.0.days_left', 0)); // the "0 hari lagi" case now surfaces
});
