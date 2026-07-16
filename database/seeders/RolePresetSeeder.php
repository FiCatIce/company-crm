<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-applies each user's role template onto their direct permissions. Because
 * roles are templates (permissions live on the user — DESIGN_RBAC.md §3.4),
 * this is how a template change is rolled out to existing users. Idempotent.
 *
 * New users already get their template at creation via RolePresets::assign();
 * this seeder is the standalone "re-sync everyone" maintenance step.
 *
 * Resilient to admin customization of roles: it re-syncs from each user's role's
 * EFFECTIVE permissions (RolePresets::effectivePermissions) rather than a code
 * preset keyed on the role name. So it does not error on — and correctly honours
 * — a renamed system role or a custom role (both of which have no RoleName enum
 * case). Users with no role are left untouched.
 */
class RolePresetSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::query()->with('roles')->each(function (User $user): void {
            $roleName = $user->getRoleNames()->first();

            if (! is_string($roleName)) {
                return;
            }

            $role = Role::where('name', $roleName)->with('permissions')->first();

            if ($role === null) {
                return;
            }

            $user->syncPermissions(RolePresets::effectivePermissions($role));
        });
    }
}
