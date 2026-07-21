<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Support\RolePresets;
use Database\Seeders\RoleSeeder;

/**
 * L2-A (DESIGN_L2_DEPRECATE_RESELLER.md) — backend stop-use for the Reseller entity.
 *
 * The contract of this batch: NEW customers and transactions are recorded with no
 * reseller, existing reseller data is left exactly as it was, and nothing is dropped
 * (fully reversible). reseller_id is no longer required or accepted on any write
 * path; a still-present form field is a silent no-op until L2-B removes the UI.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('creates a customer with a null reseller through the route', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), ['name' => 'No Reseller Co'])
        ->assertRedirect(route('customers.index'));

    $customer = Customer::where('name', 'No Reseller Co')->firstOrFail();
    expect($customer->reseller_id)->toBeNull();
});

it('records a transaction with a null reseller (NOT NULL no longer blocks)', function () {
    $customer = Customer::factory()->create(['reseller_id' => null]);
    $product = Product::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::where('customer_id', $customer->id)->firstOrFail();
    expect($transaction->reseller_id)->toBeNull();
});

it('leaves an existing customer reseller_id untouched (data intact)', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);

    $this->actingAs(userWithGlobalView())
        ->put(route('customers.update', $customer), ['name' => 'Renamed'])
        ->assertRedirect(route('customers.index'));

    expect($customer->fresh()->reseller_id)->toBe($reseller->id)
        ->and($customer->fresh()->name)->toBe('Renamed');
});

it('leaves an existing transaction reseller_id untouched on update', function () {
    $reseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);
    $transaction = Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
    ]);
    $newProduct = Product::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->put(route('transactions.update', $transaction), [
            'customer_id' => $customer->id,
            'product_id' => $newProduct->id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    expect($transaction->fresh()->reseller_id)->toBe($reseller->id)
        ->and($transaction->fresh()->product_id)->toBe($newProduct->id);
});

it('still resolves the existing reseller relation for legacy rows', function () {
    // The table and FK are intact this batch, so old records still render their
    // reseller on the 360 page — only the drop (L2-D) severs that.
    $reseller = Reseller::factory()->create(['name' => 'Legacy Distributor']);
    $customer = Customer::factory()->create(['reseller_id' => $reseller->id]);

    expect($customer->reseller)->not->toBeNull()
        ->and($customer->reseller->name)->toBe('Legacy Distributor');
});

// ---------------------------------------------------------------------------
// L2-C — the reseller FEATURE surface is gone: no route, no permission. (The
// model / factory / relation / reseller_id column stay as data plumbing until
// L2-D drops the table.)
// ---------------------------------------------------------------------------

it('has removed every reseller route (404, not a dev-error)', function () {
    $actor = userWithRole('supervisor');

    // The paths no longer resolve to any controller — a clean 404, which closes
    // the L2-B wart where /resellers rendered a now-deleted Inertia page.
    $this->actingAs($actor)->get('/resellers')->assertNotFound();
    $this->actingAs($actor)->get('/resellers/create')->assertNotFound();
    $this->actingAs($actor)->post('/resellers', ['name' => 'X'])->assertNotFound();
});

it('has removed reseller permissions from the enum and every role preset', function () {
    $resellerPerms = ['reseller.view', 'reseller.create', 'reseller.update', 'reseller.delete'];

    foreach ($resellerPerms as $perm) {
        expect(PermissionName::values())->not->toContain($perm);

        foreach (RoleName::cases() as $role) {
            expect(RolePresets::permissions($role))->not->toContain($perm);
        }
    }
});
