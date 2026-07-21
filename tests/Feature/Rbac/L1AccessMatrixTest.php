<?php

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Product;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Batch H7e (DESIGN_HIERARCHY.md) — the FINAL Layer-1 snapshot.
 *
 * Every other test proves one rule. This one freezes the whole picture: each role,
 * against a record in its OWN team and an identical record in ANOTHER team, for
 * both READ and WRITE. Two teams is the point — a single-team fixture cannot tell
 * "correctly scoped" apart from "no scoping at all", which is exactly how the
 * cross-team write IDORs survived until H7a.
 *
 * If a future change shifts any cell, this goes red and names the role, the
 * resource and the direction. Read it as the specification of L1's access model.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * Two isolated teams, each with a manager + rep + CS + maintenance, one customer
 * owned by the rep, and a transaction and interaction on it.
 *
 * @return array<string, mixed>
 */
function l1World(): array
{
    $build = function (string $tag): array {
        $team = Team::factory()->create(['name' => "Team {$tag}"]);

        $manager = userWithRole('supervisor');
        $rep = userWithRole('sales');
        $cs = userWithRole('cs');
        $maint = userWithRole('maintenance');

        $team->members()->attach([
            $manager->id => ['role_in_team' => 'manager'],
            $rep->id => ['role_in_team' => 'sales'],
            $cs->id => ['role_in_team' => 'cs'],
            $maint->id => ['role_in_team' => 'maintenance'],
        ]);

        // Support sees a rep's book only through the assignment pivot.
        $rep->assignees()->attach([$cs->id, $maint->id]);

        $customer = Customer::factory()->create();
        $customer->forceFill(['created_by' => $rep->id, 'assigned_to' => $rep->id])->save();

        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'product_id' => Product::factory()->create()->id,
            'amount' => 1_000_000,
        ]);

        $interaction = Interaction::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $rep->id,
        ]);

        return compact('team', 'manager', 'rep', 'cs', 'maint', 'customer', 'transaction', 'interaction');
    };

    return ['A' => $build('A'), 'B' => $build('B')];
}

// --- READ: customers ----------------------------------------------------------

it('scopes customer READ per role, own team vs other team', function (string $role, bool $ownTeam) {
    $w = l1World();
    $actor = $w['A'][$role];

    $visible = Customer::query()->visibleTo($actor)->pluck('id')->all();

    expect(in_array($w['A']['customer']->id, $visible, true))->toBe($ownTeam);
    // NOBODY in L1 sees the other team's book — this is the invariant that makes
    // the whole layer worth having.
    expect($visible)->not->toContain($w['B']['customer']->id);
})->with([
    'manager sees the team book' => ['manager', true],
    'rep sees their own book' => ['rep', true],
    'cs sees the assigning rep book' => ['cs', true],
    'maintenance sees the assigning rep book' => ['maint', true],
]);

it('scopes transaction and interaction READ the same way (they delegate)', function (string $role) {
    $w = l1World();
    $actor = $w['A'][$role];

    expect(Transaction::query()->visibleTo($actor)->pluck('id')->all())
        ->not->toContain($w['B']['transaction']->id)
        ->and(Interaction::query()->visibleTo($actor)->pluck('id')->all())
        ->not->toContain($w['B']['interaction']->id);
})->with(['manager', 'rep', 'cs', 'maint']);

// --- WRITE: write follows sight (H7a) -----------------------------------------

it('refuses every role a WRITE on another team customer', function (string $role) {
    $w = l1World();
    $actor = $w['A'][$role];
    $foreign = $w['B']['customer'];

    $this->actingAs($actor)
        ->put("/customers/{$foreign->id}", [
            'name' => 'HACKED',
        ])->assertForbidden();

    $this->actingAs($actor)
        ->patch("/customers/{$foreign->id}/status", ['status' => 'inactive'])
        ->assertForbidden();

    $this->actingAs($actor)
        ->patch("/customers/{$foreign->id}/owner", ['assigned_to' => $actor->id])
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete("/customers/{$foreign->id}")
        ->assertForbidden();

    expect($foreign->fresh()->name)->not->toBe('HACKED');
})->with(['manager', 'rep', 'cs', 'maint']);

it('refuses every role a WRITE on another team interaction', function (string $role) {
    $w = l1World();

    $this->actingAs($w['A'][$role])
        ->delete("/interactions/{$w['B']['interaction']->id}")
        ->assertForbidden();

    expect(Interaction::find($w['B']['interaction']->id))->not->toBeNull();
})->with(['manager', 'rep', 'cs', 'maint']);

it('refuses every role a WRITE on another team transaction', function (string $role) {
    $w = l1World();

    $this->actingAs($w['A'][$role])
        ->delete("/transactions/{$w['B']['transaction']->id}")
        ->assertForbidden();

    expect(Transaction::find($w['B']['transaction']->id))->not->toBeNull();
})->with(['manager', 'rep', 'cs', 'maint']);

