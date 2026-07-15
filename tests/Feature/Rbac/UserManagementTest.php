<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B5 — admin user management (DESIGN_RBAC.md §3.5, decisions D2/D6). Admin CRUDs
 * users, assigns roles (which seed a preset), and toggles direct permissions in
 * either direction — but can never edit its OWN role/permissions (anti-self-
 * escalation), and every change is written to the audit log.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

it('forbids non-admin roles from the user area', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('users.index'))
        ->assertForbidden();
})->with(['sales', 'cs', 'maintenance', 'supervisor']);

it('lets an admin open the user index', function () {
    $this->actingAs(userWithRole('admin'))
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Users/Index'));
});

// ---------------------------------------------------------------------------
// Create + role assignment
// ---------------------------------------------------------------------------

it('creates a user with a role preset and records an audit entry', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Sinta Sales',
            'email' => 'sinta@example.com',
            'extension' => '1007',
            'role' => 'sales',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'sinta@example.com')->sole();

    expect($user->hasRole('sales'))->toBeTrue()
        ->and($user->can(PermissionName::CustomerViewOwn->value))->toBeTrue()
        ->and($user->can(PermissionName::CustomerViewAll->value))->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'target_user_id' => $user->id,
        'action' => 'user.created',
    ]);
});

// ---------------------------------------------------------------------------
// Permission toggle — two-way (D6)
// ---------------------------------------------------------------------------

it('grants and revokes a direct permission in both directions', function () {
    $admin = userWithRole('admin');
    $target = userWithRole('sales');
    $salesPreset = RolePresets::permissions(RoleName::Sales);

    // GRANT: preset + one extra sensitive permission.
    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'sales',
            'manage_permissions' => '1',
            'permissions' => [...$salesPreset, PermissionName::CustomerViewAll->value],
        ])
        ->assertRedirect(route('users.index'));

    expect($target->fresh()->can(PermissionName::CustomerViewAll->value))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'target_user_id' => $target->id,
        'action' => 'user.updated',
    ]);

    // REVOKE: submit the preset only — the extra permission is dropped.
    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'sales',
            'manage_permissions' => '1',
            'permissions' => $salesPreset,
        ])
        ->assertRedirect(route('users.index'));

    expect($target->fresh()->can(PermissionName::CustomerViewAll->value))->toBeFalse();

    // The revoke was recorded with the removed permission in the diff.
    $log = AuditLog::where('target_user_id', $target->id)
        ->where('action', 'user.updated')->latest('id')->first();
    expect($log->changes['permissions']['removed'] ?? [])
        ->toContain(PermissionName::CustomerViewAll->value);
});

// ---------------------------------------------------------------------------
// Anti-self-escalation (D2)
// ---------------------------------------------------------------------------

it('reports that an admin cannot manage its own role or permissions', function () {
    $admin = userWithRole('admin');
    $other = userWithRole('sales');

    expect($admin->can('managePermissions', $admin))->toBeFalse()
        ->and($admin->can('assignRole', $admin))->toBeFalse()
        ->and($admin->can('managePermissions', $other))->toBeTrue()
        ->and($admin->can('assignRole', $other))->toBeTrue();
});

it('ignores an attempt to change ones own role or permissions but still saves the profile', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => 'Admin Baru',
            'email' => $admin->email,
            'role' => 'sales', // attempt to downgrade self
            'manage_permissions' => '1',
            'permissions' => [], // attempt to drop all own permissions
        ])
        ->assertRedirect(route('users.index'));

    $admin->refresh();

    // Profile updated...
    expect($admin->name)->toBe('Admin Baru')
        // ...but role and permissions are untouched (self-edit blocked).
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->can(PermissionName::PermissionAssign->value))->toBeTrue()
        ->and($admin->can(PermissionName::CustomerViewAll->value))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Delete guards
// ---------------------------------------------------------------------------

it('forbids deleting yourself', function () {
    // Two admins so the block is the self-guard, not the last-admin guard.
    $admin = userWithRole('admin');
    userWithRole('admin');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('blocks deleting the last admin', function () {
    $admin = userWithRole('admin');

    // A non-admin holding user.delete directly (a custom-permission user) — so the
    // request reaches the controller and the last-admin business rule fires.
    $deleter = userWithRole('sales');
    $deleter->givePermissionTo([PermissionName::UserView->value, PermissionName::UserDelete->value]);

    $this->actingAs($deleter)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $admin))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('deletes a user and records an audit entry', function () {
    $admin = userWithRole('admin');
    $target = userWithRole('cs');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseMissing('users', ['id' => $target->id]);

    // target_user_id is null-on-delete, so the deleted identity lives in `changes`.
    $log = AuditLog::where('action', 'user.deleted')->where('actor_id', $admin->id)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->changes['user']['email'] ?? null)->toBe($target->email);
});

// ---------------------------------------------------------------------------
// Edit page shape — sensitive flags + presets surfaced
// ---------------------------------------------------------------------------

it('flags sensitive permissions and exposes role presets on the edit page', function () {
    $admin = userWithRole('admin');
    $target = userWithRole('sales');

    $this->actingAs($admin)
        ->get(route('users.edit', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Edit')
            ->where('isSelf', false)
            ->where('can.managePermissions', true)
            ->has('rolePresets.sales')
            ->has('permissionGroups')
            // The Transaction group carries the sensitive money permission, flagged.
            ->where('permissionGroups', function ($groups) {
                $perms = collect($groups)->flatMap(fn ($g) => $g['permissions']);
                $viewAll = $perms->firstWhere('name', PermissionName::TransactionViewAll->value);
                $productView = $perms->firstWhere('name', PermissionName::ProductView->value);

                return $viewAll['sensitive'] === true && $productView['sensitive'] === false;
            }));
});

// ---------------------------------------------------------------------------
// Nav-gating data: only user.view holders see the Users nav (UI reads this)
// ---------------------------------------------------------------------------

it('exposes user.view only to admins (drives nav gating)', function () {
    expect(userWithRole('admin')->can(PermissionName::UserView->value))->toBeTrue()
        ->and(userWithRole('supervisor')->can(PermissionName::UserView->value))->toBeFalse()
        ->and(userWithRole('cs')->can(PermissionName::UserView->value))->toBeFalse();
});
