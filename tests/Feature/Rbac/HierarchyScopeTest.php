<?php

use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Team;
use App\Models\Transaction;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * H3 (DESIGN_HIERARCHY.md) — hierarchy visibility roll-up + cross-team ISOLATION.
 * The security core of the batch: a manager sees their whole team's book but never
 * another team's; a sales user never rolls up to team-mates; a CS/maintenance user
 * sees the UNION of the sales who assigned them and nothing else; unassignment
 * revokes access live. Transactions + call logs follow customer visibility, and
 * money stays omitted for money-less roles even as their customer scope widens.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * Two teams, three sales, one customer each.
 *   Team A: manager mA over sales s1, s2  → customers c1, c2
 *   Team B: manager mB over sales s3       → customer  c3
 *
 * @return array<string, mixed>
 */
function hierarchyWorld(): array
{
    $s1 = userWithRole('sales');
    $s2 = userWithRole('sales');
    $s3 = userWithRole('sales');

    $mA = userWithRole('supervisor');
    $teamA = Team::factory()->create(['name' => 'Team A']);
    $teamA->members()->attach([
        $mA->id => ['role_in_team' => 'manager'],
        $s1->id => ['role_in_team' => 'sales'],
        $s2->id => ['role_in_team' => 'sales'],
    ]);

    $mB = userWithRole('supervisor');
    $teamB = Team::factory()->create(['name' => 'Team B']);
    $teamB->members()->attach([
        $mB->id => ['role_in_team' => 'manager'],
        $s3->id => ['role_in_team' => 'sales'],
    ]);

    return [
        'mA' => $mA, 'mB' => $mB, 's1' => $s1, 's2' => $s2, 's3' => $s3,
        'c1' => Customer::factory()->createdBy($s1)->create(['name' => 'C1']),
        'c2' => Customer::factory()->createdBy($s2)->create(['name' => 'C2']),
        'c3' => Customer::factory()->createdBy($s3)->create(['name' => 'C3']),
    ];
}

// --- Manager roll-up + cross-team isolation ----------------------------------

it('rolls a manager up to their whole team but never another team', function () {
    ['mA' => $mA, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3] = hierarchyWorld();

    $visible = Customer::visibleTo($mA)->pluck('id')->all();

    expect($visible)->toContain($c1->id)
        ->toContain($c2->id)
        ->not->toContain($c3->id);
});

it('forbids a manager from opening an off-team customer by URL', function () {
    ['mA' => $mA, 'c1' => $c1, 'c3' => $c3] = hierarchyWorld();

    $this->actingAs($mA)->get(route('customers.show', $c1))->assertOk();
    $this->actingAs($mA)->get(route('customers.show', $c3))->assertForbidden();
});

// --- Sales does NOT roll up to team-mates ------------------------------------

it('does not roll a sales user up to their team-mates', function () {
    ['s1' => $s1, 'c1' => $c1, 'c2' => $c2] = hierarchyWorld();

    // s1 and s2 share Team A, but a sales user sees only their OWN book.
    $visible = Customer::visibleTo($s1)->pluck('id')->all();

    expect($visible)->toContain($c1->id)->not->toContain($c2->id);
});

// --- CS union across assigning sales -----------------------------------------

it('unions a cs across every sales that assigned them, excluding the rest', function () {
    ['s1' => $s1, 's2' => $s2, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3] = hierarchyWorld();

    $cs = userWithRole('cs');
    $s1->assignees()->attach($cs->id);
    $s2->assignees()->attach($cs->id);

    $visible = Customer::visibleTo($cs)->pluck('id')->all();

    expect($visible)->toContain($c1->id)
        ->toContain($c2->id)
        ->not->toContain($c3->id); // s3 never assigned this cs
});

it('revokes a cs\'s access to a sales book on unassignment, keeping the others', function () {
    ['s1' => $s1, 's2' => $s2, 'c1' => $c1, 'c2' => $c2] = hierarchyWorld();

    $cs = userWithRole('cs');
    $s1->assignees()->attach($cs->id);
    $s2->assignees()->attach($cs->id);

    expect(Customer::visibleTo($cs)->pluck('id')->all())->toContain($c1->id)->toContain($c2->id);

    $s1->assignees()->detach($cs->id);

    $visible = Customer::visibleTo($cs->fresh())->pluck('id')->all();

    expect($visible)->not->toContain($c1->id)  // lost s1's book...
        ->toContain($c2->id);                   // ...but keeps s2's
});

// --- Transactions + call logs follow customer visibility (free roll-up) ------

it('rolls transactions and call logs up with the customer visibility', function () {
    ['mA' => $mA, 'c1' => $c1, 'c3' => $c3] = hierarchyWorld();

    $txMine = Transaction::factory()->forCustomer($c1)->create();
    $txTheirs = Transaction::factory()->forCustomer($c3)->create();
    $callMine = Interaction::factory()->forCustomer($c1)->create(['type' => InteractionType::Call]);
    $callTheirs = Interaction::factory()->forCustomer($c3)->create(['type' => InteractionType::Call]);

    expect(Transaction::visibleTo($mA)->pluck('id')->all())
        ->toContain($txMine->id)->not->toContain($txTheirs->id);
    expect(Interaction::visibleTo($mA)->pluck('id')->all())
        ->toContain($callMine->id)->not->toContain($callTheirs->id);
});

// --- Money stays omitted for money-less roles even as scope widens -----------

it('keeps money omitted for a cs even as its customer scope widens', function () {
    ['s1' => $s1, 'c1' => $c1] = hierarchyWorld();
    Transaction::factory()->forCustomer($c1)->create(['amount' => 500_000]);

    $cs = userWithRole('cs');
    $s1->assignees()->attach($cs->id);

    // The wider (assignment) scope lets cs open the 360 — but never the money.
    $this->actingAs($cs)
        ->get(route('customers.show', $c1))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('transactions')
            ->missing('stats.totalSpend'));

    // ...and the transaction module itself stays off-limits.
    $this->actingAs($cs)->get(route('transactions.index'))->assertForbidden();
});
