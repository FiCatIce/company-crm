<?php

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * M4 invariants (DESIGN_MAINTENANCE.md) — the anti-mismatch rule (H7d) applied
 * to the service-ticket surfaces: every dashboard card equals the total of the
 * pre-filtered /service-tickets list it links to, because both count through
 * the SAME ServiceTicket::visibleTo scope with the SAME filters. Plus the 360
 * "Riwayat Servis" gating and admin's aggregate-without-rows (B4).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * Inertia props of an authenticated GET — for comparing figures across pages.
 *
 * @return array<string, mixed>
 */
function ticketProps(User $user, string $url): array
{
    return test()->actingAs($user)->get($url)->assertOk()
        ->viewData('page')['props'];
}

// ---------------------------------------------------------------------------
// Dashboard card == pre-filtered list, per capability tier
// ---------------------------------------------------------------------------

it('keeps the technician cards equal to the lists they open (mine + pool)', function () {
    $customer = Customer::factory()->create();
    [$tech] = supportAssignedToOwnerOf($customer, 'maintenance');

    // My bench: two active, one done (finished work never counts as "kerjaan").
    ServiceTicket::factory()->create(['customer_id' => $customer->id, 'assigned_to' => $tech->id]);
    ServiceTicket::factory()->inProgress()->create(['customer_id' => $customer->id, 'assigned_to' => $tech->id]);
    ServiceTicket::factory()->done()->create(['customer_id' => $customer->id, 'assigned_to' => $tech->id]);

    // Pool: two claimable on a visible customer; a received ticket on a
    // STRANGER's book (invisible — never counted) and an unassigned cancelled
    // one (not claimable) must both stay out.
    ServiceTicket::factory()->count(2)->create(['customer_id' => $customer->id]);
    ServiceTicket::factory()->create();
    ServiceTicket::factory()->cancelled()->create(['customer_id' => $customer->id]);

    $dash = ticketProps($tech, route('dashboard'));

    expect($dash['tickets'])->toMatchArray(['kind' => 'worker', 'mine' => 2, 'pool' => 2]);

    // The card's click-through filters, verbatim.
    $mineList = ticketProps($tech, route('service-tickets.index', ['assigned' => 'me', 'status' => 'active']));
    $poolList = ticketProps($tech, route('service-tickets.index', ['assigned' => 'pool', 'status' => 'received']));

    expect($mineList['tickets']['total'])->toBe($dash['tickets']['mine'])
        ->and($poolList['tickets']['total'])->toBe($dash['tickets']['pool']);
});

it('keeps the supervisor cards equal to the team roll-up list', function () {
    $sales = userWithRole('sales');
    $manager = managerOverTeamOf($sales);
    $book = Customer::factory()->createdBy($sales)->create();

    ServiceTicket::factory()->create(['customer_id' => $book->id]);
    ServiceTicket::factory()->inProgress()->create(['customer_id' => $book->id]);
    ServiceTicket::factory()->done()->create(['customer_id' => $book->id, 'finished_at' => now()]);
    ServiceTicket::factory()->done()->create(['customer_id' => $book->id, 'finished_at' => now()->startOfMonth()->subDays(3)]);
    // Off-team repair — outside the manager's sight, never counted.
    ServiceTicket::factory()->create();

    $dash = ticketProps($manager, route('dashboard'));

    expect($dash['tickets'])->toMatchArray(['kind' => 'span', 'active' => 2, 'doneThisMonth' => 1]);

    $activeList = ticketProps($manager, route('service-tickets.index', ['status' => 'active']));

    expect($activeList['tickets']['total'])->toBe($dash['tickets']['active'])
        // The /service-tickets header cards run the same scope — three surfaces,
        // one number.
        ->and($activeList['stats']['active'])->toBe($dash['tickets']['active'])
        ->and($activeList['stats']['doneThisMonth'])->toBe($dash['tickets']['doneThisMonth']);
});

