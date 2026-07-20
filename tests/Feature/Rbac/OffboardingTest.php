<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Support\UserOffboarding;
use Database\Seeders\RoleSeeder;

/**
 * Batch H7c (DESIGN_HIERARCHY.md) — offboarding: transfer to a named successor,
 * then switch the leaver off.
 *
 * The three invariants under test, each of which is a way this could go quietly
 * wrong rather than loudly:
 *
 *  1. assigned_to moves, created_by does NOT — historical attribution is immutable,
 *     so a transfer must never rewrite who originally entered a customer.
 *  2. The TEAM outlives its manager (free from DH1 teams-as-entity): membership is
 *     untouched and no rep is orphaned; the successor takes the manager seat.
 *  3. Nobody can be removed out from under their work — delete is blocked while
 *     holdings remain, with a message naming what is in the way.
 *
 * Transfers are also checked by ACCESS, not just by rows: the successor must
 * actually SEE the moved book afterwards, and the leaver must not.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A team with a manager and two same-role reps — leaver and successor.
 *
 * @return array{0: User, 1: User, 2: User, 3: Team}
 */
function offboardFixture(string $type = 'sales'): array
{
    $team = Team::factory()->create();

    $manager = userWithRole('supervisor');
    $team->members()->attach($manager->id, ['role_in_team' => 'manager']);

    $leaver = userWithRole($type);
    $leaver->forceFill(['created_by_user' => $manager->id])->save();
    $team->members()->attach($leaver->id, ['role_in_team' => $type]);

    $successor = userWithRole($type);
    $successor->forceFill(['created_by_user' => $manager->id])->save();
    $team->members()->attach($successor->id, ['role_in_team' => $type]);

    return [$manager, $leaver, $successor, $team];
}

function customerFor(User $owner): Customer
{
    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $owner->id, 'assigned_to' => $owner->id])->save();

    return $customer;
}

// --- Transfer: sales -----------------------------------------------------------

it('moves the book and the support wiring, but never created_by', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    $customer = customerFor($leaver);

    $support = userWithRole('cs');
    $leaver->assignees()->attach($support->id);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id])
        ->assertRedirect();

    $customer = $customer->fresh();

    expect($customer->assigned_to)->toBe($successor->id)
        // The invariant that makes a transfer safe to run: attribution is history.
        ->and($customer->created_by)->toBe($leaver->id)
        ->and($successor->fresh()->assignees()->pluck('users.id')->all())->toBe([$support->id])
        ->and($leaver->fresh()->assignees()->count())->toBe(0)
        ->and($leaver->fresh()->is_active)->toBeFalse();
});

it('hands the successor real SIGHT of the moved book, and takes it from the leaver', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    $customer = Customer::factory()->create();
    // Assigned-only (created by someone else) so visibility can only come from the move.
    $customer->forceFill(['created_by' => $manager->id, 'assigned_to' => $leaver->id])->save();

    expect(Customer::query()->visibleTo($successor)->pluck('id')->all())->not->toContain($customer->id);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id]);

    expect(Customer::query()->visibleTo($successor->fresh())->pluck('id')->all())
        ->toContain($customer->id)
        ->and(Customer::query()->visibleTo($leaver->fresh())->pluck('id')->all())
        ->not->toContain($customer->id);
});

it('moves the reps a support agent serves', function () {
    [$manager, $leaver, $successor] = offboardFixture('cs');
    $sales = userWithRole('sales');
    $sales->assignees()->attach($leaver->id);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id]);

    expect($sales->fresh()->assignees()->pluck('users.id')->all())->toBe([$successor->id]);
});

it('does not duplicate a pivot row the successor already holds', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    $support = userWithRole('cs');
    $leaver->assignees()->attach($support->id);
    $successor->assignees()->attach($support->id);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id]);

    expect($successor->fresh()->assignees()->pluck('users.id')->all())->toBe([$support->id]);
});

// --- Transfer: manager ---------------------------------------------------------

it('keeps the team alive when its manager leaves', function () {
    $team = Team::factory()->create();
    $leaver = userWithRole('supervisor');
    $team->members()->attach($leaver->id, ['role_in_team' => 'manager']);

    $rep = userWithRole('sales');
    $team->members()->attach($rep->id, ['role_in_team' => 'sales']);

    $successor = userWithRole('supervisor'); // teamless — joined by the transfer
    $admin = userWithRole('admin');

    $this->actingAs($admin)
        ->post("/users/{$leaver->id}/offboard", ['successor_id' => $successor->id])
        ->assertRedirect();

    $memberIds = $team->fresh()->members()->pluck('users.id')->all();

    expect($memberIds)->toContain($rep->id)          // no orphaned rep
        ->and($memberIds)->toContain($successor->id) // successor took the seat
        ->and($memberIds)->not->toContain($leaver->id)
        ->and($team->fresh()->members()->where('users.id', $successor->id)
            ->first()?->pivot?->role_in_team)->toBe('manager');
});

