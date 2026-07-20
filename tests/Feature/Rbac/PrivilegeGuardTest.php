<?php

use App\Enums\PermissionName as P;
use App\Models\User;
use App\Support\CapabilityResolver;
use Database\Seeders\RoleSeeder;

/**
 * Sweep finding #1 — `user.update` was a takeover primitive.
 *
 * UserPolicy::update accepted a target and ignored it, so anyone holding
 * `user.update` could rewrite an administrator's password — or their EMAIL, then
 * take the account over through the password-reset mail — and sign in with full
 * rights. No shipped preset grants user.update outside admin, but the role builder
 * can mint one, which is what makes it a real gap and not a theoretical one.
 *
 * The fix applies the rule the delegated model already runs on (H2) to EDITING:
 * you may never act on someone who outranks you.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * A custom-role-style account: the given permissions and nothing else — exactly
 * what the role builder can produce.
 */
function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create();
    $user->syncPermissions(array_map(fn (P $p): string => $p->value, $permissions));

    return $user;
}

// --- The takeover, closed ------------------------------------------------------

it('refuses a user.update holder the admin password', function () {
    $editor = userWithPermissions([P::UserView, P::UserUpdate]);
    $admin = userWithRole('admin');
    $originalHash = $admin->password;

    $this->actingAs($editor)->put("/users/{$admin->id}", [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'admin',
        'password' => 'takeover-password-123',
        'password_confirmation' => 'takeover-password-123',
    ])->assertForbidden();

    expect($admin->fresh()->password)->toBe($originalHash);
});

it('refuses the email route to the same takeover', function () {
    // Rewriting the address and then using the password-reset mail is the same
    // attack through another door — which is why the whole edit is guarded, not
    // just the password field.
    $editor = userWithPermissions([P::UserView, P::UserUpdate]);
    $admin = userWithRole('admin');

    $this->actingAs($editor)->put("/users/{$admin->id}", [
        'name' => $admin->name,
        'email' => 'attacker@evil.test',
        'role' => 'admin',
    ])->assertForbidden();

    expect($admin->fresh()->email)->not->toBe('attacker@evil.test');
});

it('refuses even the edit screen for a more powerful target', function () {
    $editor = userWithPermissions([P::UserView, P::UserUpdate]);
    $admin = userWithRole('admin');

    $this->actingAs($editor)->get("/users/{$admin->id}/edit")->assertForbidden();
});

// --- …without breaking the legitimate cases ------------------------------------

it('still lets a user.update holder edit a powerless account', function () {
    $editor = userWithPermissions([P::UserView, P::UserUpdate]);
    $sales = userWithRole('sales');

    $this->actingAs($editor)->put("/users/{$sales->id}", [
        'name' => 'Renamed Rep',
        'email' => $sales->email,
        'role' => 'sales',
        'password' => 'fresh-password-123',
        'password_confirmation' => 'fresh-password-123',
    ])->assertRedirect();

    expect($sales->fresh()->name)->toBe('Renamed Rep');
});

it('still lets an admin reset anyone', function () {
    $admin = userWithRole('admin');
    $peer = userWithRole('admin');
    $hash = $peer->password;

    $this->actingAs($admin)->put("/users/{$peer->id}", [
        'name' => $peer->name,
        'email' => $peer->email,
        'role' => 'admin',
        'password' => 'rotated-password-123',
        'password_confirmation' => 'rotated-password-123',
    ])->assertRedirect();

    expect($peer->fresh()->password)->not->toBe($hash);
});

it('lets an actor edit themselves (a tie never outranks)', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->get("/users/{$admin->id}/edit")->assertOk();
    expect(CapabilityResolver::outranks($admin, $admin))->toBeTrue();
});

// --- The ranking rule itself ---------------------------------------------------

it('ranks by administrative power, in both directions', function () {
    $admin = userWithRole('admin');
    $editor = userWithPermissions([P::UserView, P::UserUpdate]);
    $sales = userWithRole('sales');

    expect(CapabilityResolver::outranks($admin, $editor))->toBeTrue()
        ->and(CapabilityResolver::outranks($editor, $admin))->toBeFalse()
        ->and(CapabilityResolver::outranks($editor, $sales))->toBeTrue()
        ->and(CapabilityResolver::outranks($sales, $editor))->toBeFalse();
});

it('blocks a partial administrator from reaching a fuller one', function () {
    // Holding SOME admin powers is not enough — the target must hold none the
    // actor lacks, so a role-builder role cannot climb one rung at a time.
    $partial = userWithPermissions([P::UserView, P::UserUpdate, P::UserDelete]);
    $granter = userWithPermissions([P::UserView, P::UserUpdate, P::PermissionAssign]);

    expect(CapabilityResolver::outranks($partial, $granter))->toBeFalse();

    $this->actingAs($partial)->get("/users/{$granter->id}/edit")->assertForbidden();
});

// --- The asymmetry that hid it -------------------------------------------------

it('marks every administrative power as sensitive', function () {
    // The invariant, not a hand-patched list: the old list flagged user.delete but
    // not user.update, so the stronger power shipped without a confirmation.
    foreach (CapabilityResolver::ADMIN_POWERS as $power) {
        expect($power->sensitive())
            ->toBeTrue("{$power->value} is an admin power but is not marked sensitive");
    }
});

it('surfaces the sensitive flag to the role builder', function () {
    $admin = userWithRole('admin');

    $props = $this->actingAs($admin)->get('/roles/create')->assertOk()
        ->viewData('page')['props'];

    $flags = [];
    foreach ($props['permissionGroups'] as $group) {
        foreach ($group['permissions'] as $permission) {
            $flags[$permission['name']] = $permission['sensitive'];
        }
    }

    expect($flags[P::UserUpdate->value])->toBeTrue()
        ->and($flags[P::UserCreate->value])->toBeTrue()
        ->and($flags[P::UserView->value])->toBeTrue();
});
