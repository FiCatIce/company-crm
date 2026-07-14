<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\RolePresetSeeder;
use Database\Seeders\RoleSeeder;

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
        ->and($user->can(PermissionName::CustomerViewAll->value))->toBeTrue();
});

it('leaves a user without any role untouched', function () {
    $user = User::factory()->create();

    $this->seed(RolePresetSeeder::class);

    expect($user->fresh()->getDirectPermissions())->toBeEmpty();
});
