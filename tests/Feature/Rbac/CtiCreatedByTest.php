<?php

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Database\Seeders\RoleSeeder;

/**
 * B7 — CTI auto-lead attribution (DESIGN_RBAC.md decision D4). A guarded auto-lead
 * is stamped created_by = the resolved handling agent, putting the fresh prospect
 * inside that agent's Sales scope (created_by OR assigned_to) so they can follow it
 * up — while other reps still cannot see it. Reuses ctiPayload()/actingAsCti() from
 * CtiIngestTest.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

it('stamps a CTI auto-lead with the handling agent and puts it in their scope', function () {
    actingAsCti();
    $agent = userWithRole('sales');
    $agent->update(['extension' => '4021']);

    $response = $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '+628999888777',      // unknown caller → guarded auto-lead
        'external_call_id' => 'pbx-lead-d4',
        'agent_extension' => '4021',
        'answered' => true,
        'duration_sec' => 45,
    ]))->assertCreated();

    $lead = Customer::findOrFail($response->json('customer_id'));

    expect($lead->source)->toBe(CustomerSource::Cti)
        ->and($lead->status)->toBe(CustomerStatus::Lead)
        ->and($lead->created_by)->toBe($agent->id)         // D4: attributed to the agent
        ->and($lead->assigned_to)->toBe($agent->id)
        // ...so the handling Sales agent actually sees the prospect they just spoke to,
        ->and(Customer::visibleTo($agent)->whereKey($lead->id)->exists())->toBeTrue();

    // ...but another Sales rep does not.
    $otherRep = userWithRole('sales');
    expect(Customer::visibleTo($otherRep)->whereKey($lead->id)->exists())->toBeFalse();
});

it('leaves created_by null when the agent cannot be resolved', function () {
    actingAsCti();

    $response = $this->postJson('/api/cti/calls', ctiPayload([
        'from_number' => '+628111222333',
        'external_call_id' => 'pbx-lead-noagent',
        'agent_extension' => '9999',           // matches no user
        'answered' => true,
        'duration_sec' => 45,
    ]))->assertCreated();

    $lead = Customer::findOrFail($response->json('customer_id'));

    // No agent → unattributed (manager-only visible), never a bogus owner.
    expect($lead->created_by)->toBeNull()
        ->and($lead->assigned_to)->toBeNull();
});
