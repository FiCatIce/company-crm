<?php

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    // Render Inertia pages without a built Vite manifest.
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

it('redirects guests from the customers index to login', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

it('forbids authenticated users without an allowed role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('customers.index'))
        ->assertForbidden();
});

it('allows admin, supervisor, and cs to view the index', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Customers/Index'));
})->with(['admin', 'supervisor', 'cs']);

// ---------------------------------------------------------------------------
// Create / store
// ---------------------------------------------------------------------------

it('opens the create page for an authorized user', function () {
    $this->actingAs(userWithRole('cs'))
        ->get(route('customers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Customers/Create')->has('resellers'));
});

it('stores a customer and redirects with a success flash', function () {
    $reseller = Reseller::factory()->create();

    $response = $this->actingAs(userWithRole('admin'))
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
        ]);

    $response->assertRedirect(route('customers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('customers', [
        'name' => 'Budi Santoso',
        'reseller_id' => $reseller->id,
        'email' => 'budi@example.com',
    ]);
});

it('accepts null contact fields when storing', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Tanpa Kontak',
            'phone' => null,
            'email' => null,
            'address' => null,
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['name' => 'Tanpa Kontak', 'phone' => null]);
});

it('validates required and typed fields when storing', function () {
    $this->actingAs(userWithRole('admin'))
        ->from(route('customers.create'))
        ->post(route('customers.store'), [
            'reseller_id' => null,
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['reseller_id', 'name', 'email']);
});

it('rejects a non-existent reseller when storing', function () {
    $this->actingAs(userWithRole('admin'))
        ->from(route('customers.create'))
        ->post(route('customers.store'), [
            'reseller_id' => 999999,
            'name' => 'Ghost',
        ])
        ->assertSessionHasErrors('reseller_id');
});

it('forbids users without a role from storing', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'X',
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Edit / update
// ---------------------------------------------------------------------------

it('shows the edit page with the customer loaded', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Edit')
            ->where('customer.id', $customer->id)
            ->has('resellers'));
});

it('updates a customer and redirects with a success flash', function () {
    $customer = Customer::factory()->create();
    $newReseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->put(route('customers.update', $customer), [
            'reseller_id' => $newReseller->id,
            'name' => 'Nama Baru',
            'phone' => '0811',
            'email' => 'baru@example.com',
            'address' => 'Alamat Baru',
        ])
        ->assertRedirect(route('customers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Nama Baru',
        'reseller_id' => $newReseller->id,
    ]);
});

it('validates when updating', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->from(route('customers.edit', $customer))
        ->put(route('customers.update', $customer), [
            'reseller_id' => $customer->reseller_id,
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

// ---------------------------------------------------------------------------
// Delete (authorization matrix)
// ---------------------------------------------------------------------------

it('lets admins and supervisors delete a customer', function (string $role) {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole($role))
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
})->with(['admin', 'supervisor']);

it('forbids cs from deleting a customer', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole('cs'))
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();

    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
});

it('blocks deleting a customer that still has transactions', function () {
    $customer = Customer::factory()->create();
    Transaction::factory()->forCustomer($customer)->create();

    $this->actingAs(userWithRole('admin'))
        ->from(route('customers.index'))
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
});

// ---------------------------------------------------------------------------
// Search / filter / pagination
// ---------------------------------------------------------------------------

it('filters the index by search term', function () {
    Customer::factory()->create(['name' => 'Zebra Unique']);
    Customer::factory()->create(['name' => 'Common Name']);

    $this->actingAs(userWithRole('admin'))
        ->get(route('customers.index', ['search' => 'Zebra']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Zebra Unique'));
});

it('filters the index by reseller', function () {
    $resellerA = Reseller::factory()->create();
    $resellerB = Reseller::factory()->create();
    Customer::factory()->count(2)->create(['reseller_id' => $resellerA->id]);
    Customer::factory()->create(['reseller_id' => $resellerB->id]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('customers.index', ['reseller' => $resellerA->id]))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 2));
});

it('paginates the index at 10 per page', function () {
    Customer::factory()->count(15)->create();

    $this->actingAs(userWithRole('admin'))
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 10)
            ->where('customers.total', 15));
});
