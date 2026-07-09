<?php

use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('logs a manual interaction with source and author set server-side', function () {
    $agent = userWithRole('cs');
    $customer = Customer::factory()->create();

    $this->actingAs($agent)
        ->post(route('interactions.store', $customer), [
            'type' => InteractionType::Call->value,
            'direction' => 'out',
            'subject' => 'Follow up garansi',
            'body' => 'Menghubungi customer.',
            'outcome' => 'answered',
            'duration_sec' => 120,
        ])
        ->assertRedirect();

    $interaction = $customer->interactions()->sole();

    expect($interaction->source)->toBe(InteractionSource::Manual)
        ->and($interaction->user_id)->toBe($agent->id)
        ->and($interaction->type)->toBe(InteractionType::Call)
        ->and($interaction->duration_sec)->toBe(120);
});

it('defaults occurred_at to now when omitted', function () {
    $agent = userWithRole('cs');
    $customer = Customer::factory()->create();

    $this->actingAs($agent)
        ->post(route('interactions.store', $customer), [
            'type' => InteractionType::Note->value,
            'body' => 'Catatan.',
        ])
        ->assertRedirect();

    expect($customer->interactions()->sole()->occurred_at->isToday())->toBeTrue();
});

it('requires a direction when the interaction is a call', function () {
    $agent = userWithRole('cs');
    $customer = Customer::factory()->create();

    $this->actingAs($agent)
        ->from(route('customers.show', $customer))
        ->post(route('interactions.store', $customer), [
            'type' => InteractionType::Call->value,
        ])
        ->assertSessionHasErrors('direction');
});

it('rejects logging by a user without a CRM role', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('interactions.store', $customer), ['type' => InteractionType::Note->value])
        ->assertForbidden();
});

it('lets the author update their own manual interaction', function () {
    $agent = userWithRole('cs');
    $interaction = Interaction::factory()->create([
        'user_id' => $agent->id,
        'source' => InteractionSource::Manual,
        'type' => InteractionType::Note,
    ]);

    $this->actingAs($agent)
        ->put(route('interactions.update', $interaction), [
            'type' => InteractionType::Note->value,
            'subject' => 'Diperbarui',
            'body' => 'Update.',
        ])
        ->assertRedirect();

    expect($interaction->fresh()->subject)->toBe('Diperbarui');
});

it('forbids cs from managing another agent\'s interaction', function () {
    $mine = userWithRole('cs');
    $other = userWithRole('cs');
    $interaction = Interaction::factory()->create([
        'user_id' => $other->id,
        'source' => InteractionSource::Manual,
    ]);

    $this->actingAs($mine)
        ->put(route('interactions.update', $interaction), ['type' => InteractionType::Note->value])
        ->assertForbidden();

    $this->actingAs($mine)
        ->delete(route('interactions.destroy', $interaction))
        ->assertForbidden();
});

it('lets a supervisor manage anyone\'s manual interaction', function () {
    $author = userWithRole('cs');
    $supervisor = userWithRole('supervisor');
    $interaction = Interaction::factory()->create([
        'user_id' => $author->id,
        'source' => InteractionSource::Manual,
    ]);

    $this->actingAs($supervisor)
        ->delete(route('interactions.destroy', $interaction))
        ->assertRedirect();

    expect(Interaction::withTrashed()->find($interaction->id)->trashed())->toBeTrue();
});

it('treats CTI logs as immutable even for an admin', function () {
    $admin = userWithRole('admin');
    $interaction = Interaction::factory()->create([
        'source' => InteractionSource::Cti,
        'type' => InteractionType::Call,
    ]);

    $this->actingAs($admin)
        ->put(route('interactions.update', $interaction), [
            'type' => InteractionType::Call->value,
            'direction' => 'in',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('interactions.destroy', $interaction))
        ->assertForbidden();
});

it('soft deletes an interaction', function () {
    $agent = userWithRole('cs');
    $interaction = Interaction::factory()->create([
        'user_id' => $agent->id,
        'source' => InteractionSource::Manual,
    ]);

    $this->actingAs($agent)
        ->delete(route('interactions.destroy', $interaction))
        ->assertRedirect();

    expect(Interaction::find($interaction->id))->toBeNull()
        ->and(Interaction::withTrashed()->find($interaction->id))->not->toBeNull();
});
