<?php

use App\Enums\PermissionName as P;
use App\Models\Team;
use App\Models\User;
use App\Support\DelegatedUserCreator;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Batch H4 (DESIGN_HIERARCHY.md) — the delegated team-member UI + routes. The
 * SECURITY invariants of delegated creation are proven at the service layer in
 * CapabilityEnforcementTest (H2); these prove the ROUTE surface honours them: a
 * manager reaches a scoped area, the admin/sales do not, the type dropdown is the
 * escalation-guarded whitelist, and a privileged type cannot be POSTed in.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A supervisor (manager) placed into a team, so delegated creates land somewhere.
 *
 * @return array{0: User, 1: Team}
 */
function delegatedManager(): array
{
    $manager = userWithRole('supervisor');
    $team = Team::factory()->create();
    $team->members()->attach($manager->id, ['role_in_team' => 'manager']);

    return [$manager, $team];
}

/**
 * @return array{name: string, email: string, password: string, password_confirmation: string}
 */
function newMemberPayload(string $email = 'baru@team.test'): array
{
    return [
        'name' => 'Anggota Baru',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];
}

// --- Area access -------------------------------------------------------------

it('lets a manager open the team-members area', function () {
    [$manager] = delegatedManager();

    $this->actingAs($manager)
        ->get(route('team.members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('TeamMembers/Index'));
});

it('offers a manager only their escalation-guarded creatable types', function () {
    [$manager] = delegatedManager();

    $this->actingAs($manager)
        ->get(route('team.members.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TeamMembers/Create')
            ->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['sales', 'cs', 'maintenance']));
});

it('forbids the admin from the delegated team area (their path is /users)', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('team.members.index'))
        ->assertForbidden();
});

it('forbids a sales user (no user.create) from the team area', function () {
    $this->actingAs(userWithRole('sales'))
        ->get(route('team.members.index'))
        ->assertForbidden();
});

it('keeps the admin /users create UI sealed from a manager', function () {
    [$manager] = delegatedManager();

    $this->actingAs($manager)->get(route('users.create'))->assertForbidden();
    $this->actingAs($manager)->post(route('users.store'), [
        ...newMemberPayload('viaadmin@team.test'),
        'role' => 'sales',
    ])->assertForbidden();
});

// --- Creation ---------------------------------------------------------------

it('creates a whitelisted member with the team + created_by trail and preset perms', function () {
    [$manager, $team] = delegatedManager();

    $this->actingAs($manager)
        ->post(route('team.members.store'), [...newMemberPayload('sales@team.test'), 'type' => 'sales'])
        ->assertRedirect(route('team.members.index'));

    $member = User::where('email', 'sales@team.test')->firstOrFail();

    expect($member->hasRole('sales'))->toBeTrue()
        ->and($member->created_by_user)->toBe($manager->id)
        ->and($member->team()?->id)->toBe($team->id)
        // Exactly the sales preset — a delegate never sets permissions.
        ->and($member->can(P::CustomerViewOwn->value))->toBeTrue()
        ->and($member->can(P::CustomerViewAll->value))->toBeFalse()
        ->and($member->can(P::RevenueView->value))->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $manager->id,
        'target_user_id' => $member->id,
        'action' => 'user.created.delegated',
    ]);
});

it('rejects a privileged type at the route (never a dropdown value)', function (string $type) {
    [$manager] = delegatedManager();

    $this->actingAs($manager)
        ->from(route('team.members.create'))
        ->post(route('team.members.store'), [...newMemberPayload("{$type}@x.test"), 'type' => $type])
        ->assertSessionHasErrors('type');

    expect(User::where('email', "{$type}@x.test")->exists())->toBeFalse();
})->with(['admin', 'supervisor']);

it('requires a type', function () {
    [$manager] = delegatedManager();

    $this->actingAs($manager)
        ->from(route('team.members.create'))
        ->post(route('team.members.store'), newMemberPayload())
        ->assertSessionHasErrors('type');
});

// --- List scoping ------------------------------------------------------------

it('lists only the members within the manager reach, not other teams', function () {
    [$manager] = delegatedManager();
    $mine = DelegatedUserCreator::create($manager, 'sales', ['name' => 'Mine', 'email' => 'mine@team.test', 'password' => 'password123']);

    // A member of a different manager/team must not surface.
    [$other] = delegatedManager();
    $theirs = DelegatedUserCreator::create($other, 'cs', ['name' => 'Theirs', 'email' => 'theirs@team.test', 'password' => 'password123']);

    $this->actingAs($manager)
        ->get(route('team.members.index'))
        ->assertInertia(fn (Assert $page) => $page->where('members.data', fn ($rows) => collect($rows)->pluck('id')->contains($mine->id)
            && ! collect($rows)->pluck('id')->contains($theirs->id)));
});

// --- Password reset ----------------------------------------------------------

it('lets a manager reset a password for a member in their reach', function () {
    [$manager] = delegatedManager();
    $member = DelegatedUserCreator::create($manager, 'sales', ['name' => 'Mine', 'email' => 'mine@team.test', 'password' => 'password123']);

    $this->actingAs($manager)
        ->put(route('team.members.password', $member), ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])
        ->assertRedirect(route('team.members.index'));

    expect(Hash::check('newpass123', $member->refresh()->password))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $manager->id,
        'target_user_id' => $member->id,
        'action' => 'user.password.reset.delegated',
    ]);
});

it('forbids resetting a member outside the manager reach', function () {
    [$manager] = delegatedManager();
    [$other] = delegatedManager();
    $theirs = DelegatedUserCreator::create($other, 'sales', ['name' => 'Theirs', 'email' => 'theirs@team.test', 'password' => 'password123']);

    $this->actingAs($manager)
        ->put(route('team.members.password', $theirs), ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])
        ->assertForbidden();

    expect(Hash::check('newpass123', $theirs->refresh()->password))->toBeFalse();
});

it('forbids a manager resetting an admin or supervisor password', function (string $role) {
    [$manager] = delegatedManager();
    $target = userWithRole($role);

    $this->actingAs($manager)
        ->put(route('team.members.password', $target), ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])
        ->assertForbidden();
})->with(['admin', 'supervisor']);

// --- Shared capability flag (nav gating contract) ----------------------------

it('shares manageTeamMembers = true for a manager, false for admin and sales', function () {
    [$manager] = delegatedManager();

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageTeamMembers', true));

    $this->actingAs(userWithRole('admin'))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageTeamMembers', false));

    $this->actingAs(userWithRole('sales'))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageTeamMembers', false));
});
