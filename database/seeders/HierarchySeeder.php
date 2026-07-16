<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Batch H1 (DESIGN_HIERARCHY.md) — provisions the DORMANT hierarchy foundation:
 *
 *   1. Stamps each system role's default assignable_types (DH4 capability config)
 *      from config/hierarchy.php.
 *   2. Seeds an example team + memberships + sales<->support assignments from the
 *      users that already exist, so later batches have realistic fixtures.
 *
 * Idempotent and behavior-neutral: nothing seeded here is read by any scope,
 * dashboard, or gate yet — that activation lands in H2+.
 */
class HierarchySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoleCapabilities();
        $this->seedDemoTeams();
    }

    /**
     * Stamp default assignable_types onto system roles (DH4). Idempotent update.
     */
    protected function seedRoleCapabilities(): void
    {
        /** @var array<string, list<string>> $defaults */
        $defaults = config('hierarchy.assignable_types', []);

        foreach ($defaults as $roleSlug => $types) {
            $role = Role::where('name', $roleSlug)->first();

            if ($role !== null) {
                $role->assignable_types = $types;
                $role->save();
            }
        }
    }

    /**
     * Seed one demo team and wire existing agents into it. Idempotent:
     * firstOrCreate + syncWithoutDetaching, so a re-run neither duplicates nor
     * throws. No-ops gracefully when there are no sales users yet.
     */
    protected function seedDemoTeams(): void
    {
        $sales = User::role('sales')->orderBy('id')->get();
        $firstSales = $sales->first();

        if ($firstSales === null) {
            return; // nothing meaningful to wire yet
        }

        $type = (string) config('hierarchy.default_team_type', 'team');
        $team = Team::firstOrCreate(['name' => 'Tim Jakarta'], ['type' => $type]);

        // First supervisor leads the team (DH1); all sales join it (DH2 puts each
        // sales in exactly one team — this demo shares a single team).
        $manager = User::role('supervisor')->orderBy('id')->first();
        if ($manager !== null) {
            $team->members()->syncWithoutDetaching([$manager->id => ['role_in_team' => 'manager']]);
        }

        foreach ($sales as $salesUser) {
            $team->members()->syncWithoutDetaching([$salesUser->id => ['role_in_team' => 'sales']]);
        }

        // Assign every CS/Maintenance agent to the first sales (DH5 many-to-many).
        $support = User::role('cs')->orderBy('id')->get()
            ->merge(User::role('maintenance')->orderBy('id')->get());

        foreach ($support as $agent) {
            $firstSales->assignees()->syncWithoutDetaching([$agent->id]);
        }
    }
}
