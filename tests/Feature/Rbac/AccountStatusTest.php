<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Support\AccountStatus;
use App\Support\HierarchyResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

/**
 * Batch H7b (DESIGN_HIERARCHY.md) — the account lifecycle switch.
 *
 * The ketokan under test: deactivation revokes ACCESS and touches NOTHING ELSE.
 * So each blocking case is paired with a proof that the user's data survived, and
 * with a reactivation that restores the account whole — because a switch that is
 * advertised as reversible is only safe if it really is lossless.
 *
 * Also pinned here: the lockout guards (never self, never the last active admin)
 * and the fact that BOTH login paths are sealed, not just the password form.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A manager leading a team, plus a sales member of that team provisioned by them —
 * the reach UserPolicy::manageTeamMember grants.
 *
 * @return array{0: User, 1: User, 2: Team}
 */
function managerWithMember(string $type = 'sales'): array
{
    $manager = userWithRole('supervisor');
    $team = Team::factory()->create();
    $team->members()->attach($manager->id, ['role_in_team' => 'manager']);

    $member = userWithRole($type);
    $member->forceFill(['created_by_user' => $manager->id])->save();
    $team->members()->attach($member->id, ['role_in_team' => $type]);

    return [$manager, $member, $team];
}

/**
 * A stored passkey belonging to $user — enough for the authorize hook, which only
 * reads $passkey->user.
 */
function passkeyFor(User $user): Passkey
{
    $passkey = new Passkey;
    $passkey->forceFill([
        'user_id' => $user->id,
        'name' => 'test key',
        'credential_id' => Str::random(32),
        'credential' => ['id' => Str::random(16)],
    ])->save();

    return $passkey;
}

// --- The switch blocks access ------------------------------------------------

