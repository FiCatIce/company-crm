<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // User::factory(10)->create();

        $admin = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        RolePresets::assign($admin, RoleName::Admin);

        $this->call(DemoDataSeeder::class);

        // A second Sales rep with their own customers, for exercising RBAC
        // row-scoping (Sales sees only their own book). Idempotent.
        $this->call(DemoSalesScopingSeeder::class);

        // Batch H1 hierarchy foundation (DESIGN_HIERARCHY.md): stamps dormant role
        // capability defaults + wires the seeded agents into an example team and
        // sales<->support assignments. Behavior-neutral (nothing reads it yet).
        $this->call(HierarchySeeder::class);
    }
}
