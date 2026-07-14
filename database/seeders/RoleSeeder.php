<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Application roles. Mirror of RoleName::values() — kept as a constant for
     * backwards compatibility (a consistency test asserts they stay in sync).
     * RoleName is the single source of truth.
     *
     * @var list<string>
     */
    public const ROLES = ['admin', 'supervisor', 'sales', 'maintenance', 'cs'];

    /**
     * Seed the application roles (and the permissions they draw from).
     */
    public function run(): void
    {
        // Clear spatie's cached roles/permissions so freshly created rows are visible.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions are a prerequisite for provisioning users from role presets.
        $this->call(PermissionSeeder::class);

        foreach (RoleName::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value]);
        }
    }
}