it('still allows the legitimate in-team write', function () {
    $w = l1World();

    // The negative cases above are only meaningful if the positive one works.
    $this->actingAs($w['A']['manager'])
        ->patch("/customers/{$w['A']['customer']->id}/status", ['status' => 'inactive'])
        ->assertRedirect();

    expect($w['A']['customer']->fresh()->status->value)->toBe('inactive');
});

// --- Reassignment cannot cross the team boundary ------------------------------

it('refuses handing a customer to another team, on both write paths', function () {
    $w = l1World();
    $manager = $w['A']['manager'];
    $outsider = $w['B']['rep'];
    $customer = $w['A']['customer'];

    // assigned_to is an ACCESS GRANT: pushing a customer outward would both leak it
    // and drop it out of the actor's own team view.
    $this->actingAs($manager)
        ->patch("/customers/{$customer->id}/owner", ['assigned_to' => $outsider->id])
        ->assertSessionHasErrors('assigned_to');

    $this->actingAs($manager)
        ->put("/customers/{$customer->id}", [
            'name' => $customer->name,
            'assigned_to' => $outsider->id,
        ])->assertSessionHasErrors('assigned_to');

    expect($customer->fresh()->assigned_to)->toBe($w['A']['rep']->id);
});

// --- Team / assignment / user surfaces ----------------------------------------

it('gates the team management surfaces per capability', function (
    string $role, bool $members, bool $assignments, bool $teamView, bool $adminUsers
) {
    $w = l1World();
    $actor = $w['A'][$role];

    $expect = fn (string $url, bool $allowed) => $this->actingAs($actor)->get($url)
        ->assertStatus($allowed ? 200 : 403);

    $expect('/team/members', $members);
    $expect('/team/assignments', $assignments);
    $expect('/team', $teamView);
    $expect('/users', $adminUsers);
})->with([
    //                      members assignments team  /users
    'manager' => ['manager', true, false, true, false],
    'rep' => ['rep', false, true, true, false],
    'cs' => ['cs', false, false, true, false],
    'maintenance' => ['maint', false, false, true, false],
]);

it('keeps admin out of every data surface but in charge of users', function () {
    $w = l1World();
    $admin = userWithRole('admin');

    foreach (['/customers', '/transactions', '/team', '/team/members', '/team/assignments'] as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
    }

    $this->actingAs($admin)->get('/users')->assertOk();
    $this->actingAs($admin)->get('/roles')->assertOk();

    expect(Customer::query()->visibleTo($admin)->count())->toBe(0);
});

it('refuses cross-team reach on every people-management route', function () {
    $w = l1World();
    $managerA = $w['A']['manager'];
    $repB = $w['B']['rep'];

    $this->actingAs($managerA)->put("/team/members/{$repB->id}/password", [
        'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertForbidden();

    $this->actingAs($managerA)
        ->put("/team/members/{$repB->id}/status", ['is_active' => false])
        ->assertForbidden();

    $this->actingAs($managerA)
        ->get("/team/members/{$repB->id}/offboard")
        ->assertForbidden();

    expect($repB->fresh()->is_active)->toBeTrue();
});

it('refuses a rep assigning support from another team', function () {
    $w = l1World();

    $this->actingAs($w['A']['rep'])
        ->post('/team/assignments', ['assignee_ids' => [$w['B']['cs']->id]])
        ->assertSessionHasErrors('assignee_ids.0');

    expect($w['B']['cs']->fresh()->assignedSalesFor()->pluck('users.id')->all())
        ->toBe([$w['B']['rep']->id]);
});

// --- Money -------------------------------------------------------------------

it('shows money only where a transaction tier exists, in both directions', function (
    string $role, bool $money
) {
    $w = l1World();
    $actor = $w['A'][$role];

    $props = $this->actingAs($actor)->get('/dashboard')->assertOk()
        ->viewData('page')['props'];

    expect(array_key_exists('revenue', $props))->toBe($money);

    // …and the per-row amount agrees with the band (one gate, both surfaces).
    if ($money) {
        $row = $this->actingAs($actor)->get('/transactions')->assertOk()
            ->viewData('page')['props']['transactions']['data'][0] ?? null;

        if ($row !== null) {
            expect($row)->toHaveKey('amount');
        }
    }
})->with([
    'manager' => ['manager', true],
    'rep' => ['rep', true],
    'cs' => ['cs', false],
    'maintenance' => ['maint', false],
]);

it('never lets a scoped role read another team money', function () {
    $w = l1World();

    foreach (['manager', 'rep'] as $role) {
        $actor = $w['A'][$role];
        $visibleSum = (float) Transaction::query()->visibleTo($actor)->sum('amount');

        $band = $this->actingAs($actor)->get('/dashboard')->assertOk()
            ->viewData('page')['props']['revenue'];

        // Their own team's single 1M transaction, never the org's 2M.
        expect($band['total'])->toBe($visibleSum)
            ->toBe(1_000_000.0);
    }
});
