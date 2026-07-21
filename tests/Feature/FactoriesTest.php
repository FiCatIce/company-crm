<?php

use App\Models\Product;
use App\Models\Transaction;

it('creates a product with a non-negative warranty period', function () {
    $product = Product::factory()->create();

    expect($product->warranty_months)->toBeGreaterThanOrEqual(0);
});

it('creates a transaction linked to a customer and product', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->customer)->not->toBeNull()
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
