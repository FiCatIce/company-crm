<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use Database\Seeders\DemoDataSeeder;

beforeEach(fn () => $this->seed(DemoDataSeeder::class));

it('seeds products, customers and transactions with data', function () {
    expect(Product::count())->toBeGreaterThan(0)
        ->and(Customer::count())->toBeGreaterThan(0)
        ->and(Transaction::count())->toBeGreaterThan(0);
});

it('attributes every transaction to a real customer', function () {
    Transaction::with('customer')->get()->each(
        fn (Transaction $t) => expect($t->customer)->not->toBeNull()
    );
});
