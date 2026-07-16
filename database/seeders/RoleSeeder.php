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
     *
     * Idempotent bootstrap of the DEFAULT system roles: firstOrCreate never throws
     * and never clobbers an existing role's data. An admin may rename or delete a
     * non-admin system role via the role builder — that is an intentional
     * divergence they own. Re-running this seeder afterwards re-creates the default
     * slug as an EMPTY role (no permissions, no users); it does NOT touch the
     * renamed role or its members, and RolePresetSeeder never provisions anyone
     * into the empty shell. The admin can simply delete it (it is unused). The
     * production maintenance path is `migrate` + RolePresetSeeder, not this seeder.
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
