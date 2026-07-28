<?php

use App\Enums\PermissionName as P;
use App\Enums\RoleName;
use App\Enums\TicketCoverage;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Transaction;
use App\Support\RolePresets;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * M1 (DESIGN_MAINTENANCE.md) — the dormant service-ticket foundation. Three
 * claims under test: the schema+model round-trips, the module is genuinely
 * DORMANT (no preset holds ticket.*, so every role resolves to zero rows), and
 * scopeVisibleTo implements D3 — customer-visibility delegation UNION the
 * viewer's own work, without widening the customer axis.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

// --- Schema + model -------------------------------------------------------------

it('creates the service_tickets table with the designed columns', function () {
    expect(Schema::hasTable('service_tickets'))->toBeTrue()
        ->and(Schema::hasColumns('service_tickets', [
            'customer_id', 'transaction_id', 'product_id', 'item_description',
            'serial_no', 'complaint', 'work_notes', 'resolution', 'coverage',
            'priority', 'status', 'assigned_to', 'created_by', 'received_at',
            'started_at', 'finished_at', 'cancelled_at', 'deleted_at',
        ]))->toBeTrue();
});

it('round-trips a ticket with enum casts and resolves every relation', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $transaction = Transaction::factory()->forCustomer($customer)->create(['product_id' => $product->id]);
    $technician = userWithRole('maintenance');
    $frontDesk = userWithRole('cs');

    $ticket = ServiceTicket::factory()->create([
        'customer_id' => $customer->id,
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'assigned_to' => $technician->id,
        'created_by' => $frontDesk->id,
        'coverage' => TicketCoverage::Warranty,
        'priority' => TicketPriority::High,
    ]);

    $ticket = $ticket->fresh();

    expect($ticket->status)->toBe(TicketStatus::Received)
        ->and($ticket->coverage)->toBe(TicketCoverage::Warranty)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->customer->is($customer))->toBeTrue()
        ->and($ticket->transaction->is($transaction))->toBeTrue()
        ->and($ticket->product->is($product))->toBeTrue()
        ->and($ticket->assignee->is($technician))->toBeTrue()
        ->and($ticket->creator->is($frontDesk))->toBeTrue();
});

it('defaults a fresh unsaved ticket to received/unknown/normal in memory', function () {
    // Dev-pgsql smoke caught this: DB defaults do not exist in memory, so a
    // just-created ticket read status NULL and every TicketFlow guard refused it.
    $ticket = new ServiceTicket;

    expect($ticket->status)->toBe(TicketStatus::Received)
        ->and($ticket->coverage)->toBe(TicketCoverage::Unknown)
        ->and($ticket->priority)->toBe(TicketPriority::Normal);
});

it('marks only received and in_progress as active work', function () {
    expect(TicketStatus::Received->isActive())->toBeTrue()
        ->and(TicketStatus::InProgress->isActive())->toBeTrue()
        ->and(TicketStatus::Done->isActive())->toBeFalse()
        ->and(TicketStatus::Cancelled->isActive())->toBeFalse();
});

// --- Admin stays ticket-free (B4 — the M2 activation must not reach admin) ------

it('keeps every ticket permission out of the admin preset', function () {
    $preset = RolePresets::permissions(RoleName::Admin);

    foreach ([P::TicketView, P::TicketCreate, P::TicketUpdate, P::TicketAssign, P::TicketDelete] as $permission) {
        expect($preset)->not->toContain($permission->value);
    }
});

it('resolves zero rows for admin — even on tickets naming them', function () {
    $admin = userWithRole('admin');
    ServiceTicket::factory()->create(['assigned_to' => $admin->id, 'created_by' => $admin->id]);

    expect(ServiceTicket::query()->visibleTo($admin)->count())->toBe(0);
});

// --- scopeVisibleTo (D3: delegation ∪ own work, customer axis never widens) -----

it('shows tickets on visible customers and hides the rest', function () {
    $sales = userWithRole('sales');
    $sales->givePermissionTo(P::TicketView->value);

    $ownCustomer = Customer::factory()->createdBy($sales)->create();
    $strangerCustomer = Customer::factory()->createdBy(userWithRole('sales'))->create();

    $visible = ServiceTicket::factory()->create(['customer_id' => $ownCustomer->id]);
    $hidden = ServiceTicket::factory()->create(['customer_id' => $strangerCustomer->id]);

    $ids = ServiceTicket::query()->visibleTo($sales)->pluck('id')->all();

    expect($ids)->toContain($visible->id)
        ->and($ids)->not->toContain($hidden->id);
});

it('unions in assigned-to-me and created-by-me without widening the customer axis', function () {
    $technician = userWithRole('maintenance');
    $technician->givePermissionTo(P::TicketView->value);

    // Both customers are OUTSIDE the technician's scope (no sales assigned them).
    $outOfScopeA = Customer::factory()->createdBy(userWithRole('sales'))->create();
    $outOfScopeB = Customer::factory()->createdBy(userWithRole('sales'))->create();

    $assignedToMe = ServiceTicket::factory()->create([
        'customer_id' => $outOfScopeA->id, 'assigned_to' => $technician->id,
    ]);
    $loggedByMe = ServiceTicket::factory()->create([
        'customer_id' => $outOfScopeB->id, 'created_by' => $technician->id,
    ]);

    $ids = ServiceTicket::query()->visibleTo($technician)->pluck('id')->all();

    // The TICKETS are visible (my work)…
    expect($ids)->toContain($assignedToMe->id)
        ->and($ids)->toContain($loggedByMe->id)
        // …but the CUSTOMERS behind them stay out of reach (D3).
        ->and(Customer::query()->visibleTo($technician)->pluck('id')->all())
        ->not->toContain($outOfScopeA->id);
});

it('resolves zero rows without ticket.view even when the customer is visible', function () {
    $sales = userWithRole('sales');
    $sales->revokePermissionTo(P::TicketView->value); // strip the M2 preset grant

    $customer = Customer::factory()->createdBy($sales)->create();
    ServiceTicket::factory()->create(['customer_id' => $customer->id, 'assigned_to' => $sales->id]);

    expect(ServiceTicket::query()->visibleTo($sales)->count())->toBe(0);
});
