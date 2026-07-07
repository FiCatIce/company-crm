<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Application roles gating CRM access (see CustomerController).
     *
     * @var list<string>
     */
    public const ROLES = ['admin', 'supervisor', 'cs'];

    /**
     * Seed the application roles.
     */
    public function run(): void
    {
        // Clear spatie's cached roles/permissions so freshly created rows are visible.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
