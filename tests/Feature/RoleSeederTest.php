<?php

use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

it('seeds the admin, supervisor, and cs roles', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::pluck('name')->all())
        ->toContain('admin', 'supervisor', 'cs');
});

it('is idempotent when run more than once', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::count())->toBe(count(RoleSeeder::ROLES));
});
