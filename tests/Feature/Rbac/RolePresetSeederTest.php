<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\RolePresetSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('re-provisions a user that has a role but no direct permissions', function () {
    // The exact pre-B0 shape of an existing environment's users: a role is
    // assigned, but permissions were never synced. This is the transition step.
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    expect($user->getDirectPermissions())->toBeEmpty()
        ->and($user->can(PermissionName::CustomerViewAll->value))->toBeFalse();

    $this->seed(RolePresetSeeder::class);

    $user = $user->fresh();

    expect($user->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RolePresets::permissions(RoleName::Supervisor))->sort()->values()->all())
        // H3: the manager preset is team-scoped now, not global.
        ->and($user->can(PermissionName::CustomerViewTeam->value))->toBeTrue();
});

it('leaves a user without any role untouched', function () {
    $user = User::factory()->create();

    $this->seed(RolePresetSeeder::class);

    expect($user->fresh()->getDirectPermissions())->toBeEmpty();
});

it('re-syncs a renamed system role\'s members without error or resurrecting the slug', function () {
    $user = User::factory()->create();
    RolePresets::assign($user, RoleName::Sales); // direct = sales preset, role = 'sales'

    // Mimic the role builder editing then renaming the sales role: the template is
    // materialized onto the role row, then the role is renamed.
    $sales = Role::findByName('sales');
    $sales->syncPermissions(RolePresets::permissions(RoleName::Sales));
    $sales->name = 'penjualan';
    $sales->save();

    // Previously threw ValueError (RoleName::from('penjualan')). Idempotent re-run.
    $this->seed(RolePresetSeeder::class);
    $this->seed(RolePresetSeeder::class);

    expect(Role::where('name', 'sales')->exists())->toBeFalse()      // not resurrected
        ->and(Role::where('name', 'penjualan')->count())->toBe(1)    // no duplicate
        ->and($user->fresh()->can(PermissionName::CustomerViewOwn->value))->toBeTrue()
        ->and($user->fresh()->can(PermissionName::TransactionViewOwn->value))->toBeTrue();
});

it('re-syncs a custom-role user without error', function () {
    $role = Role::create(['name' => 'Auditor', 'guard_name' => 'web']);
    $role->syncPermissions([PermissionName::ProductView->value]);

    $user = User::factory()->create();
    $user->assignRole('Auditor');

    // Previously threw ValueError (RoleName::from('Auditor')).
    $this->seed(RolePresetSeeder::class);

    expect($user->fresh()->can(PermissionName::ProductView->value))->toBeTrue();
});
