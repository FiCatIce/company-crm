<?php

use App\Enums\PermissionName as P;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Role builder — admins create/edit/delete CUSTOM roles and set their permission
 * templates; the five system roles are locked. Custom-role permissions live on
 * the role (role_has_permissions) and reach users via getAllPermissions().
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

it('lets an admin open the role builder', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Index')
            ->has('roles', 5)); // the five seeded system roles
});

it('forbids everyone without role.manage from the role builder', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('roles.index'))
        ->assertForbidden();
})->with(['supervisor', 'sales', 'cs', 'maintenance']);

it('forbids a non-admin from creating a role', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('roles.store'), ['name' => 'Hacker', 'permissions' => [P::CustomerViewAll->value]])
        ->assertForbidden();

    expect(Role::where('name', 'Hacker')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Create + effective permissions (the headline acceptance)
// ---------------------------------------------------------------------------

it('lets an admin create a custom role with permissions', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('roles.store'), [
            'name' => 'Auditor',
            'permissions' => [P::CustomerViewAll->value, P::ProductView->value],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::findByName('Auditor');

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['customer.view.all', 'product.view']);
});

it('grants a user assigned a custom role exactly that role\'s permissions', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('roles.store'), [
            'name' => 'Auditor',
            'permissions' => [P::CustomerViewAll->value, P::ProductView->value],
        ]);

    $user = User::factory()->create();
    $user->assignRole('Auditor');

    expect($user->can(P::CustomerViewAll->value))->toBeTrue()
        ->and($user->can(P::ProductView->value))->toBeTrue()
        ->and($user->can(P::TransactionViewAll->value))->toBeFalse()
        ->and($user->can(P::RoleManage->value))->toBeFalse();
});

it('requires a name and at least one permission', function () {
    $actor = userWithRole('admin');

    $this->actingAs($actor)
        ->post(route('roles.store'), ['name' => '', 'permissions' => []])
        ->assertSessionHasErrors(['name', 'permissions']);
});

it('rejects a custom role named like a system role', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('roles.store'), ['name' => 'admin', 'permissions' => [P::CustomerViewAll->value]])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown permission string', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('roles.store'), ['name' => 'Auditor', 'permissions' => ['customer.view.all', 'not.a.real.permission']])
        ->assertSessionHasErrors('permissions.1');
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

it('lets an admin change a custom role\'s permissions', function () {
    $role = Role::create(['name' => 'Auditor', 'guard_name' => 'web']);
    $role->syncPermissions([P::CustomerViewAll->value]);

    $this->actingAs(userWithRole('admin'))
        ->put(route('roles.update', $role), [
            'name' => 'Auditor',
            'permissions' => [P::CustomerViewAll->value, P::TransactionViewAll->value],
        ])
        ->assertRedirect(route('roles.index'));

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['customer.view.all', 'transaction.view.all']);
});

// ---------------------------------------------------------------------------
// System-role protection
// ---------------------------------------------------------------------------

it('blocks deleting a system role', function () {
    $admin = Role::findByName('admin');

    $this->actingAs(userWithRole('admin'))
        ->delete(route('roles.destroy', $admin))
        ->assertSessionHas('error');

    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});

it('blocks renaming/re-permissioning a system role', function () {
    $sales = Role::findByName('sales');

    $this->actingAs(userWithRole('admin'))
        ->put(route('roles.update', $sales), ['name' => 'sales-renamed', 'permissions' => [P::RevenueView->value]]);

    // Untouched — still named 'sales' and no custom permissions attached.
    expect(Role::where('name', 'sales')->exists())->toBeTrue()
        ->and($sales->fresh()->permissions)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

it('deletes an unused custom role', function () {
    $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);
    $role->syncPermissions([P::ProductView->value]);

    $this->actingAs(userWithRole('admin'))
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'Temp')->exists())->toBeFalse();
});

it('refuses to delete a custom role still assigned to a user', function () {
    $role = Role::create(['name' => 'InUse', 'guard_name' => 'web']);
    $role->syncPermissions([P::ProductView->value]);
    User::factory()->create()->assignRole('InUse');

    $this->actingAs(userWithRole('admin'))
        ->delete(route('roles.destroy', $role))
        ->assertSessionHas('error');

    expect(Role::where('name', 'InUse')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Audit trail
// ---------------------------------------------------------------------------

it('writes an audit entry when a role is created', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'Auditor',
        'permissions' => [P::CustomerViewAll->value],
    ]);

    $log = AuditLog::where('action', 'role.created')->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->changes['role'])->toBe('Auditor');
});
