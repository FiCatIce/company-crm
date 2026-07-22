<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * H7d smoke test against the REAL dev database (pgsql), driving the REAL dashboard
 * route. Read-only — nothing is written.
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

/**
 * @return array<string, mixed>
 */
function dashboardProps(User $user): array
{
    return test()->actingAs($user)->get('/dashboard')->assertOk()
        ->viewData('page')['props'];
}

it('shows each real role the money band its scope entitles it to', function () {
    $report = [];

    foreach (['manager', 'sales', 'cs', 'maintenance', 'test'] as $slug) {
        $email = $slug === 'test' ? 'test@example.com' : "{$slug}@crm.test";
        $user = User::where('email', $email)->first();

        if ($user === null) {
            continue;
        }

        $props = dashboardProps($user);
        $band = $props['revenue'] ?? null;

        // The invariant: whatever is shown equals the SUM of the transactions this
        // user can actually open — no more, no less.
        $visibleSum = (float) Transaction::query()->visibleTo($user)->sum('amount');

        if ($band !== null) {
            expect($band['total'])->toBe($visibleSum);
        }

        $report[$email] = [
            'role' => $user->getRoleNames()->first(),
            'band' => $band === null ? 'ABSENT' : $band['scope'],
            'total' => $band['total'] ?? null,
            'visible_sum' => $visibleSum,
            'sales_revenue_widget' => array_key_exists('revenueBySales', $props),
        ];
    }

    // Money-gating unchanged: support roles get no band at all.
    foreach (['cs@crm.test', 'maintenance@crm.test'] as $email) {
        if (isset($report[$email])) {
            expect($report[$email]['band'])->toBe('ABSENT');
        }
    }

    // Admin sees no money either (B4).
    if (isset($report['test@example.com'])) {
        expect($report['test@example.com']['band'])->toBe('ABSENT');
    }

    dump($report);
});

it('produces a real NON-ZERO team total that excludes other teams', function () {
    // The dev demo data predates the amount column: only 4 of 80 transactions carry
    // one, and none sit in the manager's book — so the gating test above legitimately
    // reads 0.0 everywhere and proves nothing about the aggregate. Price the real
    // rows here, inside a rolled-back transaction, so the team SUM is exercised on
    // actual hierarchy data rather than on factory fixtures.
    DB::beginTransaction();

    try {
        $manager = User::where('email', 'manager@crm.test')->firstOrFail();

        $teamTxnIds = Transaction::query()->visibleTo($manager)->pluck('id')->all();
        expect($teamTxnIds)->not->toBeEmpty('manager sees no transactions in dev data');

        Transaction::whereIn('id', $teamTxnIds)->update(['amount' => 1_000_000]);

        // Price everything OUTSIDE the manager's sight far higher — if the band
        // leaked past the team scope, the number would be unmistakably wrong.
        $outsideIds = Transaction::whereNotIn('id', $teamTxnIds)->pluck('id')->all();
        Transaction::whereIn('id', $outsideIds)->update(['amount' => 9_000_000]);

        $expected = (float) (1_000_000 * count($teamTxnIds));
        $band = dashboardProps($manager)['revenue'] ?? null;

        expect($band)->not->toBeNull()
            ->and($band['scope'])->toBe('team')
            ->and($band['total'])->toBe($expected)
            ->and($band['total'])->toBeGreaterThan(0.0)
            // The org total would be vastly larger — prove we are not showing it.
            ->and($band['total'])->toBeLessThan((float) Transaction::sum('amount'));

        dump([
            'team' => $manager->team()?->name,
            'team_txns' => count($teamTxnIds),
            'team_revenue' => $band['total'],
            'org_revenue' => (float) Transaction::sum('amount'),
            'outside_txns' => count($outsideIds),
        ]);
    } finally {
        DB::rollBack();
    }
});
