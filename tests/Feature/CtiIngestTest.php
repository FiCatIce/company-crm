<?php

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * A valid inbound-call payload; override per scenario.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ctiPayload(array $overrides = []): array
{
    return array_merge([
        'external_call_id' => 'pbx-'.fake()->unique()->numerify('########'),
        'direction' => 'in',
        'from_number' => '+628123456789',
        'to_number' => '+622150000000',
        'started_at' => '2026-07-10T09:15:00Z',
        'answered' => true,
        'duration_sec' => 120,
        'outcome' => 'answered',
    ], $overrides);
}

/** Authenticate as the integration principal with the cti:ingest ability. */
function actingAsCti(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['cti:ingest']);

    return $user;
}

it('rejects requests without a token (401)', function () {
    $this->postJson('/api/cti/calls', ctiPayload())->assertUnauthorized();
});

it('rejects a token lacking the cti:ingest ability (403)', function () {
    Sanctum::actingAs(User::factory()->create(), ['some:other']);

    $this->postJson('/api/cti/calls', ctiPayload())->assertForbidden();
});

it('is stateless — succeeds with a bearer token and no CSRF token', function () {
    actingAsCti();
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '081234567890',
    ]))->assertCreated();

    expect($customer->interactions()->count())->toBe(1);
});

it('attaches the interaction to a matching customer', function () {
    actingAsCti();
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    $response = $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '+6281234567890',
        'external_call_id' => 'pbx-match-1',
    ]));

    $response->assertCreated()->assertJson([
        'created' => true,
        'customer_id' => $customer->id,
        'customer_created' => false,
    ]);

    $interaction = $customer->interactions()->sole();
    expect($interaction->type)->toBe(InteractionType::Call)
        ->and($interaction->source)->toBe(InteractionSource::Cti)
        ->and($interaction->external_ref)->toBe('pbx-match-1')
        ->and($interaction->duration_sec)->toBe(120);
});

it('is idempotent — a replayed external_call_id creates only one interaction', function () {
    actingAsCti();
    $customer = Customer::factory()->create(['phone' => '081234567890']);
    $payload = ctiPayload(['from_number' => '081234567890', 'external_call_id' => 'pbx-dup-1']);

    $this->postJson('/api/cti/calls', $payload)->assertCreated();

    $this->postJson('/api/cti/calls', $payload)
        ->assertOk()
        ->assertJson(['idempotent' => true]);

    expect($customer->interactions()->count())->toBe(1)
        ->and(Interaction::where('external_ref', 'pbx-dup-1')->count())->toBe(1);
});

it('auto-creates a guarded lead for an answered call from an unknown number', function () {
    actingAsCti();

    $response = $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '082298765432',
        'answered' => true,
        'duration_sec' => 45,
        'outcome' => 'answered',
    ]));

    $response->assertCreated()->assertJson(['created' => true, 'customer_created' => true]);

    $customer = Customer::query()->where('phone_normalized', '+6282298765432')->sole();
    expect($customer->status)->toBe(CustomerStatus::Lead)
        ->and($customer->source)->toBe(CustomerSource::Cti)
        ->and($customer->name)->toContain('Penelepon')
        ->and($customer->interactions()->count())->toBe(1);
});

it('skips an unmatched call that fails the lead guard', function (array $overrides) {
    actingAsCti();

    $this->postJson('/api/cti/calls', ctiPayload(array_merge([
        'from_number' => '082298765432',
    ], $overrides)))
        ->assertOk()
        ->assertJson(['skipped' => true]);

    expect(Customer::query()->where('phone_normalized', '+6282298765432')->exists())->toBeFalse()
        ->and(Interaction::count())->toBe(0);
})->with([
    'missed / unanswered' => [['answered' => false, 'duration_sec' => 0, 'outcome' => 'missed']],
    'too short' => [['answered' => true, 'duration_sec' => 5, 'outcome' => 'answered']],
    'wrong number' => [['answered' => true, 'duration_sec' => 120, 'outcome' => 'wrong_number']],
]);

it('resolves the handling agent from the extension', function () {
    actingAsCti();
    $agent = User::factory()->create(['extension' => '1001']);
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '081234567890',
        'agent_extension' => '1001',
    ]))->assertCreated();

    expect($customer->interactions()->sole()->user_id)->toBe($agent->id);
});

it('stores a null agent when the extension does not match', function () {
    actingAsCti();
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '081234567890',
        'agent_extension' => '9999',
    ]))->assertCreated();

    expect($customer->interactions()->sole()->user_id)->toBeNull();
});

it('stores the recording_url in meta', function () {
    actingAsCti();
    $customer = Customer::factory()->create(['phone' => '081234567890']);

    $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '081234567890',
        'recording_url' => 'https://pbx.local/rec/abc.mp3',
    ]))->assertCreated();

    expect($customer->interactions()->sole()->meta)
        ->toBe(['recording_url' => 'https://pbx.local/rec/abc.mp3']);
});

it('skips an unparseable caller number', function () {
    actingAsCti();

    $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => 'not-a-number',
    ]))
        ->assertOk()
        ->assertJson(['skipped' => true, 'reason' => 'unparseable_number']);

    expect(Interaction::count())->toBe(0);
});

it('rejects an invalid payload (422)', function (array $overrides) {
    actingAsCti();

    $this->postJson('/api/cti/calls', ctiPayload($overrides))->assertStatus(422);
})->with([
    'missing external_call_id' => [['external_call_id' => null]],
    'bad direction' => [['direction' => 'sideways']],
    'missing from_number' => [['from_number' => null]],
    'bad outcome' => [['outcome' => 'exploded']],
]);

it('creates a reseller-less lead customer (CTI shape)', function () {
    $lead = Customer::create([
        'name' => 'Penelepon +6282298765432',
        'phone' => '082298765432',
        'status' => CustomerStatus::Lead,
        'source' => CustomerSource::Cti,
    ]);

    expect($lead->fresh()->status)->toBe(CustomerStatus::Lead)
        ->and($lead->fresh()->source)->toBe(CustomerSource::Cti);
});
