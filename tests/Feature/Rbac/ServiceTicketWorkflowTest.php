<?php

use App\Enums\TicketCoverage;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use App\Support\SupportAssignments;
use App\Support\TicketFlow;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * M2 (DESIGN_MAINTENANCE.md) — the ticket workflow end to end: scoped intake
 * (CS creates only inside its reach, anchors can't cross-wire to another
 * customer's purchase), the pool→claim→start→done state machine with its guards,
 * ownership bounds (a technician works ONLY their bench, supervisor overrides,
 * cross-team never leaks), and the read-only/none tiers (sales/admin).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * One serviced team plus an outsider:
 *   Team: manager, sales s1 (customer c1), cs + tech1 + tech2 all assigned by s1.
 *   Outside: sales s2 (customer c2) — nobody in the team may reach c2.
 *
 * @return array<string, mixed>
 */
function ticketWorld(): array
{
    $team = Team::factory()->create(['name' => 'Servis A']);
    $manager = userWithRole('supervisor');
    $s1 = userWithRole('sales');
    $team->members()->attach([
        $manager->id => ['role_in_team' => 'manager'],
        $s1->id => ['role_in_team' => 'sales'],
    ]);

    $cs = userWithRole('cs');
    $tech1 = userWithRole('maintenance');
    $tech2 = userWithRole('maintenance');
    // H5: the assignable pool is TEAM-scoped — support joins the team first.
    $team->members()->attach([
        $cs->id => ['role_in_team' => 'cs'],
        $tech1->id => ['role_in_team' => 'maintenance'],
        $tech2->id => ['role_in_team' => 'maintenance'],
    ]);
    SupportAssignments::assign($s1, $cs);
    SupportAssignments::assign($s1, $tech1);
    SupportAssignments::assign($s1, $tech2);

    $s2 = userWithRole('sales');

    return [
        'manager' => $manager, 's1' => $s1, 's2' => $s2,
        'cs' => $cs, 'tech1' => $tech1, 'tech2' => $tech2,
        'c1' => Customer::factory()->createdBy($s1)->create(['name' => 'C1']),
        'c2' => Customer::factory()->createdBy($s2)->create(['name' => 'C2']),
    ];
}

function poolTicket(Customer $customer): ServiceTicket
{
    return ServiceTicket::factory()->create([
        'customer_id' => $customer->id,
        'assigned_to' => null,
        'created_by' => null,
    ]);
}

// --- Intake (store) -------------------------------------------------------------

it('lets CS create a ticket for an in-scope customer into the pool', function () {
    $w = ticketWorld();

    $this->actingAs($w['cs'])
        ->post('/service-tickets', [
            'customer_id' => $w['c1']->id,
            'item_description' => 'TV LED 43 inci',
            'complaint' => 'Tidak mau menyala.',
        ])
        ->assertRedirect();

    $ticket = ServiceTicket::query()->latest('id')->firstOrFail();

    expect($ticket->customer_id)->toBe($w['c1']->id)
        ->and($ticket->created_by)->toBe($w['cs']->id)
        ->and($ticket->assigned_to)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Received)
        ->and($ticket->coverage)->toBe(TicketCoverage::Unknown)
        ->and($ticket->received_at)->not->toBeNull();
});

it('refuses CS intake for a customer outside its scope', function () {
    $w = ticketWorld();

    $this->actingAs($w['cs'])
        ->from('/service-tickets')
        ->post('/service-tickets', [
            'customer_id' => $w['c2']->id,
            'item_description' => 'Kulkas',
            'complaint' => 'Bocor.',
        ])
        ->assertSessionHasErrors('customer_id');

    expect(ServiceTicket::query()->count())->toBe(0);
});

it('refuses a transaction anchor that belongs to another customer', function () {
    $w = ticketWorld();
    $foreign = Transaction::factory()->forCustomer($w['c2'])->create();

    $this->actingAs($w['cs'])
        ->post('/service-tickets', [
            'customer_id' => $w['c1']->id,
            'transaction_id' => $foreign->id,
            'complaint' => 'Mati total.',
        ])
        ->assertSessionHasErrors('transaction_id');

    expect(ServiceTicket::query()->count())->toBe(0);
});

