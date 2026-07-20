<?php

use App\Enums\PermissionName as P;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\CapabilityResolver;
use App\Support\DelegatedUserCreator;
use App\Support\SupportAssignments;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Sweep finding #5 — the delegation guard bounded USER-ADMIN power but not DATA
 * power.
 *
 * The chain: an admin places a custom role carrying customer.view.all into a
 * manager's assignable_types, and the manager can then mint org-wide readers at
 * will. Every cross-team bound built across H1–H7 becomes bypassable through the
 * delegation path — the isolation is real everywhere except the one door that hands
 * out accounts.
 *
 * The fix is the same shape as finding #1 (outranks): you may not hand out what you
 * do not hold. It is enforced twice — at CONFIGURATION time when the whitelist is
 * saved, and at RUNTIME when a user is created or assigned.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A custom role carrying exactly $permissions, wired into supervisor's whitelist —
 * i.e. the state an admin could configure before this guard existed.
 */
function customRoleDelegatedToManagers(string $name, array $permissions): Role
{
    $role = new Role;
    $role->forceFill(['name' => $name, 'guard_name' => 'web'])->save();
    $role->syncPermissions(array_map(fn (P $p): string => $p->value, $permissions));

    $supervisor = Role::where('name', 'supervisor')->firstOrFail();
    $supervisor->forceFill(['assignable_types' => ['sales', 'cs', 'maintenance', $name]])->save();

    return $role;
}

function managerOnTeam(): User
{
    $manager = userWithRole('supervisor');
    Team::factory()->create()->members()->attach($manager->id, ['role_in_team' => 'manager']);

    return $manager;
}

// --- Runtime: a delegate cannot mint a wider reader ---------------------------

it('blocks a manager creating a role that sees the whole org', function (P $power) {
    customRoleDelegatedToManagers('wide-reader', [P::CustomerViewOwn, $power]);

    $manager = managerOnTeam();

    expect($manager->canCreateUserType('wide-reader'))->toBeFalse();

    expect(fn () => DelegatedUserCreator::create($manager, 'wide-reader', [
        'name' => 'Wide', 'email' => 'wide@x.test', 'password' => 'password-123',
    ]))->toThrow(AuthorizationException::class);

    expect(User::where('email', 'wide@x.test')->exists())->toBeFalse();
})->with([
    'all customers' => [P::CustomerViewAll],
    'all transactions (org money)' => [P::TransactionViewAll],
    'all interactions' => [P::InteractionViewAll],
]);

it('keeps the wider role out of the creatable dropdown entirely', function () {
    customRoleDelegatedToManagers('wide-reader', [P::CustomerViewAll]);

    expect(CapabilityResolver::creatableTypes(managerOnTeam()))
        ->not->toContain('wide-reader');
});

it('blocks it through the real delegated-create route', function () {
    customRoleDelegatedToManagers('wide-reader', [P::CustomerViewAll]);

    $this->actingAs(managerOnTeam())->post('/team/members', [
        'type' => 'wide-reader',
        'name' => 'Wide',
        'email' => 'wide@x.test',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ])->assertSessionHasErrors('type');

    expect(User::where('email', 'wide@x.test')->exists())->toBeFalse();
});

it('blocks assigning support whose role sees more than the rep', function () {
    // The same widening by the back door: pulling in an agent who reads the org.
    customRoleDelegatedToManagers('wide-cs', [P::CustomerViewAssigned, P::CustomerViewAll]);

    $salesRole = Role::where('name', 'sales')->firstOrFail();
    $salesRole->forceFill(['assignable_types' => ['cs', 'maintenance', 'wide-cs']])->save();

    $team = Team::factory()->create();
    $sales = userWithRole('sales');
    $wide = User::factory()->create();
    $wide->syncRoles(['wide-cs']);
    $team->members()->attach([
        $sales->id => ['role_in_team' => 'sales'],
        $wide->id => ['role_in_team' => 'cs'],
    ]);

    expect($sales->canAssign('wide-cs'))->toBeFalse();

    expect(fn () => SupportAssignments::assign($sales, $wide))
        ->toThrow(AuthorizationException::class);
});

