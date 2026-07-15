<?php

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
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
    'reseller' => Reseller::class,
];

// admin/supervisor may view/create/update every domain resource.
it('lets managers view, create and update every resource', function (string $role) use ($resources) {
    $user = userWithRole($role);

    foreach ($resources as $class) {
        expect($user->can('viewAny', $class))->toBeTrue()
            ->and($user->can('view', $class))->toBeTrue()
            ->and($user->can('create', $class))->toBeTrue()
            ->and($user->can('update', $class))->toBeTrue();
    }
})->with(['admin', 'supervisor']);

// CS keeps customers/products/resellers but B3 removed ALL transaction access.
it('lets cs manage customers, products and resellers but not transactions', function () {
    $user = userWithRole('cs');

    foreach ([Customer::class, Product::class, Reseller::class] as $class) {
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

// Only admin + supervisor may delete (destructive — excludes cs).
it('lets only admin and supervisor delete resources', function (string $role, bool $canDelete) use ($resources) {
    $user = userWithRole($role);

    foreach ($resources as $class) {
        expect($user->can('delete', $class))->toBe($canDelete);
    }
})->with([
    ['admin', true],
    ['supervisor', true],
    ['cs', false],
]);

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
