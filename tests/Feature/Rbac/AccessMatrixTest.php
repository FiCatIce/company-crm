<?php

use App\Enums\PermissionName as P;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\RoleSeeder;

/**
 * B7 — final access-matrix regression snapshot. The authoritative role→permission
 * matrix is RolePresets (derived from DESIGN_RBAC.md §3.3). These tests pin (a)
 * that provisioning applies exactly that matrix with no drift, and (b) the
 * security-critical invariants of the matrix itself, so a mis-edited preset in a
 * future change fails loudly.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

// (a) Every role provisions to EXACTLY its preset — the full per-role permission
// matrix, snapshotted. Any added/removed cell for any role turns this red.
it('provisions each role to exactly its documented preset', function (string $role) {
    $user = userWithRole($role);

    $expected = collect(RolePresets::permissions(RoleName::from($role)))->sort()->values()->all();

    expect($user->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe($expected);
})->with(['admin', 'supervisor', 'sales', 'maintenance', 'cs']);

// (b) Security-critical invariants of the matrix — a wrong preset that still
// "provisions correctly" (a fails only vs itself) is caught here vs the design.
it('keeps admin free of every data/money permission (B4 lockdown)', function () {
    $admin = userWithRole('admin');

    foreach ([
        P::CustomerViewAll, P::CustomerViewOwn, P::CustomerCreate, P::CustomerUpdateAll,
        P::CustomerDelete, P::TransactionViewAll, P::TransactionViewOwn, P::TransactionCreate,
        P::RevenueView, P::ProductView,
    ] as $permission) {
        expect($admin->can($permission->value))->toBeFalse();
    }

    // ...but keeps its system role.
    expect($admin->can(P::UserView->value))->toBeTrue()
        ->and($admin->can(P::PermissionAssign->value))->toBeTrue()
        ->and($admin->can(P::DashboardStatsAggregate->value))->toBeTrue()
        ->and($admin->can(P::InteractionViewAll->value))->toBeTrue();
});

it('scopes sales to own (never .all) and denies it ORG money', function () {
    $sales = userWithRole('sales');

    expect($sales->can(P::CustomerViewOwn->value))->toBeTrue()
        ->and($sales->can(P::CustomerViewAll->value))->toBeFalse()
        ->and($sales->can(P::TransactionViewOwn->value))->toBeTrue()
        ->and($sales->can(P::TransactionViewAll->value))->toBeFalse()
        ->and($sales->can(P::InteractionViewOwn->value))->toBeTrue()
        ->and($sales->can(P::InteractionViewAll->value))->toBeFalse()
        // H7d: sales DOES hold revenue.view, but it only ever sums the rows it can
        // already open (Transaction::visibleTo = its own book) — the amounts it
        // reads per row in /transactions today. Org money stays out of reach
        // because that needs transaction.view.all, asserted false above, which is
        // also what gates the per-reseller org revenue breakdown.
        ->and($sales->can(P::RevenueView->value))->toBeTrue()
        ->and($sales->can(P::CustomerDelete->value))->toBeFalse();
});

it('denies cs and maintenance all money, and keeps maintenance read-only', function () {
    foreach (['cs', 'maintenance'] as $role) {
        $user = userWithRole($role);

        // H3: cs/maintenance are hierarchy-scoped now (assigned sales' books), no
        // longer global — but still no money.
        expect($user->can(P::CustomerViewAll->value))->toBeFalse()
            ->and($user->can(P::CustomerViewAssigned->value))->toBeTrue()
            ->and($user->can(P::TransactionViewAll->value))->toBeFalse()   // no money
            ->and($user->can(P::TransactionViewOwn->value))->toBeFalse()
            ->and($user->can(P::RevenueView->value))->toBeFalse();
    }

    // CS is front-line (write); maintenance is read-only (D8).
    $cs = userWithRole('cs');
    $maintenance = userWithRole('maintenance');

    expect($cs->can(P::CustomerCreate->value))->toBeTrue()
        ->and($cs->can(P::CustomerUpdateAll->value))->toBeTrue()
        ->and($maintenance->can(P::CustomerCreate->value))->toBeFalse()
        ->and($maintenance->can(P::CustomerUpdateAll->value))->toBeFalse()
        ->and($maintenance->can(P::CustomerUpdateOwn->value))->toBeFalse();
});

it('gives user-management + permission-granting to admin alone', function () {
    foreach (['supervisor', 'sales', 'maintenance', 'cs'] as $role) {
        $user = userWithRole($role);

        expect($user->can(P::UserView->value))->toBeFalse()
            ->and($user->can(P::RoleAssign->value))->toBeFalse()
            ->and($user->can(P::PermissionAssign->value))->toBeFalse();
    }
});

it('grants a roleless user nothing at all', function () {
    $user = User::factory()->create();

    foreach (P::values() as $permission) {
        expect($user->can($permission))->toBeFalse();
    }
});

// M2 (DESIGN_MAINTENANCE.md §2.3) — the service-ticket block of the matrix:
// admin none (B4 stays), sales read-only, cs intake, maintenance works its bench,
// supervisor routes. ticket.delete (D9) is held by NO preset — explicit grant only.
it('applies the service-ticket matrix per role', function () {
    $admin = userWithRole('admin');
    foreach ([P::TicketView, P::TicketCreate, P::TicketUpdate, P::TicketAssign, P::TicketDelete] as $permission) {
        expect($admin->can($permission->value))->toBeFalse();
    }

    $sales = userWithRole('sales');
    expect($sales->can(P::TicketView->value))->toBeTrue()
        ->and($sales->can(P::TicketCreate->value))->toBeFalse()
        ->and($sales->can(P::TicketUpdate->value))->toBeFalse()
        ->and($sales->can(P::TicketAssign->value))->toBeFalse();

    $cs = userWithRole('cs');
    expect($cs->can(P::TicketView->value))->toBeTrue()
        ->and($cs->can(P::TicketCreate->value))->toBeTrue()
        ->and($cs->can(P::TicketUpdate->value))->toBeFalse()
        ->and($cs->can(P::TicketAssign->value))->toBeFalse();

    $maintenance = userWithRole('maintenance');
    expect($maintenance->can(P::TicketView->value))->toBeTrue()
        ->and($maintenance->can(P::TicketCreate->value))->toBeTrue()
        ->and($maintenance->can(P::TicketUpdate->value))->toBeTrue()
        ->and($maintenance->can(P::TicketAssign->value))->toBeFalse();

    $supervisor = userWithRole('supervisor');
    expect($supervisor->can(P::TicketView->value))->toBeTrue()
        ->and($supervisor->can(P::TicketCreate->value))->toBeTrue()
        ->and($supervisor->can(P::TicketUpdate->value))->toBeTrue()
        ->and($supervisor->can(P::TicketAssign->value))->toBeTrue();

    foreach (['supervisor', 'sales', 'maintenance', 'cs'] as $role) {
        expect(userWithRole($role)->can(P::TicketDelete->value))->toBeFalse();
    }
});
