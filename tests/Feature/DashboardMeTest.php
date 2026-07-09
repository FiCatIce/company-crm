<?php

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Batch 3f — the per-agent "me" block on the dashboard. Everything here is
 * scoped to the signed-in user (assigned customers / authored interactions);
 * the org-wide widgets are unaffected and covered by DashboardMetricsTest.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('scopes myCustomers and myRecentInteractions to the acting user', function () {
    $me = userWithRole('cs');
    $other = User::factory()->create();

    Customer::factory()->count(2)->create(['assigned_to' => $me->id]);
    Customer::factory()->create(['assigned_to' => $other->id]);
    Customer::factory()->create(['assigned_to' => null]);

    $mine = Customer::factory()->create(['assigned_to' => $me->id]);
    Interaction::factory()->forCustomer($mine)->count(3)->create(['user_id' => $me->id]);
    Interaction::factory()->count(2)->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('me.myCustomers', 3)              // 2 + mine, others/unassigned excluded
            ->has('me.myRecentInteractions', 3));     // only mine
});

it('counts only my interactions dated today', function () {
    $me = userWithRole('admin');
    $customer = Customer::factory()->create(['assigned_to' => $me->id]);

    Interaction::factory()->forCustomer($customer)->count(2)->create([
        'user_id' => $me->id,
        'occurred_at' => now(),
    ]);
    Interaction::factory()->forCustomer($customer)->create([
        'user_id' => $me->id,
        'occurred_at' => now()->subDay(),             // yesterday → excluded
    ]);
    Interaction::factory()->forCustomer($customer)->create([
        'user_id' => User::factory()->create()->id,   // someone else, today → excluded
        'occurred_at' => now(),
    ]);

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('me.myInteractionsToday', 2));
});

it('counts my expiring warranties from owned customers only, reusing the 30-day rule', function () {
    $me = userWithRole('admin');
    $reseller = Reseller::factory()->create();
    $product = Product::factory()->create(['warranty_months' => 12]);

    $mineSoon = Customer::factory()->create(['assigned_to' => $me->id, 'reseller_id' => $reseller->id]);
    $mineFar = Customer::factory()->create(['assigned_to' => $me->id, 'reseller_id' => $reseller->id]);
    $othersSoon = Customer::factory()->create(['assigned_to' => User::factory()->create()->id, 'reseller_id' => $reseller->id]);

    // Mine, expiring in ~6 days → counts.
    Transaction::factory()->create([
        'customer_id' => $mineSoon->id, 'reseller_id' => $reseller->id, 'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12)->addDays(6),
    ]);
    // Mine, expiring in ~9 months → excluded (beyond 30 days).
    Transaction::factory()->create([
        'customer_id' => $mineFar->id, 'reseller_id' => $reseller->id, 'product_id' => $product->id,
        'purchased_at' => now()->subMonths(3),
    ]);
    // Another owner's, expiring soon → excluded (not mine).
    Transaction::factory()->create([
        'customer_id' => $othersSoon->id, 'reseller_id' => $reseller->id, 'product_id' => $product->id,
        'purchased_at' => now()->subMonths(12)->addDays(6),
    ]);

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('me.myExpiringWarranties', 1));
});

it('returns my five most recent interactions newest first with the expected shape', function () {
    $me = userWithRole('cs');
    $customer = Customer::factory()->create(['assigned_to' => $me->id, 'name' => 'Pelanggan A']);

    // Seven on ascending dates; the newest (i=7) must lead and only five show.
    foreach (range(1, 7) as $i) {
        Interaction::factory()->forCustomer($customer)->create([
            'user_id' => $me->id,
            'subject' => "Interaksi {$i}",
            'occurred_at' => now()->subDays(10 - $i),
        ]);
    }

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('me.myRecentInteractions', 5)
            ->where('me.myRecentInteractions.0.subject', 'Interaksi 7')     // newest first
            ->where('me.myRecentInteractions.0.customer.name', 'Pelanggan A')
            ->has('me.myRecentInteractions.0', fn (Assert $row) => $row
                ->hasAll(['id', 'customer', 'type', 'type_label', 'direction', 'occurred_at', 'subject'])));
});

it('renders the personal block calmly with no assignments or interactions', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('me.myCustomers', 0)
            ->where('me.myInteractionsToday', 0)
            ->where('me.myExpiringWarranties', 0)
            ->has('me.myRecentInteractions', 0));
});
