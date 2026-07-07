<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use Database\Seeders\DemoDataSeeder;

beforeEach(fn () => $this->seed(DemoDataSeeder::class));

it('seeds all four entities with data', function () {
    expect(Reseller::count())->toBeGreaterThan(0)
        ->and(Product::count())->toBeGreaterThan(0)
        ->and(Customer::count())->toBeGreaterThan(0)
        ->and(Transaction::count())->toBeGreaterThan(0);
});

it('seeds a reseller tree with both roots and children', function () {
    expect(Reseller::whereNull('parent_id')->count())->toBeGreaterThan(0)
        ->and(Reseller::whereNotNull('parent_id')->count())->toBeGreaterThan(0);

    // Every non-root reseller points at a reseller that actually exists.
    $orphans = Reseller::whereNotNull('parent_id')
        ->whereNotIn('parent_id', Reseller::pluck('id'))
        ->count();

    expect($orphans)->toBe(0);
});

it('keeps every transaction consistent with its customer reseller', function () {
    Transaction::with('customer')->get()->each(
        fn (Transaction $t) => expect($t->reseller_id)->toBe($t->customer->reseller_id)
    );
});
