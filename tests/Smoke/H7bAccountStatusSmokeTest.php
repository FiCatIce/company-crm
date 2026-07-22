<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * H7b smoke test against the REAL dev database (pgsql), driving the REAL routes —
 * the "test hijau tapi UI ngawur" guard. Lives outside tests/Feature on purpose so
 * RefreshDatabase does NOT bind: this must run on actual seeded data.
 *
 * It leaves the database as it found it (the target is reactivated at the end).
 * Run explicitly:  vendor/bin/pest tests/Smoke
 */
uses(TestCase::class);

beforeEach(function () {
    // phpunit.xml forces DB_DATABASE=:memory:, so point the pgsql connection back
    // at the real dev database explicitly.
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => 'company_crm',
        'database.connections.pgsql.username' => env('DB_USERNAME', 'postgres'),
        'database.connections.pgsql.password' => env('DB_PASSWORD', ''),
    ]);
    DB::purge('pgsql');
    $this->withoutVite();
});

it('deactivates on real data without touching the book', function () {
    $manager = User::where('email', 'manager@crm.test')->firstOrFail();
    // The rep with the biggest real book WITHIN the manager's reach — a deactivation
    // that "keeps the data" is only meaningful if there IS data to keep. Reps outside
    // their reach correctly 403 (H4 scoping), so they are filtered out here.
    $sales = User::role('sales')
        ->withCount('assignedCustomers')
        ->orderByDesc('assigned_customers_count')
        ->get()
        ->first(fn (User $u): bool => $manager->can('setStatus', $u));

    expect($sales)->not->toBeNull('no reachable sales rep in the dev data');

    // "Data survives" is a vacuous claim against an empty book. If the reachable rep
    // has none, lend them real customer rows for the duration and restore them after.
    $borrowed = [];
    if (Customer::where('assigned_to', $sales->id)->count() === 0) {
        $borrowed = Customer::query()->limit(3)->get()
            ->map(fn (Customer $c): array => ['id' => $c->id, 'was' => $c->assigned_to])->all();

        foreach ($borrowed as $row) {
            Customer::whereKey($row['id'])->update(['assigned_to' => $sales->id]);
        }
    }

    $before = [
        'assigned' => Customer::where('assigned_to', $sales->id)->count(),
        'created' => Customer::where('created_by', $sales->id)->count(),
        'assignees' => $sales->assignees()->pluck('users.id')->all(),
        'visible' => Customer::query()->visibleTo($sales)->count(),
    ];

    // 1. manager deactivates through the real route
    $this->actingAs($manager)
        ->put("/team/members/{$sales->id}/status", ['is_active' => false])
        ->assertRedirect();

    expect($sales->fresh()->is_active)->toBeFalse();

    // 2. the data is untouched
    expect(Customer::where('assigned_to', $sales->id)->count())->toBe($before['assigned'])
        ->and(Customer::where('created_by', $sales->id)->count())->toBe($before['created'])
        ->and($sales->fresh()->assignees()->pluck('users.id')->all())->toBe($before['assignees']);

    // 3. login is refused
    auth()->logout();
    $this->flushSession();
    $this->post('/login', ['email' => $sales->email, 'password' => 'password']);
    $this->assertGuest();

    // 4. reactivate → login works again, visibility restored
    $this->actingAs($manager)
        ->put("/team/members/{$sales->id}/status", ['is_active' => true])
        ->assertRedirect();

    auth()->logout();
    $this->flushSession();
    $this->post('/login', ['email' => $sales->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($sales->fresh());

    expect(Customer::query()->visibleTo($sales->fresh())->count())->toBe($before['visible']);

    dump([
        'target' => $sales->email,
        'book_assigned' => $before['assigned'],
        'book_visible' => $before['visible'],
        'assignees' => $before['assignees'],
        'borrowed_rows' => count($borrowed),
        'restored_active' => $sales->fresh()->is_active,
    ]);

    // Leave the dev database exactly as it was found.
    foreach ($borrowed as $row) {
        Customer::whereKey($row['id'])->update(['assigned_to' => $row['was']]);
    }
});

it('cuts a live session mid-flight on real data', function () {
    $sales = User::role('sales')->firstOrFail();

    $this->actingAs($sales)->get('/dashboard')->assertOk();

    $sales->forceFill(['is_active' => false])->save();
    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();

    $sales->forceFill(['is_active' => true])->save();
    expect($sales->fresh()->is_active)->toBeTrue();
});
