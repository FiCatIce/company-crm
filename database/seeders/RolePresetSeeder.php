<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-applies each user's role preset onto their direct permissions. Because
 * roles are templates (permissions live on the user — DESIGN_RBAC.md §3.4),
 * this is how a preset change is rolled out to existing users. Idempotent.
 *
 * New users already get their preset at creation via RolePresets::assign(); this
 * seeder is the standalone "re-sync everyone" maintenance step.
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

            RolePresets::assign($user, RoleName::from($roleName));
        });
    }
}
