<?php

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds the CRM columns to customers and amount to transactions', function () {
    expect(Schema::hasColumns('customers', ['assigned_to', 'phone_normalized', 'status', 'source']))->toBeTrue()
        ->and(Schema::hasColumn('transactions', 'amount'))->toBeTrue();
});

it('casts status/source to enums and defaults status to active', function () {
    $customer = Customer::factory()->create([
        'status' => CustomerStatus::Lead,
        'source' => CustomerSource::Referral,
    ]);

    expect($customer->status)->toBe(CustomerStatus::Lead)
        ->and($customer->source)->toBe(CustomerSource::Referral);

    // Column default applies when not provided (raw insert, bypassing the model).
    $id = DB::table('customers')->insertGetId([
        'reseller_id' => Customer::factory()->create()->reseller_id,
        'name' => 'Tanpa Status',
    ]);

    expect(Customer::find($id)->status)->toBe(CustomerStatus::Active);
});

it('auto-populates phone_normalized (E.164) from phone via the mutator', function () {
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    expect($customer->phone)->toBe('081234567890')
        ->and($customer->phone_normalized)->toBe('+6281234567890');

    $customer->update(['phone' => '0898-7654-3210']);

    expect($customer->fresh()->phone_normalized)->toBe(PhoneNormalizer::e164('0898-7654-3210'));
});

it('links a customer to its owner and back', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $owner->id]);

    expect($customer->owner->is($owner))->toBeTrue()
        ->and($owner->assignedCustomers->pluck('id'))->toContain($customer->id);
});

it('nulls assigned_to when the owner is deleted', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $owner->id]);

    $owner->delete();

    expect($customer->fresh()->assigned_to)->toBeNull();
});

it('casts the transaction amount to a two-decimal value', function () {
    $transaction = Transaction::factory()->create(['amount' => 1_500_000]);

    expect($transaction->fresh()->amount)->toBe('1500000.00');
});

it('backfills phone_normalized for legacy rows via the command', function () {
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    // Simulate a row saved before the mutator existed.
    DB::table('customers')->where('id', $customer->id)->update(['phone_normalized' => null]);

    $this->artisan('customers:backfill-phone')->assertSuccessful();

    expect($customer->fresh()->phone_normalized)->toBe('+6281234567890');
});
