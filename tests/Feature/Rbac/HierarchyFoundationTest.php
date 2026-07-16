<?php

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoleLabels;
use Database\Seeders\HierarchySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/*
 * Batch H1 (DESIGN_HIERARCHY.md) — proves the hierarchy foundation exists and its
 * relations/labels/capabilities work, WITHOUT activating any behavior. The rest of
 * the suite staying green is the proof that H1 changed no existing behavior.
 */

// --- Schema: additive, nullable, dormant --------------------------------------

it('creates the dormant hierarchy schema', function () {
    expect(Schema::hasTable('teams'))->toBeTrue()
        ->and(Schema::hasTable('team_user'))->toBeTrue()
        ->and(Schema::hasTable('sales_assignee'))->toBeTrue()
        ->and(Schema::hasColumns('teams', ['name', 'type', 'parent_id']))->toBeTrue()
        ->and(Schema::hasColumn('team_user', 'role_in_team'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'created_by_user'))->toBeTrue()
        ->and(Schema::hasColumn('roles', 'assignable_types'))->toBeTrue();
});

it('lets a user exist with no team and no creator (nullable/dormant)', function () {
    $user = User::factory()->create();

    expect($user->teams()->count())->toBe(0)
        ->and($user->team())->toBeNull()
        ->and($user->created_by_user)->toBeNull();
});

it('defaults a team type to team', function () {
    $team = Team::create(['name' => 'Tim Uji']);

    // The default lives on the column, so read it back from the DB.
    expect($team->fresh()->type)->toBe('team');
});

// --- Relations ----------------------------------------------------------------

it('relates teams and members both ways', function () {
    $team = Team::factory()->create();
    $manager = User::factory()->create();
    $sales = User::factory()->create();

    $team->members()->attach([
        $manager->id => ['role_in_team' => 'manager'],
        $sales->id => ['role_in_team' => 'sales'],
    ]);

    expect($team->members()->count())->toBe(2)
        ->and($sales->teams()->pluck('teams.id')->all())->toContain($team->id)
        ->and($sales->team()?->id)->toBe($team->id)
        ->and($team->members()->find($manager->id)?->getAttribute('pivot')->role_in_team)->toBe('manager');
});

it('relates a parent/child team (L4 hook)', function () {
    $region = Team::factory()->create(['type' => 'region']);
    $child = Team::factory()->create(['parent_id' => $region->id]);

    expect($child->parent?->id)->toBe($region->id)
        ->and($region->children()->pluck('id')->all())->toContain($child->id);
});

it('relates a sales user to assigned CS/maintenance both ways (DH5)', function () {
    $sales = User::factory()->create();
    $cs = User::factory()->create();

    $sales->assignees()->attach($cs->id);

    expect($sales->assignees()->pluck('users.id')->all())->toContain($cs->id)
        ->and($cs->assignedSalesFor()->pluck('users.id')->all())->toContain($sales->id);
});

it('records who created a user (delegated trail)', function () {
    $manager = User::factory()->create();
    $created = User::factory()->create(['created_by_user' => $manager->id]);

    expect($created->createdByUser?->id)->toBe($manager->id)
        ->and($manager->createdUsers()->pluck('id')->all())->toContain($created->id);
});

// --- Naming registry (L3 white-label hook) ------------------------------------

it('resolves every team-role label from one place', function () {
    expect(TeamRoleLabels::label('sales'))->toBe('Sales')
        ->and(TeamRoleLabels::label('supervisor'))->toBe('Manager')
        ->and(TeamRoleLabels::label('cs'))->toBe('CS')
        ->and(TeamRoleLabels::label('maintenance'))->toBe('Maintenance')
        ->and(TeamRoleLabels::label('admin'))->toBe('Admin')
        // Unknown/custom slug never renders blank — headline fallback.
        ->and(TeamRoleLabels::label('marketing'))->toBe('Marketing');
});

// --- Dormant capability + permissions (DH4) -----------------------------------

it('seeds dormant capability defaults and the new permissions', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(HierarchySeeder::class);

    $supervisor = Role::where('name', 'supervisor')->first();
    $sales = Role::where('name', 'sales')->first();

    expect($supervisor?->assignable_types)->toBe(['sales', 'cs', 'maintenance'])
        ->and($sales?->assignable_types)->toBe(['cs', 'maintenance']);

    // The new permissions exist (seeded) but are dormant — attached to no preset,
    // so no role/user actually holds them yet.
    expect(Permission::where('name', PermissionName::UserAssign->value)->exists())->toBeTrue()
        ->and(Permission::where('name', PermissionName::TeamView->value)->exists())->toBeTrue();
});

it('keeps the new permissions out of every role preset (still dormant)', function () {
    $this->seed(RoleSeeder::class);

    $sales = userWithRole('sales');
    $supervisor = userWithRole('supervisor');

    foreach ([$sales, $supervisor] as $user) {
        expect($user->can(PermissionName::TeamView->value))->toBeFalse()
            ->and($user->can(PermissionName::UserAssign->value))->toBeFalse();
    }
});

// --- Idempotency --------------------------------------------------------------

it('is idempotent — re-running the seeder neither duplicates nor throws', function () {
    $this->seed(RoleSeeder::class);
    userWithRole('sales');
    userWithRole('supervisor');
    userWithRole('cs');

    $this->seed(HierarchySeeder::class);
    $this->seed(HierarchySeeder::class);

    expect(Team::where('name', 'Tim Jakarta')->count())->toBe(1);
});
