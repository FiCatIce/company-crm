<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Support\RolePresets;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('seeds exactly the permissions declared in the enum', function () {
    expect(Permission::pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionName::values())->sort()->values()->all());
});

it('seeds exactly the roles declared in the enum', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleName::values())->sort()->values()->all());
});

it('keeps RoleSeeder::ROLES in sync with the RoleName enum (drift guard)', function () {
    expect(RoleSeeder::ROLES)->toBe(RoleName::values());
});

it('gives every role a non-empty preset of valid permissions', function () {
    $valid = PermissionName::values();

    foreach (RoleName::cases() as $role) {
        $preset = RolePresets::permissions($role);

        expect($preset)->not->toBeEmpty()
            ->and(array_diff($preset, $valid))->toBe([]);
    }
});

it('grants user-management only to admin', function () {
    expect(RolePresets::permissions(RoleName::Admin))->toContain(PermissionName::PermissionAssign->value)
        ->and(RolePresets::permissions(RoleName::Supervisor))->not->toContain(PermissionName::PermissionAssign->value)
        ->and(RolePresets::permissions(RoleName::Cs))->not->toContain(PermissionName::UserView->value);
});

it('provisions a user with its role preset as direct permissions', function () {
    $user = userWithRole('cs');

    expect($user->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RolePresets::permissions(RoleName::Cs))->sort()->values()->all())
        ->and($user->hasRole('cs'))->toBeTrue();
});

it('leaves role_has_permissions empty (roles are templates, not carriers)', function () {
    // Permissions live on users (direct), never on roles — decision D6-A.
    foreach (Role::all() as $role) {
        expect($role->permissions)->toBeEmpty();
    }
});
