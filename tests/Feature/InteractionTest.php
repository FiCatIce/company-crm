<?php

use App\Enums\InteractionOutcome;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('creates the interactions table with the expected columns', function () {
    expect(Schema::hasTable('interactions'))->toBeTrue()
        ->and(Schema::hasColumns('interactions', [
            'customer_id', 'user_id', 'type', 'direction', 'subject', 'body',
            'outcome', 'duration_sec', 'occurred_at', 'source', 'external_ref', 'deleted_at',
        ]))->toBeTrue();
});

it('creates an interaction via factory with enum casts', function () {
    $interaction = Interaction::factory()->call()->create();

    expect($interaction->type)->toBe(InteractionType::Call)
        ->and($interaction->outcome)->toBeInstanceOf(InteractionOutcome::class)
        ->and($interaction->source)->toBe(InteractionSource::Manual)
        ->and($interaction->occurred_at)->not->toBeNull();
});

it('belongs to a customer and a handling user', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create();
    $interaction = Interaction::factory()->forCustomer($customer)->create(['user_id' => $user->id]);

    expect($interaction->customer->is($customer))->toBeTrue()
        ->and($interaction->user->is($user))->toBeTrue()
        ->and($customer->interactions->pluck('id'))->toContain($interaction->id)
        ->and($user->interactions->pluck('id'))->toContain($interaction->id);
});

it('nulls the handler when the user is deleted', function () {
    $user = User::factory()->create();
    $interaction = Interaction::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect($interaction->fresh()->user_id)->toBeNull();
});

it('soft deletes interactions', function () {
    $interaction = Interaction::factory()->create();

    $interaction->delete();

    expect($interaction->trashed())->toBeTrue()
        ->and(Interaction::count())->toBe(0)
        ->and(Interaction::withTrashed()->count())->toBe(1);
});

it('cascades interactions when the customer is deleted', function () {
    $customer = Customer::factory()->create();
    Interaction::factory()->forCustomer($customer)->count(3)->create();

    $customer->delete();

    expect(Interaction::withTrashed()->count())->toBe(0);
});