it('suggests coverage from the purchase warranty window', function () {
    $w = ticketWorld();
    $product = Product::factory()->create(['warranty_months' => 12]);

    $inWarranty = Transaction::factory()->forCustomer($w['c1'])
        ->create(['product_id' => $product->id, 'purchased_at' => now()->subMonths(2)]);
    $expired = Transaction::factory()->forCustomer($w['c1'])
        ->create(['product_id' => $product->id, 'purchased_at' => now()->subMonths(30)]);

    $this->actingAs($w['cs'])->post('/service-tickets', [
        'customer_id' => $w['c1']->id, 'transaction_id' => $inWarranty->id, 'complaint' => 'Rusak.',
    ]);
    $this->actingAs($w['cs'])->post('/service-tickets', [
        'customer_id' => $w['c1']->id, 'transaction_id' => $expired->id, 'complaint' => 'Rusak.',
    ]);

    $tickets = ServiceTicket::query()->orderBy('id')->get();

    expect($tickets[0]->coverage)->toBe(TicketCoverage::Warranty)
        ->and($tickets[0]->product_id)->toBe($product->id) // anchor fills the product
        ->and($tickets[1]->coverage)->toBe(TicketCoverage::Paid);
});

// --- Pool → claim → start → done ------------------------------------------------

it('walks the happy path: claim stamps the owner, start and complete stamp the clock', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    $this->actingAs($w['tech1'])->post("/service-tickets/{$ticket->id}/claim")->assertSessionHas('success');
    expect($ticket->fresh()->assigned_to)->toBe($w['tech1']->id);

    $this->actingAs($w['tech1'])->post("/service-tickets/{$ticket->id}/start")->assertSessionHas('success');
    $ticket = $ticket->fresh();
    expect($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->started_at)->not->toBeNull();

    $this->actingAs($w['tech1'])
        ->post("/service-tickets/{$ticket->id}/complete", ['resolution' => 'Ganti power supply.'])
        ->assertSessionHas('success');
    $ticket = $ticket->fresh();
    expect($ticket->status)->toBe(TicketStatus::Done)
        ->and($ticket->finished_at)->not->toBeNull()
        ->and($ticket->resolution)->toBe('Ganti power supply.');
});

it('refuses completing without a resolution', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);
    TicketFlowClaimStart($w['tech1'], $ticket);

    $this->actingAs($w['tech1'])
        ->post("/service-tickets/{$ticket->id}/complete", [])
        ->assertSessionHasErrors('resolution');

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

function TicketFlowClaimStart(User $tech, ServiceTicket $ticket): void
{
    TicketFlow::claim($tech, $ticket);
    TicketFlow::start($tech, $ticket->fresh());
}

it('refuses a second technician claiming an already-claimed ticket', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    $this->actingAs($w['tech1'])->post("/service-tickets/{$ticket->id}/claim");

    // Policy: claim requires an UNclaimed ticket — this is now a 403, not a flash.
    $this->actingAs($w['tech2'])->post("/service-tickets/{$ticket->id}/claim")->assertForbidden();

    expect($ticket->fresh()->assigned_to)->toBe($w['tech1']->id);
});

it('blocks starting an unclaimed ticket', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    // A technician has no standing on an unowned ticket (authorization)…
    $this->actingAs($w['tech1'])->post("/service-tickets/{$ticket->id}/start")->assertForbidden();

    // …a supervisor passes authz but hits the state guard: work needs an owner.
    $this->actingAs($w['manager'])->post("/service-tickets/{$ticket->id}/start")->assertSessionHas('error');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Received);
});

it('rejects invalid transitions: done is final, received cannot complete', function () {
    $w = ticketWorld();

    $received = poolTicket($w['c1']);
    TicketFlow::claim($w['tech1'], $received);
    $this->actingAs($w['tech1'])
        ->post("/service-tickets/{$received->id}/complete", ['resolution' => 'x'])
        ->assertSessionHas('error');
    expect($received->fresh()->status)->toBe(TicketStatus::Received);

    $done = ServiceTicket::factory()->done()->create([
        'customer_id' => $w['c1']->id, 'assigned_to' => $w['tech1']->id,
    ]);
    $this->actingAs($w['tech1'])->post("/service-tickets/{$done->id}/start")->assertSessionHas('error');
    $this->actingAs($w['tech1'])
        ->post("/service-tickets/{$done->id}/cancel", ['reason' => 'salah'])
        ->assertSessionHas('error');
    expect($done->fresh()->status)->toBe(TicketStatus::Done);
});

