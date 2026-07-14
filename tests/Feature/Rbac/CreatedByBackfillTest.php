<?php

use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerCreatedByBackfill;

// New customers are created with a null created_by (the column is not fillable),
// which is exactly the "legacy row" shape the backfill targets.

it('backfills created_by from assigned_to for owned customers', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $owner->id]);

    expect($customer->created_by)->toBeNull();

    $updated = CustomerCreatedByBackfill::run();

    expect($updated)->toBe(1)
        ->and($customer->fresh()->created_by)->toBe($owner->id);
});

it('leaves ownerless customers with a null created_by', function () {
    $customer = Customer::factory()->create(['assigned_to' => null]);

    CustomerCreatedByBackfill::run();

    expect($customer->fresh()->created_by)->toBeNull();
});

it('is idempotent and never overwrites an existing creator', function () {
    $owner = User::factory()->create();
    $creator = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $owner->id]);
    $customer->forceFill(['created_by' => $creator->id])->save();

    $updated = CustomerCreatedByBackfill::run();

    expect($updated)->toBe(0)
        ->and($customer->fresh()->created_by)->toBe($creator->id);
});
