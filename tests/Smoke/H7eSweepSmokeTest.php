<?php

use App\Models\Customer;
use App\Models\User;
use App\Support\AccountStatus;
use App\Support\HierarchyResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * H7e smoke test against the REAL dev database (pgsql), driving the REAL routes.
 * All writes run inside a rolled-back transaction.
 *
 * Run explicitly:  vendor/bin/pest tests/Smoke
 */
uses(TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => 'company_crm',
        'database.connections.pgsql.username' => env('DB_USERNAME', 'postgres'),
        'database.connections.pgsql.password' => env('DB_PASSWORD', ''),
    ]);
    DB::purge('pgsql');
    $this->withoutVite();
});

it('refuses the owner quick-change to someone outside the actor hierarchy', function () {
    DB::beginTransaction();

    try {
        $manager = User::where('email', 'manager@crm.test')->firstOrFail();

        $customer = Customer::query()->visibleTo($manager)->firstOrFail();

        // Someone the manager genuinely cannot reach: not on their team, not them.
        $teamMateIds = HierarchyResolver::teamMemberIds($manager);
        $outsider = User::whereNotIn('id', $teamMateIds)->where('is_active', true)->firstOrFail();

        $before = $customer->assigned_to;

        $this->actingAs($manager)
            ->patch("/customers/{$customer->id}/owner", ['assigned_to' => $outsider->id])
            ->assertSessionHasErrors('assigned_to');

        expect($customer->fresh()->assigned_to)->toBe($before);

        // …and the picker no longer offers them either.
        $props = $this->actingAs($manager)->get("/customers/{$customer->id}/edit")
            ->assertOk()->viewData('page')['props'];
        $offered = array_column($props['users'] ?? [], 'value');

        expect($offered)->not->toContain((string) $outsider->id);

        dump([
            'manager' => $manager->email,
            'customer' => $customer->id,
            'outsider_rejected' => $outsider->email,
            'picker_size' => count($offered),
        ]);
    } finally {
        DB::rollBack();
    }
});

it('measures the last administrator by power on real accounts', function () {
    $admins = User::permission('permission.assign')->where('is_active', true)->get();

    expect($admins)->not->toBeEmpty('dev DB has no active administrator');

    // With more than one active administrator, none of them is "last".
    foreach ($admins as $admin) {
        expect(AccountStatus::isLastAdmin($admin))->toBe($admins->count() === 1);
    }

    dump([
        'active_administrators' => $admins->pluck('email')->all(),
        'lockout_guard_armed' => $admins->count() === 1,
    ]);
});
