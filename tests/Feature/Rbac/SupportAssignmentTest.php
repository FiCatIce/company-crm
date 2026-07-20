<?php

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Support\SupportAssignments;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Batch H5 (DESIGN_HIERARCHY.md) — the support-assignment UI. The pivot these
 * routes write is read LIVE by Customer::scopeVisibleTo (H3), so the real subject
 * here is ACCESS: assigning must grant sight of the rep's book immediately and
 * unassigning must revoke it — without disturbing another rep's assignment (the
 * union). Also pins the H5 pool decision: same team only.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A sales rep who belongs to a team.
 *
 * @return array{0: User, 1: Team}
 */
function salesOnTeam(): array
{
    $team = Team::factory()->create();
    $sales = userWithRole('sales');
    $team->members()->attach($sales->id, ['role_in_team' => 'sales']);

    return [$sales, $team];
}

function agentOnTeam(Team $team, string $role): User
{
    $agent = userWithRole($role);
    $team->members()->attach($agent->id, ['role_in_team' => $role]);

    return $agent;
}

/**
 * A customer owned by $owner (created_by is server-side only, so force it).
 */
function customerOwnedBy(User $owner): Customer
{
    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $owner->id])->save();

    return $customer;
}

/**
 * @return list<int>
 */
function visibleCustomerIds(User $user): array
{
    return Customer::query()->visibleTo($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
}

// --- Access effect: the whole point of the batch ------------------------------

it('grants the assignee live sight of the rep book on assign', function (string $role) {
    [$sales, $team] = salesOnTeam();
    $agent = agentOnTeam($team, $role);
    $customer = customerOwnedBy($sales);

    expect(visibleCustomerIds($agent))->not->toContain($customer->id);

    $this->actingAs($sales)
        ->post(route('team.assignments.store'), ['assignee_ids' => [$agent->id]])
        ->assertRedirect(route('team.assignments.index'));

    $this->assertDatabaseHas('sales_assignee', [
        'sales_user_id' => $sales->id,
        'assignee_user_id' => $agent->id,
    ]);

    expect(visibleCustomerIds($agent))->toContain($customer->id);
})->with(['cs', 'maintenance']);

it('revokes only the unassigned rep book and keeps the other assigner (union)', function () {
    [$salesA, $team] = salesOnTeam();
    $salesB = agentOnTeam($team, 'sales');
    $agent = agentOnTeam($team, 'cs');

    $customerA = customerOwnedBy($salesA);
    $customerB = customerOwnedBy($salesB);

    SupportAssignments::assign($salesA, $agent);
    SupportAssignments::assign($salesB, $agent);

    expect(visibleCustomerIds($agent))->toContain($customerA->id)->toContain($customerB->id);

    $this->actingAs($salesA)
        ->delete(route('team.assignments.destroy', $agent))
        ->assertRedirect(route('team.assignments.index'));

    $ids = visibleCustomerIds($agent);
    expect($ids)->not->toContain($customerA->id)   // A's book is gone...
        ->and($ids)->toContain($customerB->id);    // ...B still assigns them
});

// --- Type + team guards -------------------------------------------------------

it('rejects assigning a non-support type even from the same team', function (string $role) {
    [$sales, $team] = salesOnTeam();
    $other = agentOnTeam($team, $role);

    $this->actingAs($sales)
        ->from(route('team.assignments.index'))
        ->post(route('team.assignments.store'), ['assignee_ids' => [$other->id]])
        ->assertSessionHasErrors('assignee_ids.0');

    $this->assertDatabaseMissing('sales_assignee', [
        'sales_user_id' => $sales->id,
        'assignee_user_id' => $other->id,
    ]);
})->with(['sales', 'supervisor', 'admin']);

it('rejects assigning support from another team (pool is team-scoped)', function () {
    [$sales] = salesOnTeam();
    $outsider = agentOnTeam(Team::factory()->create(), 'cs');

    $this->actingAs($sales)
        ->from(route('team.assignments.index'))
        ->post(route('team.assignments.store'), ['assignee_ids' => [$outsider->id]])
        ->assertSessionHasErrors('assignee_ids.0');

    $this->assertDatabaseMissing('sales_assignee', [
        'sales_user_id' => $sales->id,
        'assignee_user_id' => $outsider->id,
    ]);
});

// --- Always self-scoped -------------------------------------------------------

it('records the ACTING rep as the assigner, never another rep', function () {
    [$salesA, $team] = salesOnTeam();
    $salesB = agentOnTeam($team, 'sales');
    $agent = agentOnTeam($team, 'cs');

    $this->actingAs($salesA)->post(route('team.assignments.store'), ['assignee_ids' => [$agent->id]]);

    $this->assertDatabaseHas('sales_assignee', ['sales_user_id' => $salesA->id, 'assignee_user_id' => $agent->id]);
    $this->assertDatabaseMissing('sales_assignee', ['sales_user_id' => $salesB->id, 'assignee_user_id' => $agent->id]);
});

it('cannot sever another reps assignment', function () {
    [$salesA, $team] = salesOnTeam();
    $salesB = agentOnTeam($team, 'sales');
    $agent = agentOnTeam($team, 'cs');
    SupportAssignments::assign($salesB, $agent);

    $this->actingAs($salesA)->delete(route('team.assignments.destroy', $agent));

    // A only ever detaches its OWN row, so B's assignment survives untouched.
    $this->assertDatabaseHas('sales_assignee', ['sales_user_id' => $salesB->id, 'assignee_user_id' => $agent->id]);
});

// --- Candidate pool -----------------------------------------------------------

it('offers only same-team support that is not already assigned', function () {
    [$sales, $team] = salesOnTeam();
    $free = agentOnTeam($team, 'cs');
    $already = agentOnTeam($team, 'maintenance');
    agentOnTeam($team, 'sales');                          // wrong type
    agentOnTeam(Team::factory()->create(), 'cs');         // wrong team

    SupportAssignments::assign($sales, $already);

    $this->actingAs($sales)
        ->get(route('team.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TeamAssignments/Index')
            ->where('hasTeam', true)
            ->where('candidates', fn ($c) => collect($c)->pluck('id')->all() === [$free->id])
            ->where('assignees', fn ($a) => collect($a)->pluck('id')->all() === [$already->id]));
});

it('shows a teamless rep an empty pool', function () {
    $sales = userWithRole('sales'); // no team

    $this->actingAs($sales)
        ->get(route('team.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasTeam', false)
            ->where('candidates', []));
});

// --- Area gating --------------------------------------------------------------

it('forbids the assignment area for roles without user.assign', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('team.assignments.index'))
        ->assertForbidden();
})->with(['cs', 'maintenance', 'supervisor', 'admin']);

// --- Audit --------------------------------------------------------------------

it('audits assign and unassign', function () {
    [$sales, $team] = salesOnTeam();
    $agent = agentOnTeam($team, 'cs');

    $this->actingAs($sales)->post(route('team.assignments.store'), ['assignee_ids' => [$agent->id]]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $sales->id, 'target_user_id' => $agent->id, 'action' => 'support.assigned',
    ]);

    $this->actingAs($sales)->delete(route('team.assignments.destroy', $agent));
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $sales->id, 'target_user_id' => $agent->id, 'action' => 'support.unassigned',
    ]);
});
