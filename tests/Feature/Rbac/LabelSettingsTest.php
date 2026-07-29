<?php

use App\Models\AuditLog;
use App\Models\LabelOverride;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * L3-C — the /settings/labels admin panel: gate (setting.labels, admin-only),
 * save → cache flush → next request live, empty/whitespace/markup-only input =
 * reset to default (row deleted, never a blank label), the admin slug is
 * unsubmittable even outside the UI (D2), and the 40-char bound holds.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('shows the panel to admin with the four overridable slugs only', function () {
    LabelOverride::create(['key' => 'sales', 'label' => 'Account Manager']);

    $this->actingAs(userWithRole('admin'))
        ->get(route('labels.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Labels')
            ->has('rows', 4)
            ->where('rows', fn ($rows) => collect($rows)->pluck('slug')->doesntContain('admin'))
            ->where('rows', fn ($rows) => collect($rows)
                ->contains(fn ($row) => $row['slug'] === 'sales'
                    && $row['default'] === 'Sales'
                    && $row['override'] === 'Account Manager')));
});

it('403s every non-admin role on view and update', function () {
    foreach (['supervisor', 'sales', 'cs', 'maintenance'] as $role) {
        $user = userWithRole($role);

        $this->actingAs($user)->get(route('labels.edit'))->assertForbidden();
        $this->actingAs($user)
            ->put(route('labels.update'), ['overrides' => ['sales' => 'X']])
            ->assertForbidden();
    }
});

it('saves an override and the very next request reads it everywhere', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)
        ->from(route('labels.edit'))
        ->put(route('labels.update'), ['overrides' => [
            'supervisor' => '', 'sales' => 'Account Manager', 'cs' => '', 'maintenance' => '',
        ]])
        ->assertRedirect(route('labels.edit'))
        ->assertSessionHas('success');

    expect(LabelOverride::query()->where('key', 'sales')->value('label'))->toBe('Account Manager');

    // Cache flushed by the model event — a fresh request resolves the new name.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('labels.roles.sales', 'Account Manager')
            ->where('labels.rolesLower.sales', 'account manager'));

    // The change is audited with before → after.
    expect(AuditLog::query()->where('action', 'settings.labels.updated')->exists())->toBeTrue();
});

it('treats empty, whitespace, and markup-only input as reset to default', function () {
    LabelOverride::create(['key' => 'sales', 'label' => 'Account Manager']);
    LabelOverride::create(['key' => 'cs', 'label' => 'Layanan']);
    LabelOverride::create(['key' => 'maintenance', 'label' => 'Teknisi Lapangan']);

    $this->actingAs(userWithRole('admin'))
        ->put(route('labels.update'), ['overrides' => [
            'supervisor' => '',
            'sales' => '   ',          // whitespace → reset
            'cs' => '<b></b>',         // markup-only → reset
            'maintenance' => ' <i>Teknisi Lapangan</i> ', // markup stripped, kept
        ]])
        ->assertRedirect();

    expect(LabelOverride::query()->where('key', 'sales')->exists())->toBeFalse()
        ->and(LabelOverride::query()->where('key', 'cs')->exists())->toBeFalse()
        ->and(LabelOverride::query()->where('key', 'maintenance')->value('label'))->toBe('Teknisi Lapangan');
});

it('rejects the admin slug even when forged outside the ui', function () {
    $this->actingAs(userWithRole('admin'))
        ->from(route('labels.edit'))
        ->put(route('labels.update'), ['overrides' => [
            'supervisor' => '', 'sales' => '', 'cs' => '', 'maintenance' => '',
            'admin' => 'Superuser',
        ]])
        ->assertRedirect(route('labels.edit'))
        ->assertSessionHasErrors('overrides');

    expect(LabelOverride::query()->where('key', 'admin')->exists())->toBeFalse();
});

it('enforces the 40-char bound and renders fine at the limit', function () {
    $admin = userWithRole('admin');
    $atLimit = str_repeat('Nama Panjang', 3).'Sekt'; // 40 chars

    expect(strlen($atLimit))->toBe(40);

    $this->actingAs($admin)
        ->from(route('labels.edit'))
        ->put(route('labels.update'), ['overrides' => [
            'supervisor' => '', 'sales' => str_repeat('x', 41), 'cs' => '', 'maintenance' => '',
        ]])
        ->assertSessionHasErrors('overrides.sales');

    $this->actingAs($admin)
        ->put(route('labels.update'), ['overrides' => [
            'supervisor' => '', 'sales' => $atLimit, 'cs' => '', 'maintenance' => '',
        ]])
        ->assertSessionHasNoErrors();

    // The widest label the panel allows must not break the pages that print it.
    $sales = userWithRole('sales');
    $this->actingAs($sales)->get(route('dashboard'))->assertOk();
    $this->actingAs($sales)->get(route('team.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('labels.roles.sales', $atLimit));
});
