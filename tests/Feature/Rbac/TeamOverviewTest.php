<?php

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Support\SupportAssignments;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Batch H6 (DESIGN_HIERARCHY.md) — the "Tim Saya" overview + the dashboard
 * hierarchy band. Two things are under test: that every figure is SCOPED to the
 * viewer's tier (a manager never sees another team), and that the dashboard and
 * the list page never disagree — the regression that bit us before, where the
 * dashboard read 0 while /customers listed 5.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A full team: manager, two reps, one CS, one maintenance.
 *
 * @return array{team: Team, manager: User, repA: User, repB: User, cs: User, maint: User}
 */
function h6Team(string $name): array
{
    $team = Team::factory()->create(['name' => $name]);
    $manager = userWithRole('supervisor');
    $repA = userWithRole('sales');
    $repB = userWithRole('sales');
    $cs = userWithRole('cs');
    $maint = userWithRole('maintenance');

    $team->members()->attach([
        $manager->id => ['role_in_team' => 'manager'],
        $repA->id => ['role_in_team' => 'sales'],
        $repB->id => ['role_in_team' => 'sales'],
        $cs->id => ['role_in_team' => 'cs'],
        $maint->id => ['role_in_team' => 'maintenance'],
    ]);

    return compact('team', 'manager', 'repA', 'repB', 'cs', 'maint');
}

/** A customer owned by $owner (created_by is server-side only). */
function h6Customer(User $owner): Customer
{
    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $owner->id])->save();

    return $customer;
}

// --- Dashboard hierarchy band -------------------------------------------------

it('counts the reps and support inside the manager team', function () {
    $t = h6Team('Alpha');

    $this->actingAs($t['manager'])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.kind', 'manager')
            ->where('team.repCount', 2)
            ->where('team.supportCount', 2));
});

it('counts the support a rep has assigned', function () {
    $t = h6Team('Alpha');
    SupportAssignments::assign($t['repA'], $t['cs']);

    $this->actingAs($t['repA'])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.kind', 'sales')
            ->where('team.supportCount', 1));
});

it('counts the reps that assigned a support agent', function () {
    $t = h6Team('Alpha');
    SupportAssignments::assign($t['repA'], $t['cs']);
    SupportAssignments::assign($t['repB'], $t['cs']);

    $this->actingAs($t['cs'])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.kind', 'support')
            ->where('team.repCount', 2));
});

it('omits the hierarchy band for admin (no team position)', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->missing('team'));
});

// --- The anti-mismatch invariant ----------------------------------------------

it('keeps the dashboard customer figure identical to the /customers total', function (string $key) {
    $t = h6Team('Alpha');
    h6Customer($t['repA']);
    h6Customer($t['repA']);
    h6Customer($t['repB']);
    SupportAssignments::assign($t['repA'], $t['cs']);

    $actor = $t[$key];
    $expected = Customer::query()->visibleTo($actor)->count();

    // Guard against a vacuous pass: 0 == 0 would prove nothing, and 0-vs-N is
    // exactly the shape of the bug this test exists to catch.
    expect($expected)->toBeGreaterThan(0);

    $this->actingAs($actor)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('me.myCustomers', $expected));

    $this->actingAs($actor)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('customers.total', $expected));
})->with(['manager', 'repA', 'cs']);

// --- Tim Saya: manager --------------------------------------------------------

it('shows the manager their reps with book size and who supports each', function () {
    $t = h6Team('Alpha');
    h6Customer($t['repA']);
    h6Customer($t['repA']);
    SupportAssignments::assign($t['repA'], $t['cs']);

    $this->actingAs($t['manager'])
        ->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Team/Index')
            ->where('kind', 'manager')
            ->where('team.name', 'Alpha')
            ->where('reps', function ($reps) use ($t) {
                $rows = collect($reps);
                $a = $rows->firstWhere('id', $t['repA']->id);

                return $rows->pluck('id')->sort()->values()->all() === collect([$t['repA']->id, $t['repB']->id])->sort()->values()->all()
                    && $a['customers_count'] === 2
                    && collect($a['assignees'])->pluck('id')->all() === [$t['cs']->id];
            })
            ->where('agents', fn ($agents) => collect($agents)->pluck('id')->sort()->values()->all()
                === collect([$t['cs']->id, $t['maint']->id])->sort()->values()->all()));
});

it('never leaks another team into the manager overview', function () {
    $a = h6Team('Alpha');
    $b = h6Team('Beta');
    SupportAssignments::assign($b['repA'], $b['cs']);

    $this->actingAs($a['manager'])
        ->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reps', fn ($reps) => ! collect($reps)->pluck('id')->contains($b['repA']->id))
            ->where('agents', fn ($agents) => ! collect($agents)->pluck('id')->contains($b['cs']->id)));
});

// --- Tim Saya: rep + support --------------------------------------------------

it('shows a rep the support they assigned', function () {
    $t = h6Team('Alpha');
    SupportAssignments::assign($t['repA'], $t['maint']);

    $this->actingAs($t['repA'])
        ->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kind', 'sales')
            ->where('agents', fn ($agents) => collect($agents)->pluck('id')->all() === [$t['maint']->id]));
});

it('shows a support agent only the reps that assigned them', function () {
    $t = h6Team('Alpha');
    SupportAssignments::assign($t['repA'], $t['cs']);

    // repB shares the team but never assigned this agent — it must NOT appear.
    $this->actingAs($t['cs'])
        ->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kind', 'support')
            ->where('agents', fn ($agents) => collect($agents)->pluck('id')->all() === [$t['repA']->id]));
});

// --- Gating -------------------------------------------------------------------

it('forbids Tim Saya for a viewer without team.view', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('team.index'))
        ->assertForbidden();
});

it('grants team.view to every role that has a team position', function (string $role) {
    expect(userWithRole($role)->can('team.view'))->toBeTrue();
})->with(['supervisor', 'sales', 'cs', 'maintenance']);
