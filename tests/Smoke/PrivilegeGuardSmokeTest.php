<?php

use App\Enums\PermissionName as P;
use App\Models\User;
use App\Support\CapabilityResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sweep finding #1 verified against the REAL dev database (pgsql), driving the REAL
 * routes. Writes run inside a rolled-back transaction.
 *
 * Run explicitly:  vendor/bin/pest tests/Smoke
 */
uses(TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => 'company_crm',
        'database.connections.pgsql.username' => env('DB_USERNAME', 'postgres'),
        'database.connections.pgsql.password' => env('DB_PASSWORD', ''),
    ]);
    DB::purge('pgsql');
    $this->withoutVite();
});

it('refuses a role-builder editor the real admin credentials', function () {
    DB::beginTransaction();

    try {
        $admin = User::permission(P::PermissionAssign->value)
            ->where('is_active', true)->firstOrFail();

        // Exactly what the role builder can mint today: profile-editing rights and
        // nothing else. This account used to be able to rotate the admin password.
        $editor = User::factory()->create(['email' => 'sweep-editor@crm.test']);
        $editor->syncPermissions([P::UserView->value, P::UserUpdate->value]);

        $originalHash = $admin->password;
        $originalEmail = $admin->email;

        $this->actingAs($editor)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => 'attacker@evil.test',
            'role' => 'admin',
            'password' => 'takeover-password-123',
            'password_confirmation' => 'takeover-password-123',
        ])->assertForbidden();

        $admin->refresh();

        expect($admin->password)->toBe($originalHash)
            ->and($admin->email)->toBe($originalEmail)
            ->and(CapabilityResolver::outranks($editor, $admin))->toBeFalse();

        // …and the same editor can still do their actual job on a lesser account.
        $rep = User::role('sales')->where('is_active', true)->firstOrFail();
        $this->actingAs($editor)->get("/users/{$rep->id}/edit")->assertOk();

        dump([
            'admin_protected' => $admin->email,
            'editor_permissions' => $editor->getPermissionNames()->all(),
            'can_still_edit_rep' => $rep->email,
        ]);
    } finally {
        DB::rollBack();
    }
});

it('marks every administrative power sensitive in the real catalog', function () {
    $admin = User::permission(P::PermissionAssign->value)
        ->where('is_active', true)->firstOrFail();

    $props = $this->actingAs($admin)->get('/roles/create')->assertOk()
        ->viewData('page')['props'];

    $flags = [];
    foreach ($props['permissionGroups'] as $group) {
        foreach ($group['permissions'] as $permission) {
            $flags[$permission['name']] = $permission['sensitive'];
        }
    }

    $newlyFlagged = [];
    foreach (CapabilityResolver::ADMIN_POWERS as $power) {
        expect($flags[$power->value] ?? false)->toBeTrue();
        $newlyFlagged[] = $power->value;
    }

    dump(['sensitive_admin_powers' => $newlyFlagged]);
});