// --- …without breaking the normal delegated flow ------------------------------

it('still lets a manager create the ordinary team roles', function (string $type) {
    $manager = managerOnTeam();

    $user = DelegatedUserCreator::create($manager, $type, [
        'name' => 'Normal', 'email' => "{$type}@x.test", 'password' => 'password-123',
    ]);

    expect($user->hasRole($type))->toBeTrue();
})->with(['sales', 'cs', 'maintenance']);

it('does not treat view.assigned as wider than view.team', function () {
    // The trap in this fix: CS holds customer.view.assigned, which a manager does
    // NOT hold. Read as a set difference it looks like an escalation, but it is a
    // different axis and narrower in practice — counting it would have blocked
    // managers from creating CS at all and quietly broken H4.
    $manager = managerOnTeam();

    expect($manager->can(P::CustomerViewAssigned->value))->toBeFalse()
        ->and($manager->canCreateUserType('cs'))->toBeTrue()
        ->and($manager->canCreateUserType('maintenance'))->toBeTrue();
});

// --- Configuration time: the whitelist itself ---------------------------------

it('refuses an admin delegating a role that outreaches the grantor', function () {
    $wide = new Role;
    $wide->forceFill(['name' => 'wide-reader', 'guard_name' => 'web'])->save();
    $wide->syncPermissions([P::CustomerViewAll->value]);

    $this->actingAs(userWithRole('admin'))->post('/roles', [
        'name' => 'team-lead',
        'permissions' => [P::CustomerViewTeam->value, P::UserCreate->value],
        'assignable_types' => ['wide-reader'],
    ])->assertSessionHasErrors('assignable_types.0');

    expect(Role::where('name', 'team-lead')->exists())->toBeFalse();
});

it('allows delegating a role whose powers the grantor holds', function () {
    $narrow = new Role;
    $narrow->forceFill(['name' => 'narrow-rep', 'guard_name' => 'web'])->save();
    $narrow->syncPermissions([P::CustomerViewOwn->value]);

    $this->actingAs(userWithRole('admin'))->post('/roles', [
        'name' => 'team-lead',
        'permissions' => [P::CustomerViewTeam->value, P::CustomerViewOwn->value, P::UserCreate->value],
        'assignable_types' => ['narrow-rep'],
    ])->assertSessionHasNoErrors();

    expect(Role::where('name', 'team-lead')->exists())->toBeTrue();
});

it('names the offending power in the error', function () {
    $wide = new Role;
    $wide->forceFill(['name' => 'wide-reader', 'guard_name' => 'web'])->save();
    $wide->syncPermissions([P::TransactionViewAll->value]);

    $response = $this->actingAs(userWithRole('admin'))->post('/roles', [
        'name' => 'team-lead',
        'permissions' => [P::CustomerViewTeam->value],
        'assignable_types' => ['wide-reader'],
    ]);

    $response->assertSessionHasErrors('assignable_types.0');

    $message = session('errors')->get('assignable_types.0')[0];

    // The operator is told WHICH power blocked it, not just that it was refused.
    expect($message)->toContain(P::TransactionViewAll->value)
        ->and($message)->toContain('wide-reader');
});

// --- The rule itself ----------------------------------------------------------

it('reports excess powers in both dimensions', function () {
    $role = new Role;
    $role->forceFill(['name' => 'mixed', 'guard_name' => 'web'])->save();
    $role->syncPermissions([P::CustomerViewAll->value, P::UserDelete->value, P::CustomerViewOwn->value]);

    $excess = CapabilityResolver::excessPowersFor([P::CustomerViewOwn->value], 'mixed');

    expect($excess)->toContain(P::CustomerViewAll->value)   // data dimension
        ->toContain(P::UserDelete->value)                   // user-admin dimension
        ->not->toContain(P::CustomerViewOwn->value);        // held by the grantor
});
