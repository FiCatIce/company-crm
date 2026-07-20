<?php

use App\Enums\InteractionSource;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Batch H7 — WRITE FOLLOWS SIGHT. H3 made READS hierarchy-tight but left writes
 * global: customer.update.all / customer.delete / interaction.manage.all were
 * unbounded, so a team-scoped manager or an assignment-scoped CS agent could
 * mutate records they could not even see, just by knowing an id. These are the
 * IDOR regressions for that closure.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/** A rep on their own team, owning one customer. @return array{0: User, 1: Customer} */
function repWithBook(): array
{
    $rep = userWithRole('sales');
    $team = Team::factory()->create();
    $team->members()->attach($rep->id, ['role_in_team' => 'sales']);

    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $rep->id, 'assigned_to' => $rep->id])->save();

    return [$rep, $customer];
}

// --- Customer write IDOR ------------------------------------------------------

it('blocks a manager from editing a customer of another team by id', function () {
    [$repB, $customerB] = repWithBook();
    managerOverTeamOf($repB);                       // team B has its own manager

    $managerA = managerOverTeamOf(userWithRole('sales'));

    $this->actingAs($managerA)
        ->put(route('customers.update', $customerB), [])
        ->assertForbidden();
});

it('blocks a manager from deleting a customer of another team by id', function () {
    [$repB, $customerB] = repWithBook();
    managerOverTeamOf($repB);

    $managerA = managerOverTeamOf(userWithRole('sales'));

    $this->actingAs($managerA)
        ->delete(route('customers.destroy', $customerB))
        ->assertForbidden();

    $this->assertDatabaseHas('customers', ['id' => $customerB->id]);
});

it('blocks a manager from the quick-change endpoints on another team customer', function (string $route) {
    [$repB, $customerB] = repWithBook();
    managerOverTeamOf($repB);

    $managerA = managerOverTeamOf(userWithRole('sales'));

    $this->actingAs($managerA)
        ->patch(route($route, $customerB), [])
        ->assertForbidden();
})->with(['customers.status', 'customers.owner']);

it('blocks a CS agent from editing the book of a rep who never assigned them', function () {
    [, $customer] = repWithBook();
    $cs = userWithRole('cs');            // holds customer.update.all, but sees nothing

    $this->actingAs($cs)
        ->put(route('customers.update', $customer), [])
        ->assertForbidden();
});

it('still lets an assigned CS agent and the own-team manager write', function () {
    $customer = Customer::factory()->create();
    [$cs, $rep] = supportAssignedToOwnerOf($customer, 'cs');
    $manager = managerOverTeamOf($rep);

    expect($cs->can('update', $customer->fresh()))->toBeTrue()
        ->and($manager->can('update', $customer->fresh()))->toBeTrue()
        ->and($rep->can('update', $customer->fresh()))->toBeTrue();
});

// --- Interaction moderation IDOR ---------------------------------------------

it('blocks a manager from moderating an interaction on another team customer', function () {
    [$repB, $customerB] = repWithBook();
    managerOverTeamOf($repB);

    $interaction = Interaction::factory()->create([
        'customer_id' => $customerB->id,
        'user_id' => $repB->id,
        'source' => InteractionSource::Manual,
    ]);

    $managerA = managerOverTeamOf(userWithRole('sales'));

    expect($managerA->can('update', $interaction))->toBeFalse()
        ->and($managerA->can('delete', $interaction))->toBeFalse();
});

it('still lets the own-team manager moderate their team interactions', function () {
    [$rep, $customer] = repWithBook();
    $manager = managerOverTeamOf($rep);

    $interaction = Interaction::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $rep->id,
        'source' => InteractionSource::Manual,
    ]);

    expect($manager->can('update', $interaction))->toBeTrue();
});

// --- Handing a customer over grants sight, so it is bounded too ---------------

it('rejects handing a customer to an agent outside the actor hierarchy', function () {
    [$rep, $customer] = repWithBook();
    $outsider = userWithRole('sales');   // no shared team

    $this->actingAs($rep)
        ->from(route('customers.edit', $customer))
        ->put(route('customers.update', $customer), [
            'reseller_id' => $customer->reseller_id,
            'name' => $customer->name,
            'assigned_to' => $outsider->id,
        ])
        ->assertSessionHasErrors('assigned_to');

    expect($customer->fresh()->assigned_to)->toBe($rep->id);
});

it('allows handing a customer to a teammate', function () {
    [$rep, $customer] = repWithBook();
    $mate = userWithRole('sales');
    $rep->teams()->first()?->members()->attach($mate->id, ['role_in_team' => 'sales']);

    $this->actingAs($rep)
        ->from(route('customers.edit', $customer))
        ->put(route('customers.update', $customer), [
            'reseller_id' => $customer->reseller_id,
            'name' => $customer->name,
            'assigned_to' => $mate->id,
        ])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->assigned_to)->toBe($mate->id);
});

// --- P2 regression: per-row abilities ----------------------------------------

it('marks a rep own rows editable in the customer list (P2 blank-instance regression)', function () {
    [$rep, $customer] = repWithBook();

    $this->actingAs($rep)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.data', function ($rows) use ($customer) {
                $row = collect($rows)->firstWhere('id', $customer->id);

                return $row['can_edit'] === true && $row['can_delete'] === false;
            }));
});
