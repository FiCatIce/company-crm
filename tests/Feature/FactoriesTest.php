<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;

it('creates a top-level reseller with no parent by default', function () {
    $reseller = Reseller::factory()->create();

    expect($reseller->parent_id)->toBeNull()
        ->and($reseller->exists)->toBeTrue();
});

it('builds a reseller tree via the childOf state', function () {
    $parent = Reseller::factory()->create();
    $child = Reseller::factory()->childOf($parent)->create();

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children->pluck('id'))->toContain($child->id);
});

it('creates a product with a non-negative warranty period', function () {
    $product = Product::factory()->create();

    expect($product->warranty_months)->toBeGreaterThanOrEqual(0);
});

it('creates a customer attached to a reseller', function () {
    $customer = Customer::factory()->create();

    expect($customer->reseller)->not->toBeNull()
        ->and($customer->reseller)->toBeInstanceOf(Reseller::class);
});

it('creates a transaction whose reseller matches its customer', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->reseller_id)->toBe($transaction->customer->reseller_id)
        ->and($transaction->product)->not->toBeNull();
});

it('computes warranty expiry and under-warranty status from the product', function () {
    $product = Product::factory()->create(['warranty_months' => 12]);

    $active = Transaction::factory()->create([
        'product_id' => $product->id,
        'purchased_at' => now()->subMonths(6),
    ]);

    expect($active->warranty_expires_at->toDateString())
        ->toBe(now()->subMonths(6)->addMonths(12)->toDateString())
        ->and($active->is_under_warranty)->toBeTrue();

    $expired = Transaction::factory()->create([
        'product_id' => Product::factory()->create(['warranty_months' => 6])->id,
        'purchased_at' => now()->subYear(),
    ]);

    expect($expired->is_under_warranty)->toBeFalse();
});
