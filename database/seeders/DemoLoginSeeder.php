<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Predictable one-user-per-role logins for manual QA of the RBAC redesign.
 * All accounts use the password "password". Idempotent: reuses the existing agent
 * with the same extension when present (so it renames the random-email demo agents
 * rather than duplicating them), otherwise creates the account.
 *
 * Not wired into DatabaseSeeder — run on demand: db:seed --class=DemoLoginSeeder.
 */
class DemoLoginSeeder extends Seeder
{
    public function run(): void
    {
        // [email, name, role, PBX extension]
        $logins = [
            ['manager@crm.test', 'Manager Demo', RoleName::Supervisor, '1001'],
            ['cs@crm.test', 'CS Demo', RoleName::Cs, '1002'],
            ['sales@crm.test', 'Sales Demo', RoleName::Sales, '1005'],
            ['maintenance@crm.test', 'Maintenance Demo', RoleName::Maintenance, '1006'],
        ];

        foreach ($logins as [$email, $name, $role, $extension]) {
            $user = User::where('email', $email)->first()
                ?? User::where('extension', $extension)->first()
                ?? new User;

            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'extension' => $extension,
                'email_verified_at' => now(),
            ])->save();

            RolePresets::assign($user, $role);

            // Sales is row-scoped (view.own), so give it a handful of owned
            // customers to make the scoping visible when logging in. Only grab
            // customers nobody has entered yet (created_by IS NULL) — never steal
            // another rep's owned book (e.g. sales2's from DemoSalesScopingSeeder),
            // so the two reps stay cleanly isolated across re-seeds.
            if ($role === RoleName::Sales) {
                Customer::query()
                    ->whereNull('created_by')
                    ->inRandomOrder()
                    ->limit(6)
                    ->update(['assigned_to' => $user->id]);
            }
        }
    }
}