it('blocks login for a deactivated account', function () {
    $user = User::factory()->inactive()->create(['email' => 'off@crm.test']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('still lets an active account log in', function () {
    $user = User::factory()->create(['email' => 'on@crm.test']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

it('blocks a deactivated account that has two-factor enabled before the challenge', function () {
    // The 2FA gate validates credentials through the SAME Fortify callback, so an
    // inactive user must never even reach the challenge screen.
    $user = User::factory()->withTwoFactor()->inactive()->create(['email' => '2fa@crm.test']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('login.id');

    $this->assertGuest();
});

it('refuses a passkey login for a deactivated account', function () {
    // Passkeys bypass the Fortify pipeline entirely (PasskeyLoginController calls
    // guard->login() directly), so they need their OWN seal — this asserts that
    // second hook is armed, which no password-login test would ever catch.
    $off = passkeyFor(User::factory()->inactive()->create());
    $on = passkeyFor(User::factory()->create());

    expect(Passkeys::allowsLogin(request(), $off))->toBeFalse()
        ->and(Passkeys::allowsLogin(request(), $on))->toBeTrue();
});

it('ends a live session as soon as the account is deactivated', function () {
    $user = userWithRole('sales');

    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

// --- The data stays put -------------------------------------------------------

it('leaves customers and support assignments untouched when deactivating', function () {
    [$manager, $sales] = managerWithMember();
    $support = userWithRole('cs');
    $sales->assignees()->attach($support->id);

    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $sales->id, 'assigned_to' => $sales->id])->save();

    $this->actingAs($manager)
        ->put("/team/members/{$sales->id}/status", ['is_active' => false])
        ->assertRedirect();

    expect($sales->fresh()->is_active)->toBeFalse()
        ->and($customer->fresh()->assigned_to)->toBe($sales->id)
        ->and($customer->fresh()->created_by)->toBe($sales->id)
        ->and($sales->assignees()->pluck('users.id')->all())->toBe([$support->id]);
});

it('restores full access on reactivation', function () {
    [$manager, $sales] = managerWithMember();
    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $sales->id])->save();

    $this->actingAs($manager)->put("/team/members/{$sales->id}/status", ['is_active' => false]);
    $this->actingAs($manager)->put("/team/members/{$sales->id}/status", ['is_active' => true]);

    $sales = $sales->fresh();
    expect($sales->is_active)->toBeTrue()
        ->and(Customer::query()->visibleTo($sales)->pluck('id')->all())->toContain($customer->id);

    // Drop the manager's session before proving the member can sign in again.
    auth()->logout();
    $this->flushSession();

    $this->post('/login', ['email' => $sales->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($sales);
});

// --- Who may flip it ----------------------------------------------------------

it('lets a manager deactivate their own team member', function () {
    [$manager, $member] = managerWithMember();

    $this->actingAs($manager)
        ->put("/team/members/{$member->id}/status", ['is_active' => false])
        ->assertRedirect();

    expect($member->fresh()->is_active)->toBeFalse();
});

it('refuses a manager reaching a member of another team', function () {
    [$manager] = managerWithMember();
    [, $outsider] = managerWithMember();

    $this->actingAs($manager)
        ->put("/team/members/{$outsider->id}/status", ['is_active' => false])
        ->assertForbidden();

    expect($outsider->fresh()->is_active)->toBeTrue();
});

it('refuses a manager reaching a peer manager', function () {
    [$manager, , $team] = managerWithMember();
    $peer = userWithRole('supervisor');
    $team->members()->attach($peer->id, ['role_in_team' => 'manager']);

    $this->actingAs($manager)
        ->put("/team/members/{$peer->id}/status", ['is_active' => false])
        ->assertForbidden();

    expect($peer->fresh()->is_active)->toBeTrue();
});

it('lets an admin deactivate anyone', function () {
    $admin = userWithRole('admin');
    $sales = userWithRole('sales');

    $this->actingAs($admin)
        ->put("/users/{$sales->id}/status", ['is_active' => false])
        ->assertRedirect();

    expect($sales->fresh()->is_active)->toBeFalse();
});

it('refuses a sales rep the switch entirely', function () {
    $sales = userWithRole('sales');
    $other = userWithRole('cs');

    $this->actingAs($sales)
        ->put("/users/{$other->id}/status", ['is_active' => false])
        ->assertForbidden();

    $this->actingAs($sales)
        ->put("/team/members/{$other->id}/status", ['is_active' => false])
        ->assertForbidden();

    expect($other->fresh()->is_active)->toBeTrue();
});

// --- Lockout guards -----------------------------------------------------------

it('never lets a user deactivate themselves', function () {
    $admin = userWithRole('admin');
    User::factory()->create()->assignRole(RoleName::Admin->value); // a second admin

    $this->actingAs($admin)
        ->put("/users/{$admin->id}/status", ['is_active' => false])
        ->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('counts only ACTIVE administrators in the lockout guard', function () {
    $admin = userWithRole('admin');
    $spare = userWithRole('admin');

    expect(AccountStatus::isLastAdmin($admin))->toBeFalse();

    $spare->forceFill(['is_active' => false])->save();

    // With the spare already switched off, the remaining one is the LAST ACTIVE
    // administrator — counting rows alone would have missed this and allowed a
    // lockout by switching them off one at a time.
    expect(AccountStatus::isLastAdmin($admin))->toBeTrue();
});

it('measures admin POWER, not the role label', function () {
    // H7e: system roles keep role_has_permissions empty, so a user holding the
    // `admin` ROLE without its permissions can administer nothing. A guard that
    // counted role members would treat this powerless stand-in as a second admin
    // and let the real one be removed — locking everyone out of /users and /roles.
    $admin = userWithRole('admin');
    $standIn = User::factory()->create();
    $standIn->syncRoles([RoleName::Admin->value]); // role only, no permissions

    expect($standIn->can(PermissionName::PermissionAssign->value))->toBeFalse()
        ->and(AccountStatus::isLastAdmin($admin))->toBeTrue();
});

it('blocks the route when the target is the only active administrator', function () {
    $target = userWithRole('admin');

    // An actor who may edit users but holds no grant power — enough to reach the
    // route, not enough to replace the administrator they are switching off.
    $actor = userWithRole('supervisor');
    $actor->givePermissionTo(PermissionName::UserUpdate->value);

    $this->actingAs($actor)
        ->put("/users/{$target->id}/status", ['is_active' => false])
        ->assertSessionHas('error');

    expect($target->fresh()->is_active)->toBeTrue();
});

it('blocks demoting the last administrator, and re-stamps the preset on a role change', function () {
    // The role change was the only lifecycle path without the lockout guard, and it
    // changed the LABEL without the abilities — the two defects that combined into
    // the lockout.
    $admin = userWithRole('admin');
    $actor = userWithRole('admin');

    $this->actingAs($actor)->put("/users/{$admin->id}", [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'sales',
    ])->assertRedirect();

    expect($admin->fresh()->hasRole('sales'))->toBeTrue()
        ->and($admin->fresh()->can(PermissionName::CustomerViewOwn->value))->toBeTrue()
        ->and($admin->fresh()->can(PermissionName::PermissionAssign->value))->toBeFalse();

    // $actor is now the last administrator — nobody may demote them.
    $other = userWithRole('supervisor');
    $other->givePermissionTo([PermissionName::UserUpdate->value, PermissionName::RoleAssign->value]);

    $this->actingAs($other)->put("/users/{$actor->id}", [
        'name' => $actor->name, 'email' => $actor->email, 'role' => 'sales',
    ])->assertSessionHas('error');

    expect($actor->fresh()->hasRole(RoleName::Admin->value))->toBeTrue();
});

// --- Downstream effects -------------------------------------------------------

it('stops offering a deactivated agent as a new assignment candidate', function () {
    [$sales, $team] = [userWithRole('sales'), Team::factory()->create()];
    $team->members()->attach($sales->id, ['role_in_team' => 'sales']);
    $agent = userWithRole('cs');
    $team->members()->attach($agent->id, ['role_in_team' => 'cs']);

    expect(HierarchyResolver::supportCandidateIds($sales, ['cs']))->toContain($agent->id);

    $agent->forceFill(['is_active' => false])->save();

    expect(HierarchyResolver::supportCandidateIds($sales, ['cs']))->not->toContain($agent->id);
});

it('keeps an EXISTING assignment to a deactivated agent (reversible, not rewritten)', function () {
    $sales = userWithRole('sales');
    $agent = userWithRole('cs');
    $sales->assignees()->attach($agent->id);

    $agent->forceFill(['is_active' => false])->save();

    expect($sales->fresh()->assignees()->pluck('users.id')->all())->toBe([$agent->id]);
});

it('flags a deactivated member in the Tim Saya overview', function () {
    [$manager, $sales] = managerWithMember();
    $sales->forceFill(['is_active' => false])->save();

    $this->actingAs($manager)->get('/team')
        ->assertInertia(fn ($page) => $page
            ->where('kind', 'manager')
            ->where('reps.0.id', $sales->id)
            ->where('reps.0.is_active', false));
});

it('audits both directions', function () {
    [$manager, $member] = managerWithMember();

    $this->actingAs($manager)->put("/team/members/{$member->id}/status", ['is_active' => false]);
    $this->actingAs($manager)->put("/team/members/{$member->id}/status", ['is_active' => true]);

    expect(AuditLog::where('target_user_id', $member->id)->pluck('action')->all())
        ->toBe(['user.deactivated', 'user.reactivated']);
});

it('does not log a no-op flip', function () {
    [$manager, $member] = managerWithMember();

    $this->actingAs($manager)->put("/team/members/{$member->id}/status", ['is_active' => true]);

    expect(AuditLog::where('target_user_id', $member->id)->count())->toBe(0);
});
