<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Batch H7d (DESIGN_HIERARCHY.md) — the scoped revenue band.
 *
 * H3 moved the manager off transaction.view.all, which silently blanked their money
 * band: they read `amount` on every row of /transactions but the dashboard showed
 * them nothing. The fix is not to hand the org total back — it is to SUM exactly
 * what they can see (Transaction::visibleTo), so the headline equals the rows the
 * viewer can open. That equality is the real subject here, and the last test pins
 * it directly: it is the same class of bug as the old "dashboard 0 / list 5".
 *
 * Money-gating is unchanged: CS/maintenance hold no transaction view tier, so the
 * band is ABSENT for them (key missing, never null or 0) even though H3/H5 widened
 * which customers they can see. Admin holds no money permission at all (B4).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A transaction worth $amount on a customer owned by $owner.
 */
function saleFor(User $owner, float $amount): Transaction
{
    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $owner->id, 'assigned_to' => $owner->id])->save();

    return Transaction::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => Product::factory()->create()->id,
        'amount' => $amount,
        'purchased_at' => now()->toDateString(),
    ]);
}

/**
 * A manager leading a team that contains a fresh rep. Returns [manager, rep].
 *
 * @return array{0: User, 1: User}
 */
function teamWithRep(): array
{
    $manager = userWithRole('supervisor');
    $rep = userWithRole('sales');

    $team = Team::factory()->create();
    $team->members()->attach([
        $manager->id => ['role_in_team' => 'manager'],
        $rep->id => ['role_in_team' => 'sales'],
    ]);

    return [$manager, $rep];
}

/**
 * The revenue prop the dashboard hands this user, or null when absent.
 */
function revenueBandFor(User $user): ?array
{
    $props = test()->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->viewData('page')['props'];

    return $props['revenue'] ?? null;
}

// --- Manager: team revenue, not org, not zero ---------------------------------

it('sums the whole team book for a manager', function () {
    [$manager, $rep] = teamWithRep();

    saleFor($rep, 1_000_000);
    saleFor($rep, 500_000);
    saleFor($manager, 250_000);

    expect(revenueBandFor($manager))
        ->not->toBeNull()
        ->and(revenueBandFor($manager)['total'])->toBe(1_750_000.0)
        ->and(revenueBandFor($manager)['scope'])->toBe('team');
});

it('does not count another team in a manager total', function () {
    [$managerA, $repA] = teamWithRep();
    [$managerB, $repB] = teamWithRep();

    saleFor($repA, 1_000_000);
    saleFor($repB, 9_000_000);

    expect(revenueBandFor($managerA)['total'])->toBe(1_000_000.0)
        ->and(revenueBandFor($managerB)['total'])->toBe(9_000_000.0);
});

it('gives a manager a non-zero band where H3 previously blanked it', function () {
    // The regression this batch exists for: a manager holds revenue.view but no
    // transaction.view.all, which used to omit the band entirely.
    [$manager, $rep] = teamWithRep();
    saleFor($rep, 750_000);

    expect($manager->can('transaction.view.all'))->toBeFalse()
        ->and(revenueBandFor($manager))->not->toBeNull()
        ->and(revenueBandFor($manager)['total'])->toBe(750_000.0);
});

// --- Rep: own book -------------------------------------------------------------

it('sums only their own book for a rep', function () {
    [, $rep] = teamWithRep();
    $stranger = userWithRole('sales');

    saleFor($rep, 300_000);
    saleFor($stranger, 8_000_000);

    expect(revenueBandFor($rep)['total'])->toBe(300_000.0)
        ->and(revenueBandFor($rep)['scope'])->toBe('own');
});

// --- Global role: org-wide, unchanged ------------------------------------------

it('keeps the org total for a global viewer', function () {
    $global = userWithGlobalView();
    $global->givePermissionTo('revenue.view');

    [, $rep] = teamWithRep();
    saleFor($rep, 1_000_000);
    saleFor(userWithRole('sales'), 2_000_000);

    expect(revenueBandFor($global)['total'])->toBe(3_000_000.0)
        ->and(revenueBandFor($global)['scope'])->toBe('org');
});

// --- Money stays omitted where it always was -----------------------------------

it('omits the band entirely for CS and maintenance', function (string $role) {
    [, $rep] = teamWithRep();
    $support = userWithRole($role);
    $rep->assignees()->attach($support->id);

    saleFor($rep, 4_000_000);

    // The assignment widened which CUSTOMERS they see — money must not follow.
    $props = $this->actingAs($support)->get('/dashboard')->assertOk()
        ->viewData('page')['props'];

    expect($props)->not->toHaveKey('revenue');
})->with(['cs', 'maintenance']);

it('omits the band for admin', function () {
    $admin = userWithRole('admin');
    [, $rep] = teamWithRep();
    saleFor($rep, 5_000_000);

    $props = $this->actingAs($admin)->get('/dashboard')->assertOk()
        ->viewData('page')['props'];

    expect($props)->not->toHaveKey('revenue');
});

it('never surfaces money from a dangling revenue.view alone', function () {
    // revenue.view without any transaction view tier must still show nothing.
    $user = userWithRole('maintenance');
    $user->givePermissionTo('revenue.view');

    [, $rep] = teamWithRep();
    saleFor($rep, 6_000_000);

    $props = $this->actingAs($user)->get('/dashboard')->assertOk()
        ->viewData('page')['props'];

    expect($props)->not->toHaveKey('revenue');
});

it('keeps the org reseller revenue breakdown global-only', function () {
    [$manager, $rep] = teamWithRep();
    saleFor($rep, 1_000_000);

    $props = $this->actingAs($manager)->get('/dashboard')->assertOk()
        ->viewData('page')['props'];

    // The manager gets their money band but NOT the per-reseller org breakdown,
    // which spans every team.
    expect($props)->toHaveKey('revenue')
        ->and($props)->not->toHaveKey('topResellersByRevenue');
});

// --- The invariant that matters ------------------------------------------------

it('matches the sum of the transactions the viewer can actually open', function (string $who) {
    [$manager, $rep] = teamWithRep();
    $outsider = userWithRole('sales');

    saleFor($rep, 1_100_000);
    saleFor($manager, 900_000);
    saleFor($outsider, 7_000_000);

    $user = $who === 'manager' ? $manager : $rep;

    $visibleSum = (float) Transaction::query()->visibleTo($user)->sum('amount');

    expect(revenueBandFor($user)['total'])->toBe($visibleSum)
        ->and($visibleSum)->toBeGreaterThan(0.0);
})->with(['manager', 'rep']);
