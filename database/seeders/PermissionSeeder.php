<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds every permission in the PermissionName enum. Idempotent — safe to run
 * repeatedly. Permissions are attached to USERS (not roles) via RolePresets;
 * this seeder only guarantees the permission rows exist.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
