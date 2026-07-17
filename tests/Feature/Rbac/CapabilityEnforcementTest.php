<?php

use App\Enums\PermissionName as P;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\DelegatedUserCreator;
use App\Support\SupportAssignments;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Batch H2 (DESIGN_HIERARCHY.md) — delegated capability enforcement. A manager may
 * CREATE only whitelisted, NON-privileged team members; a sales user may ASSIGN
 * only existing CS/maintenance. This is a SECURITY boundary — these tests exist to
 * prove delegated creation/assignment cannot become a privilege-escalation vector.
 * (Backend logic only; the create/assign UIs land in H4/H5.)
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
function managerInTeam(): array
{
    $manager = userWithRole('supervisor');
    $team = Team::factory()->create();
    $team->members()->attach($manager->id, ['role_in_team' => 'manager']);

    return [$manager, $team];
}

/**
 * @return array{name: string, email: string, password: string}
 */
function memberData(string $email): array
{
    return ['name' => 'Baru '.$email, 'email' => $email, 'password' => 'password123'];
}

// --- Delegated creation: allowed types ---------------------------------------

it('lets a manager create whitelisted members with team + created_by trail', function (string $type) {
    [$manager, $team] = managerInTeam();

    $user = DelegatedUserCreator::create($manager, $type, memberData("{$type}@team.test"));

    expect($user->hasRole($type))->toBeTrue()
        ->and($user->created_by_user)->toBe($manager->id)
        ->and($user->team()?->id)->toBe($team->id);
})->with(['sales', 'cs', 'maintenance']);

it('applies the role preset, not manager-chosen permissions, to a delegated user', function () {
    [$manager] = managerInTeam();

    $sales = DelegatedUserCreator::create($manager, 'sales', memberData('sales@team.test'));

    // Exactly the sales preset — a delegate never sets permissions.
    expect($sales->can(P::CustomerViewOwn->value))->toBeTrue()
        ->and($sales->can(P::CustomerViewAll->value))->toBeFalse()
        ->and($sales->can(P::RevenueView->value))->toBeFalse();
});

// --- Delegated creation: escalation blocked ----------------------------------

it('blocks a manager from creating a privileged role (escalation)', function (string $type) {
    [$manager] = managerInTeam();

    expect(fn () => DelegatedUserCreator::create($manager, $type, memberData("{$type}@x.test")))
        ->toThrow(AuthorizationException::class);

    expect(User::where('email', "{$type}@x.test")->exists())->toBeFalse();
})->with(['admin', 'supervisor']);

it('reports a manager cannot create privileged types nor set permissions', function () {
    [$manager] = managerInTeam();
    $someone = userWithRole('sales');

    expect($manager->canCreateUserType('sales'))->toBeTrue()
        ->and($manager->canCreateUserType('cs'))->toBeTrue()
        ->and($manager->canCreateUserType('admin'))->toBeFalse()
        ->and($manager->canCreateUserType('supervisor'))->toBeFalse()
        // No permission.assign → cannot toggle anyone's permissions.
        ->and($manager->can('managePermissions', $someone))->toBeFalse();
});

// --- Sales cannot create at all ----------------------------------------------

it('blocks a sales user from creating any user', function () {
    $sales = userWithRole('sales');

    expect($sales->canCreateUserType('cs'))->toBeFalse()      // no user.create
        ->and($sales->canCreateUserType('sales'))->toBeFalse();

    expect(fn () => DelegatedUserCreator::create($sales, 'cs', memberData('nope@x.test')))
        ->toThrow(AuthorizationException::class);
});

// --- Assignment (DH5) --------------------------------------------------------

it('lets a sales user assign existing CS/maintenance to themselves', function (string $type) {
    $sales = userWithRole('sales');
    $assignee = userWithRole($type);

    SupportAssignments::assign($sales, $assignee);

    expect($sales->assignees()->pluck('users.id')->all())->toContain($assignee->id)
        ->and($assignee->assignedSalesFor()->pluck('users.id')->all())->toContain($sales->id);
})->with(['cs', 'maintenance']);

it('blocks a sales user from assigning a non-support type', function (string $type) {
    $sales = userWithRole('sales');
    $other = userWithRole($type);

    expect($sales->canAssign($type))->toBeFalse();

    expect(fn () => SupportAssignments::assign($sales, $other))
        ->toThrow(AuthorizationException::class);

    expect($sales->assignees()->count())->toBe(0);
})->with(['sales', 'admin', 'supervisor']);

// --- No whitelist => no capability -------------------------------------------

it('denies create/assign to a role with an explicit empty assignable_types', function () {
    // A custom role that CAN create/assign, but whitelists nothing.
    $role = Role::create(['name' => 'empty-creator', 'guard_name' => 'web']);
    $role->syncPermissions([P::UserCreate->value, P::UserAssign->value]);
    $role->assignable_types = []; // explicit empty — not null "use default"
    $role->save();

    $user = User::factory()->create();
    $user->assignRole('empty-creator');

    expect($user->canCreateUserType('sales'))->toBeFalse()
        ->and($user->canAssign('cs'))->toBeFalse();
});

// --- Admin regression + admin-UI leak seal -----------------------------------

it('keeps admin unrestricted create working while sealing the admin UI from managers', function () {
    $admin = userWithRole('admin');
    $supervisor = userWithRole('supervisor');
    $sales = userWithRole('sales');

    // The admin's unrestricted /users create UI is intact...
    expect($admin->can('create', User::class))->toBeTrue()
        // ...but a manager holding user.create can NOT reach it (needs permission.assign)...
        ->and($supervisor->can('create', User::class))->toBeFalse()
        ->and($sales->can('create', User::class))->toBeFalse();

    // ...and admin is unrestricted for delegated creation too (any type).
    expect($admin->canCreateUserType('supervisor'))->toBeTrue()
        ->and($admin->canCreateUserType('sales'))->toBeTrue();
});

it('exposes the delegated createType policy ability', function () {
    [$manager] = managerInTeam();
    $policy = new UserPolicy;

    expect($policy->createType($manager, 'sales'))->toBeTrue()
        ->and($policy->createType($manager, 'admin'))->toBeFalse();
});

// --- Admin sets assignable_types via the role builder (backend, DH4) ---------

it('lets an admin set a custom role assignable_types via the role builder', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('roles.store'), [
            'name' => 'Marketing',
            'permissions' => [P::UserCreate->value, P::DashboardView->value],
            'assignable_types' => ['cs', 'maintenance'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Marketing')->first();

    expect($role?->assignable_types)->toBe(['cs', 'maintenance']);
});

it('rejects admin as an assignable_type (never delegable)', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('roles.store'), [
            'name' => 'Sneaky',
            'permissions' => [P::UserCreate->value],
            'assignable_types' => ['admin'],
        ])
        ->assertSessionHasErrors('assignable_types.0');

    expect(Role::where('name', 'Sneaky')->exists())->toBeFalse();
});
