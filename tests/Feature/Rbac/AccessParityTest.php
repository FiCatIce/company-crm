<?php

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Access parity snapshot for the management roles. Started as the B0 no-op guard
 * (engine swap must not change access); from B3 CS is intentionally tightened —
 * it loses all transaction/money access — so CS gets its own expectations below.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

$resources = [
    'customer' => Customer::class,
    'transaction' => Transaction::class,
    'product' => Product::class,
];

// Supervisor (Manager) may view/create/update customers and products.
// H3: transactions are hierarchy-scoped now — the list (viewAny) + create stay
// open, but class-level view/update resolve only against a concrete record (a
// null-record check is false, exactly like any other scoped viewer).
it('lets the manager view, create and update the non-scoped resources', function () {
    $user = userWithRole('supervisor');

    foreach ([Customer::class, Product::class] as $class) {
        expect($user->can('viewAny', $class))->toBeTrue()
            ->and($user->can('view', $class))->toBeTrue()
            ->and($user->can('create', $class))->toBeTrue()
            ->and($user->can('update', $class))->toBeTrue();
    }

    // Transactions: list + create open, record-level checks are scoped.
    expect($user->can('viewAny', Transaction::class))->toBeTrue()
        ->and($user->can('create', Transaction::class))->toBeTrue();
});

// B4: admin is a system role — DENIED every data resource, but keeps dashboard
// (aggregate) + user management. Full lockdown matrix lives in AdminLockdownTest.
it('denies admin every domain data action while keeping system access', function () use ($resources) {
    $user = userWithRole('admin');

    foreach ($resources as $class) {
        expect($user->can('viewAny', $class))->toBeFalse()
            ->and($user->can('view', $class))->toBeFalse()
            ->and($user->can('create', $class))->toBeFalse()
            ->and($user->can('update', $class))->toBeFalse()
            ->and($user->can('delete', $class))->toBeFalse();
    }

    expect($user->can(PermissionName::DashboardView->value))->toBeTrue()
        ->and($user->can(PermissionName::UserView->value))->toBeTrue();
});

// CS keeps customers/products but B3 removed ALL transaction access.
it('lets cs manage customers and products but not transactions', function () {
    $user = userWithRole('cs');

    foreach ([Customer::class, Product::class] as $class) {
        expect($user->can('viewAny', $class))->toBeTrue()
            ->and($user->can('create', $class))->toBeTrue()
            ->and($user->can('update', $class))->toBeTrue();
    }

    // No money: no view/create/update/delete on transactions, no revenue.
    expect($user->can('viewAny', Transaction::class))->toBeFalse()
        ->and($user->can('view', Transaction::class))->toBeFalse()
        ->and($user->can('create', Transaction::class))->toBeFalse()
        ->and($user->can('update', Transaction::class))->toBeFalse()
        ->and($user->can('delete', Transaction::class))->toBeFalse()
        ->and($user->can(PermissionName::RevenueView->value))->toBeFalse();
});

// Only the supervisor may delete data resources (cs and — since B4 — admin cannot).
// H3: the manager's transaction delete is hierarchy-scoped, so the class-level
// (no-record) check is false — it deletes only transactions it can see.
it('lets the manager delete the non-scoped resources', function () {
    $user = userWithRole('supervisor');

    foreach ([Customer::class, Product::class] as $class) {
        expect($user->can('delete', $class))->toBeTrue();
    }

    expect($user->can('delete', Transaction::class))->toBeFalse(); // scoped: needs a record
});

it('denies deleting any resource to cs and admin', function (string $role) use ($resources) {
    $user = userWithRole($role);

    foreach ($resources as $class) {
        expect($user->can('delete', $class))->toBeFalse();
    }
})->with(['admin', 'cs']);

it('lets admin, supervisor and cs reach the dashboard permission', function (string $role) {
    expect(userWithRole($role)->can(PermissionName::DashboardView->value))->toBeTrue();
})->with(['admin', 'supervisor', 'cs']);

it('denies every domain action to a user without a role', function () use ($resources) {
    $user = User::factory()->create();

    foreach ($resources as $class) {
        expect($user->can('viewAny', $class))->toBeFalse()
            ->and($user->can('create', $class))->toBeFalse()
            ->and($user->can('update', $class))->toBeFalse()
            ->and($user->can('delete', $class))->toBeFalse();
    }

    expect($user->can(PermissionName::DashboardView->value))->toBeFalse();
});
