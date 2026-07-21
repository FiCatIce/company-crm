<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Support\RolePresets;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reseller deprecation — END STATE (DESIGN_L2_DEPRECATE_RESELLER.md, L2-D done).
 *
 * The entity is fully gone: no route, no permission, no model relation, no table,
 * no reseller_id column. The ONLY survivor is the archived distributor NAME on
 * customers.reseller_name_legacy — plain historical text, no tree/relation/FK.
 * Customer + transaction writes carry on with no reseller at all.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('has dropped the resellers table and both reseller_id columns', function () {
    expect(Schema::hasTable('resellers'))->toBeFalse()
        ->and(Schema::hasColumn('customers', 'reseller_id'))->toBeFalse()
        ->and(Schema::hasColumn('transactions', 'reseller_id'))->toBeFalse();
});

it('keeps the archived distributor name readable on the customer', function () {
    // The L2-D migration snapshotted resellers.name here before the drop; a fresh
    // DB has no legacy rows, so prove the column round-trips the historical value.
    $customer = Customer::factory()->create();
    DB::table('customers')->where('id', $customer->id)
        ->update(['reseller_name_legacy' => 'Legacy Distributor']);

    expect($customer->fresh()->reseller_name_legacy)->toBe('Legacy Distributor');
});

it('no longer exposes a reseller relation on the models', function () {
    expect(method_exists(Customer::class, 'reseller'))->toBeFalse()
        ->and(method_exists(Transaction::class, 'reseller'))->toBeFalse();
});

it('creates customers and transactions with no reseller involved', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), ['name' => 'No Reseller Co'])
        ->assertRedirect(route('customers.index'));

    $customer = Customer::where('name', 'No Reseller Co')->firstOrFail();
    $product = Product::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->post(route('transactions.store'), [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'purchased_at' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::where('customer_id', $customer->id)->exists())->toBeTrue();
});

// --- Feature + permission surface (removed in L2-C, still gone) ----------------

it('has removed every reseller route (404, not a dev-error)', function () {
    $actor = userWithRole('supervisor');

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
