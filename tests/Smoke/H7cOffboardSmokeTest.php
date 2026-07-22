<?php

use App\Models\Customer;
use App\Models\User;
use App\Support\UserOffboarding;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * H7c smoke test against the REAL dev database (pgsql), driving the REAL routes.
 * Everything runs inside a transaction that is rolled back, so the dev data is
 * left exactly as found even though a full offboard really executes.
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

it('offboards a real rep: book moves, created_by holds, team survives', function () {
    DB::beginTransaction();

    try {
        $manager = User::where('email', 'manager@crm.test')->firstOrFail();

        // H7e: the successor pool is bounded by the ACTOR too, so it must be asked
        // for the same way the route asks — otherwise this harness picks a candidate
        // the route will (correctly) refuse and the offboard silently no-ops.
        $leaver = User::role('sales')->get()
            ->first(fn (User $u): bool => $manager->can('offboard', $u)
                && UserOffboarding::eligibleSuccessors($u, $manager)->isNotEmpty());

        expect($leaver)->not->toBeNull('no offboardable rep with a successor in dev data');

        $successor = UserOffboarding::eligibleSuccessors($leaver, $manager)->first();

        // Give the leaver a real book so the transfer has something to carry.
        $sample = Customer::query()->limit(3)->pluck('id')->all();
        Customer::whereIn('id', $sample)->update(['assigned_to' => $leaver->id]);
        $createdBefore = Customer::whereIn('id', $sample)
            ->pluck('created_by', 'id')->all();

        $team = $leaver->team();
        $teamMatesBefore = $team?->members()->pluck('users.id')->all() ?? [];

        $this->actingAs($manager)
            ->post("/team/members/{$leaver->id}/offboard", ['successor_id' => $successor->id])
            ->assertSessionHasNoErrors()   // a validation bounce is also a redirect
            ->assertRedirect();

        $movedTo = Customer::whereIn('id', $sample)->pluck('assigned_to')->unique()->all();
        $createdAfter = Customer::whereIn('id', $sample)->pluck('created_by', 'id')->all();
        $teamMatesAfter = $team?->fresh()->members()->pluck('users.id')->all() ?? [];

        expect($movedTo)->toBe([$successor->id])
            ->and($createdAfter)->toBe($createdBefore)          // attribution immutable
            ->and($leaver->fresh()->is_active)->toBeFalse()
            ->and($teamMatesAfter)->toContain($successor->id)
            ->and($teamMatesAfter)->not->toContain($leaver->id);

        // Every teammate other than the leaver is still on the team — nobody orphaned.
        foreach (array_diff($teamMatesBefore, [$leaver->id]) as $mate) {
            expect($teamMatesAfter)->toContain($mate);
        }

        // The successor really SEES the transferred book now.
        expect(Customer::query()->visibleTo($successor->fresh())->pluck('id')->all())
            ->toContain(...$sample);

        dump([
            'leaver' => $leaver->email,
            'successor' => $successor->email,
            'customers_moved' => count($sample),
            'created_by_unchanged' => $createdAfter === $createdBefore,
            'team' => $team?->name,
            'teammates_before' => count($teamMatesBefore),
            'teammates_after' => count($teamMatesAfter),
        ]);
    } finally {
        DB::rollBack(); // dev data untouched
    }
});

it('refuses to delete a real user who still holds a book', function () {
    DB::beginTransaction();

    try {
        $admin = User::role('admin')->firstOrFail();
        $holder = User::role('sales')->get()
            ->first(fn (User $u): bool => UserOffboarding::hasHoldings($u));

        expect($holder)->not->toBeNull('no rep with holdings in dev data');

        $this->actingAs($admin)
            ->delete("/users/{$holder->id}")
            ->assertSessionHas('error');

        expect(User::find($holder->id))->not->toBeNull();

        dump([
            'blocked' => $holder->email,
            'reason' => UserOffboarding::blockingReason($holder),
        ]);
    } finally {
        DB::rollBack();
    }
});
