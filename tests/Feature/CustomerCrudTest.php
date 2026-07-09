<?php

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
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

// ---------------------------------------------------------------------------
// Lifecycle (status + source)
// ---------------------------------------------------------------------------

it('stores the lifecycle status and source', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Prospek Baru',
            'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Referral->value,
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', [
        'name' => 'Prospek Baru',
        'status' => CustomerStatus::Lead->value,
        'source' => CustomerSource::Referral->value,
    ]);
});

it('defaults status to active when omitted', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Tanpa Status',
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', [
        'name' => 'Tanpa Status',
        'status' => CustomerStatus::Active->value,
        'source' => null,
    ]);
});

it('rejects an invalid status or source when storing', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->from(route('customers.create'))
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Bad Enums',
            'status' => 'vip',
            'source' => 'carrier-pigeon',
        ])
        ->assertSessionHasErrors(['status', 'source']);
});

it('updates the lifecycle status and source', function () {
    $customer = Customer::factory()->create([
        'status' => CustomerStatus::Lead,
        'source' => null,
    ]);

    $this->actingAs(userWithRole('admin'))
        ->put(route('customers.update', $customer), [
            'reseller_id' => $customer->reseller_id,
            'name' => $customer->name,
            'status' => CustomerStatus::Inactive->value,
            'source' => CustomerSource::Online->value,
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'status' => CustomerStatus::Inactive->value,
        'source' => CustomerSource::Online->value,
    ]);
});

it('filters the index by status', function () {
    Customer::factory()->create(['name' => 'A Lead', 'status' => CustomerStatus::Lead]);
    Customer::factory()->create(['name' => 'An Active', 'status' => CustomerStatus::Active]);

    $this->actingAs(userWithRole('admin'))
        ->get(route('customers.index', ['status' => CustomerStatus::Lead->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'A Lead')
            ->where('customers.data.0.status', 'lead')
            ->where('filters.status', 'lead'));
});

it('ignores an unknown status filter value', function () {
    Customer::factory()->count(3)->create();

    $this->actingAs(userWithRole('admin'))
        ->get(route('customers.index', ['status' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 3)
            ->where('filters.status', null));
});

// ---------------------------------------------------------------------------
// Quick-change status (Customer 360 header)
// ---------------------------------------------------------------------------

it('quick-changes the status via the status endpoint', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Lead]);

    $this->actingAs(userWithRole('cs'))
        ->from(route('customers.show', $customer))
        ->patch(route('customers.status', $customer), ['status' => CustomerStatus::Churned->value])
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('success');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Churned);
});

it('validates the status on quick-change', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $this->actingAs(userWithRole('admin'))
        ->from(route('customers.show', $customer))
        ->patch(route('customers.status', $customer), ['status' => 'nope'])
        ->assertSessionHasErrors('status');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

it('forbids a roleless user from quick-changing status', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $this->actingAs(User::factory()->create())
        ->patch(route('customers.status', $customer), ['status' => CustomerStatus::Lead->value])
        ->assertForbidden();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});