it('promotes an existing team member into the vacated manager seat', function () {
    $team = Team::factory()->create();
    $leaver = userWithRole('supervisor');
    $team->members()->attach($leaver->id, ['role_in_team' => 'manager']);

    $successor = userWithRole('supervisor');
    $team->members()->attach($successor->id, ['role_in_team' => 'member']);

    $this->actingAs(userWithRole('admin'))
        ->post("/users/{$leaver->id}/offboard", ['successor_id' => $successor->id]);

    expect($team->fresh()->members()->where('users.id', $successor->id)
        ->first()?->pivot?->role_in_team)->toBe('manager');
});

// --- Successor eligibility -----------------------------------------------------

it('refuses a successor with a different role', function () {
    [$manager, $leaver] = offboardFixture();
    $wrongType = userWithRole('cs');
    $manager->team()?->members()->attach($wrongType->id, ['role_in_team' => 'cs']);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $wrongType->id])
        ->assertSessionHasErrors('successor_id');

    expect($leaver->fresh()->is_active)->toBeTrue();
});

it('refuses a successor from another team', function () {
    [$manager, $leaver] = offboardFixture();
    [, , $outsider] = offboardFixture(); // same role, different team

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $outsider->id])
        ->assertSessionHasErrors('successor_id');

    expect($leaver->fresh()->is_active)->toBeTrue();
});

it('refuses a deactivated successor', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    $successor->forceFill(['is_active' => false])->save();

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id])
        ->assertSessionHasErrors('successor_id');
});

it('accepts a teamless successor and joins them to the team', function () {
    [$manager, $leaver, , $team] = offboardFixture();
    $teamless = userWithRole('sales');

    expect(UserOffboarding::eligibleSuccessors($leaver)->pluck('id')->all())
        ->toContain($teamless->id);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $teamless->id]);

    expect($team->fresh()->members()->pluck('users.id')->all())->toContain($teamless->id);
});

it('never offers the leaver themselves as a successor', function () {
    [, $leaver] = offboardFixture();

    expect(UserOffboarding::eligibleSuccessors($leaver)->pluck('id')->all())
        ->not->toContain($leaver->id);
});

// --- Guards --------------------------------------------------------------------

it('blocks deleting a user who still holds a book, naming what is in the way', function () {
    [, $leaver] = offboardFixture();
    customerFor($leaver);

    $this->actingAs(userWithRole('admin'))
        ->delete("/users/{$leaver->id}")
        ->assertSessionHas('error');

    expect(User::find($leaver->id))->not->toBeNull();
    expect(session('error'))->toContain('customer');
});

it('blocks deleting a manager who still leads a team', function () {
    $team = Team::factory()->create();
    $leaver = userWithRole('supervisor');
    $team->members()->attach($leaver->id, ['role_in_team' => 'manager']);

    $this->actingAs(userWithRole('admin'))
        ->delete("/users/{$leaver->id}")
        ->assertSessionHas('error');

    expect(User::find($leaver->id))->not->toBeNull();
    expect(session('error'))->toContain('tim');
});

it('allows deleting once the user has been offboarded', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    customerFor($leaver);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id]);

    $this->actingAs(userWithRole('admin'))
        ->delete("/users/{$leaver->id}")
        ->assertRedirect();

    expect(User::find($leaver->id))->toBeNull();
});

it('refuses a manager offboarding someone outside their reach', function () {
    [$manager] = offboardFixture();
    [, $outsider, $outsiderSuccessor] = offboardFixture();

    $this->actingAs($manager)
        ->post("/users/{$outsider->id}/offboard", ['successor_id' => $outsiderSuccessor->id])
        ->assertForbidden();

    $this->actingAs($manager)
        ->get("/team/members/{$outsider->id}/offboard")
        ->assertForbidden();

    expect($outsider->fresh()->is_active)->toBeTrue();
});

it('never lets a user offboard themselves', function () {
    $admin = userWithRole('admin');
    $other = userWithRole('admin');

    $this->actingAs($admin)
        ->post("/users/{$admin->id}/offboard", ['successor_id' => $other->id])
        ->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

// --- Screen + audit ------------------------------------------------------------

it('shows the holdings and the eligible successors before anything moves', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    customerFor($leaver);
    customerFor($leaver);

    $this->actingAs($manager)
        ->get("/team/members/{$leaver->id}/offboard")
        ->assertInertia(fn ($page) => $page
            ->component('Offboard/Show')
            ->where('user.id', $leaver->id)
            ->where('holdings.customers', 2)
            ->where('successors.0.id', $successor->id));

    // Nothing moved just by looking at the screen.
    expect($leaver->fresh()->is_active)->toBeTrue()
        ->and(Customer::where('assigned_to', $leaver->id)->count())->toBe(2);
});

it('audits the transfer with the successor and what moved', function () {
    [$manager, $leaver, $successor] = offboardFixture();
    customerFor($leaver);

    $this->actingAs($manager)
        ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id]);

    $log = AuditLog::where('action', 'user.offboarded')->where('target_user_id', $leaver->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($manager->id)
        ->and($log->changes['successor']['id'])->toBe($successor->id)
        ->and($log->changes['transferred']['customers'])->toBe(1);
});