it('keeps the cs intake card equal to the created=me active list', function () {
    $customer = Customer::factory()->create();
    [$cs] = supportAssignedToOwnerOf($customer, 'cs');

    ServiceTicket::factory()->create(['customer_id' => $customer->id, 'created_by' => $cs->id]);
    // Finished intake and someone else's intake both stay out.
    ServiceTicket::factory()->done()->create(['customer_id' => $customer->id, 'created_by' => $cs->id]);
    ServiceTicket::factory()->create(['customer_id' => $customer->id]);

    $dash = ticketProps($cs, route('dashboard'));

    expect($dash['tickets'])->toMatchArray(['kind' => 'intake', 'myActiveIntakes' => 1]);

    $list = ticketProps($cs, route('service-tickets.index', ['created' => 'me', 'status' => 'active']));

    expect($list['tickets']['total'])->toBe($dash['tickets']['myActiveIntakes']);
});

it('keeps the sales watch card equal to the active list over their book', function () {
    $sales = userWithRole('sales');
    $book = Customer::factory()->createdBy($sales)->create();

    ServiceTicket::factory()->create(['customer_id' => $book->id]);
    ServiceTicket::factory()->inProgress()->create(['customer_id' => $book->id]);
    ServiceTicket::factory()->done()->create(['customer_id' => $book->id]);
    // A stranger's repair — invisible to this rep.
    ServiceTicket::factory()->create();

    $dash = ticketProps($sales, route('dashboard'));

    expect($dash['tickets'])->toMatchArray(['kind' => 'watch', 'active' => 2]);

    $list = ticketProps($sales, route('service-tickets.index', ['status' => 'active']));

    expect($list['tickets']['total'])->toBe($dash['tickets']['active']);
});

// ---------------------------------------------------------------------------
// Admin: aggregate numbers with NO rows behind them (B4)
// ---------------------------------------------------------------------------

it('gives admin bare org ticket counts and keeps the list itself closed', function () {
    $admin = userWithRole('admin');

    ServiceTicket::factory()->count(2)->create();
    ServiceTicket::factory()->done()->create(['finished_at' => now()]);
    ServiceTicket::factory()->done()->create(['finished_at' => now()->startOfMonth()->subDays(3)]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticketsAggregate.active', 2)
            ->where('ticketsAggregate.doneThisMonth', 1)
            // Never the scoped band — admin has no ticket.view.
            ->missing('tickets'));

    // The numbers have no drill-down: the list stays 403 (aggregate ≠ access).
    $this->actingAs($admin)->get(route('service-tickets.index'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// 360 "Riwayat Servis" — scoped and permission-gated
// ---------------------------------------------------------------------------

it('shows the 360 service history to the book owner, scoped to that customer', function () {
    $sales = userWithRole('sales');
    $book = Customer::factory()->createdBy($sales)->create();

    ServiceTicket::factory()->create(['customer_id' => $book->id]);
    ServiceTicket::factory()->done()->create(['customer_id' => $book->id]);
    // Another customer's ticket must never appear in this 360.
    ServiceTicket::factory()->create();

    $this->actingAs($sales)->get(route('customers.show', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceTickets', 2)
            ->has('serviceTickets.0', fn (Assert $row) => $row
                ->hasAll(['id', 'item', 'status', 'received_at', 'finished_at', 'assignee'])));
});

it('omits the 360 service history entirely without ticket.view', function () {
    // Mint the role-builder case the section guards against: customer sight
    // WITHOUT ticket sight (direct permissions, no preset).
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionName::CustomerViewOwn->value);

    $book = Customer::factory()->createdBy($viewer)->create();
    ServiceTicket::factory()->create(['customer_id' => $book->id]);

    $this->actingAs($viewer)->get(route('customers.show', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('serviceTickets'));
});

it('still 403s a stranger 360 even when service history exists on it', function () {
    $owner = userWithRole('sales');
    $stranger = userWithRole('sales');
    $book = Customer::factory()->createdBy($owner)->create();
    ServiceTicket::factory()->create(['customer_id' => $book->id]);

    $this->actingAs($stranger)->get(route('customers.show', $book))->assertForbidden();
});
