<?php

use App\Enums\PermissionName;
use App\Models\LabelOverride;
use App\Support\TeamRoleLabels;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * L3-A — white-label label registry (ketok D1/D2/D4). Resolution order,
 * the un-overridable admin identity, the acronym-aware sentence form, cache
 * invalidation on every override write, and the Inertia share every Vue page
 * will read from in L3-B. The setting.labels permission ships dormant.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Resolution: override → config default → headline fallback
// ---------------------------------------------------------------------------

it('resolves the config default when no override exists', function () {
    expect(TeamRoleLabels::label('sales'))->toBe('Sales')
        ->and(TeamRoleLabels::label('supervisor'))->toBe('Manager')
        ->and(TeamRoleLabels::label('cs'))->toBe('CS');
});

it('lets a db override win over the config default', function () {
    LabelOverride::create(['key' => 'sales', 'label' => 'Account Manager']);

    expect(TeamRoleLabels::label('sales'))->toBe('Account Manager')
        // The others keep their defaults — the map merges, never replaces.
        ->and(TeamRoleLabels::label('cs'))->toBe('CS');
});

it('headline-cases an unknown slug so a custom role never renders blank', function () {
    expect(TeamRoleLabels::label('regional-manager'))->toBe('Regional Manager');
});

it('never applies an override to the admin identity', function () {
    LabelOverride::create(['key' => 'admin', 'label' => 'Superuser']);

    expect(TeamRoleLabels::label('admin'))->toBe('Admin')
        ->and(TeamRoleLabels::all()['admin'])->toBe('Admin');
});

// ---------------------------------------------------------------------------
// Sentence-context form (D4): lowercase words, acronyms survive
// ---------------------------------------------------------------------------

it('lowercases the sentence form but preserves all-caps acronyms', function () {
    expect(TeamRoleLabels::labelLower('sales'))->toBe('sales')
        ->and(TeamRoleLabels::labelLower('cs'))->toBe('CS')
        ->and(TeamRoleLabels::labelLower('supervisor'))->toBe('manager');

    LabelOverride::create(['key' => 'sales', 'label' => 'Account Manager']);

    expect(TeamRoleLabels::labelLower('sales'))->toBe('account manager');

    LabelOverride::create(['key' => 'maintenance', 'label' => 'IT Support']);

    expect(TeamRoleLabels::labelLower('maintenance'))->toBe('IT support');
});

// ---------------------------------------------------------------------------
// Cache: reads are cached, every write invalidates
// ---------------------------------------------------------------------------

it('reflects create, update, and delete of an override immediately', function () {
    // Prime the cache + request memo.
    expect(TeamRoleLabels::label('sales'))->toBe('Sales');

    $override = LabelOverride::create(['key' => 'sales', 'label' => 'Account Manager']);
    expect(TeamRoleLabels::label('sales'))->toBe('Account Manager');

    $override->update(['label' => 'Relationship Manager']);
    expect(TeamRoleLabels::label('sales'))->toBe('Relationship Manager');

    $override->delete();
    expect(TeamRoleLabels::label('sales'))->toBe('Sales');
});

it('caches the override map so repeated reads do not re-query', function () {
    TeamRoleLabels::label('sales');

    expect(Cache::has(TeamRoleLabels::CACHE_KEY))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Inertia share — the single source Vue copy reads from (L3-B target)
// ---------------------------------------------------------------------------

it('shares resolved labels in both forms with every inertia page', function () {
    LabelOverride::create(['key' => 'sales', 'label' => 'Account Manager']);

    $this->actingAs(userWithRole('sales'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('labels.roles.sales', 'Account Manager')
            ->where('labels.roles.cs', 'CS')
            ->where('labels.roles.supervisor', 'Manager')
            ->where('labels.rolesLower.sales', 'account manager')
            ->where('labels.rolesLower.cs', 'CS'));
});

// ---------------------------------------------------------------------------
// setting.labels — dormant: seeded, admin-only, gating nothing yet
// ---------------------------------------------------------------------------

it('gives setting.labels to admin alone, dormant', function () {
    expect(userWithRole('admin')->can(PermissionName::SettingLabels->value))->toBeTrue();

    foreach (['supervisor', 'sales', 'cs', 'maintenance'] as $role) {
        expect(userWithRole($role)->can(PermissionName::SettingLabels->value))->toBeFalse();
    }
});