// --- Ownership bounds -----------------------------------------------------------

it("keeps a technician off another technician's bench; the supervisor overrides", function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);
    TicketFlowClaimStart($w['tech1'], $ticket);

    // tech2 SEES the ticket (same book) but may not work it.
    $this->actingAs($w['tech2'])
        ->post("/service-tickets/{$ticket->id}/complete", ['resolution' => 'bukan punyaku'])
        ->assertForbidden();
    $this->actingAs($w['tech2'])
        ->put("/service-tickets/{$ticket->id}", ['work_notes' => 'nyelonong'])
        ->assertForbidden();

    // The manager may (ticket.assign override, within their team sight).
    $this->actingAs($w['manager'])
        ->post("/service-tickets/{$ticket->id}/complete", ['resolution' => 'Diselesaikan manajer.'])
        ->assertSessionHas('success');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Done);
});

it('routes work via assign: supervisor only, and only to a capable technician', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    // Sales holds no ticket.assign.
    $this->actingAs($w['s1'])
        ->post("/service-tickets/{$ticket->id}/assign", ['assignee_id' => $w['tech1']->id])
        ->assertForbidden();

    // CS cannot WORK tickets, so it is not an eligible assignee.
    $this->actingAs($w['manager'])
        ->post("/service-tickets/{$ticket->id}/assign", ['assignee_id' => $w['cs']->id])
        ->assertSessionHas('error');
    expect($ticket->fresh()->assigned_to)->toBeNull();

    $this->actingAs($w['manager'])
        ->post("/service-tickets/{$ticket->id}/assign", ['assignee_id' => $w['tech1']->id])
        ->assertSessionHas('success');
    expect($ticket->fresh()->assigned_to)->toBe($w['tech1']->id);
});

it('never lets a manager reach another team’s ticket', function () {
    $w = ticketWorld();
    $foreign = poolTicket($w['c2']); // outsider book — no path into the team

    $this->actingAs($w['manager'])->get("/service-tickets/{$foreign->id}")->assertForbidden();
    $this->actingAs($w['manager'])
        ->post("/service-tickets/{$foreign->id}/assign", ['assignee_id' => $w['tech1']->id])
        ->assertForbidden();
});

it('keeps CS to intake: no claim, no transitions', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    $this->actingAs($w['cs'])->post("/service-tickets/{$ticket->id}/claim")->assertForbidden();
    $this->actingAs($w['cs'])->post("/service-tickets/{$ticket->id}/start")->assertForbidden();
});

// --- Read tiers -----------------------------------------------------------------

it('scopes the sales view to their own book', function () {
    $w = ticketWorld();
    $mine = poolTicket($w['c1']);
    poolTicket($w['c2']);

    expect(ServiceTicket::query()->visibleTo($w['s1'])->pluck('id')->all())->toBe([$mine->id]);

    $this->actingAs($w['s1'])
        ->get('/service-tickets')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Page component ships in M3 — skip the file-existence check.
            ->component('ServiceTickets/Index', shouldExist: false)
            ->where('tickets.total', 1));
});

it('denies admin every ticket surface', function () {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    $admin = userWithRole('admin');

    $this->actingAs($admin)->get('/service-tickets')->assertForbidden();
    $this->actingAs($admin)->get("/service-tickets/{$ticket->id}")->assertForbidden();
    $this->actingAs($admin)->delete("/service-tickets/{$ticket->id}")->assertForbidden();

    expect(ServiceTicket::query()->visibleTo($admin)->count())->toBe(0);
});

it('refuses hard delete for every preset (D9: explicit grant only)', function (string $role) {
    $w = ticketWorld();
    $ticket = poolTicket($w['c1']);

    $this->actingAs(userWithRole($role))
        ->delete("/service-tickets/{$ticket->id}")
        ->assertForbidden();

    expect(ServiceTicket::withTrashed()->find($ticket->id))->not->toBeNull();
})->with(['supervisor', 'cs', 'maintenance', 'sales']);
