<?php

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * B0 parity snapshot: after swapping the authorization engine from role strings
 * to permissions, the effective access for the three legacy roles must be
 * IDENTICAL to before. If any of these change, B0 was not a no-op.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

$resources = [
    'customer' => Customer::class,
    'transaction' => Transaction::class,
    'product' => Product::class,
    'reseller' => Reseller::class,
];

// admin/supervisor/cs may view/create/update every domain resource.
it('lets managers and cs view, create and update every resource', function (string $role) use ($resources) {
    $user = userWithRole($role);

    foreach ($resources as $class) {
        expect($user->can('viewAny', $class))->toBeTrue()
            ->and($user->can('view', $class))->toBeTrue()
            ->and($user->can('create', $class))->toBeTrue()
            ->and($user->can('update', $class))->toBeTrue();
    }
})->with(['admin', 'supervisor', 'cs']);

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
