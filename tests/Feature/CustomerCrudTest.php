<?php

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
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
})->with(['supervisor', 'cs']);

// ---------------------------------------------------------------------------
// Create / store
// ---------------------------------------------------------------------------

it('opens the create page for an authorized user', function () {
    $this->actingAs(userWithRole('cs'))
        ->get(route('customers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Customers/Create')->has('users'));
});

it('stores a customer without a reseller (L2-A stop-use)', function () {
    $response = $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), [
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
        ]);

    $response->assertRedirect(route('customers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('customers', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
    ]);
});

it('accepts null contact fields when storing', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), [
            'name' => 'Tanpa Kontak',
            'phone' => null,
            'email' => null,
            'address' => null,
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['name' => 'Tanpa Kontak', 'phone' => null]);
});

it('validates required and typed fields when storing', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('customers.create'))
        ->post(route('customers.store'), [
            'name' => '',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['name', 'email']);
    // reseller_id is no longer a required/validated field (L2-A).
});

it('forbids users without a role from storing', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('customers.store'), [
            'name' => 'X',
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Edit / update
// ---------------------------------------------------------------------------

it('shows the edit page with the customer loaded', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Edit')
            ->where('customer.id', $customer->id));
});

it('updates a customer and redirects with a success flash', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->put(route('customers.update', $customer), [
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
    ]);
});

it('validates when updating', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->from(route('customers.edit', $customer))
        ->put(route('customers.update', $customer), [
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

// ---------------------------------------------------------------------------
// Delete (authorization matrix)
// ---------------------------------------------------------------------------

it('lets a manager delete a customer inside their own team', function () {
    // H7: the delete permission is bounded by visibility, so the manager must
    // actually lead the team that owns the customer.
    $rep = userWithRole('sales');
    $customer = Customer::factory()->create();
    $customer->forceFill(['created_by' => $rep->id])->save();

    $this->actingAs(managerOverTeamOf($rep))
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
});

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

    $this->actingAs(userWithGlobalView())
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

    $this->actingAs(userWithGlobalView())
        ->get(route('customers.index', ['search' => 'Zebra']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Zebra Unique'));
});

it('paginates the index at 10 per page', function () {
    Customer::factory()->count(15)->create();

    $this->actingAs(userWithGlobalView())
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 10)
            ->where('customers.total', 15));
});

// ---------------------------------------------------------------------------
// Lifecycle (status + source)
// ---------------------------------------------------------------------------

it('stores the lifecycle status and source', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), [
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
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), [
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
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('customers.create'))
        ->post(route('customers.store'), [
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

    $this->actingAs(userWithGlobalView())
        ->put(route('customers.update', $customer), [
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

    $this->actingAs(userWithGlobalView())
        ->get(route('customers.index', ['status' => CustomerStatus::Lead->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'A Lead')
            ->where('customers.data.0.status', 'lead')
            ->where('filters.status', 'lead'));
});

it('ignores an unknown status filter value', function () {
    Customer::factory()->count(3)->create();

    $this->actingAs(userWithGlobalView())
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

    $this->actingAs(userWithGlobalView())
        ->from(route('customers.show', $customer))
        ->patch(route('customers.status', $customer), ['status' => CustomerStatus::Churned->value])
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('success');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Churned);
});

it('validates the status on quick-change', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $this->actingAs(userWithGlobalView())
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

// ---------------------------------------------------------------------------
// Assignment / owner — an ACCESS GATE since B1/H3 (Customer::scopeVisibleTo
// matches created_by OR assigned_to), so H7 bounds the recipient to the actor's
// hierarchy. A global-view actor is unrestricted; see WriteScopeTest for the bound.
// ---------------------------------------------------------------------------

it('stores the assigned owner', function () {
    $agent = User::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->post(route('customers.store'), [
            'name' => 'Punya Agen',
            'assigned_to' => $agent->id,
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['name' => 'Punya Agen', 'assigned_to' => $agent->id]);
});

it('stores an unassigned customer (null owner)', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('customers.store'), [
            'name' => 'Tanpa Owner',
            'assigned_to' => null,
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['name' => 'Tanpa Owner', 'assigned_to' => null]);
});

it('rejects a non-existent owner when storing', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('customers.create'))
        ->post(route('customers.store'), [
            'name' => 'Bad Owner',
            'assigned_to' => 999999,
        ])
        ->assertSessionHasErrors('assigned_to');
});

it('updates and clears the assigned owner', function () {
    $agent = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $agent->id]);

    $this->actingAs(userWithGlobalView())
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'assigned_to' => null,
        ])
        ->assertRedirect(route('customers.index'));

    expect($customer->fresh()->assigned_to)->toBeNull();
});

it('exposes the owner on each index row', function () {
    $owner = User::factory()->create(['name' => 'Agen X']);
    Customer::factory()->create(['assigned_to' => $owner->id, 'name' => 'Owned Cust']);

    $this->actingAs(userWithGlobalView())
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.owner.name', 'Agen X'));
});

it('filters the index by owner=me', function () {
    $agent = userWithRole('cs');
    Customer::factory()->count(2)->create(['assigned_to' => $agent->id]);
    Customer::factory()->create(['assigned_to' => null]);

    $this->actingAs($agent)
        ->get(route('customers.index', ['owner' => 'me']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 2)
            ->where('filters.owner', 'me'));
});

it('filters the index by unassigned owner', function () {
    $agent = userWithGlobalView();
    Customer::factory()->create(['assigned_to' => $agent->id]);
    Customer::factory()->count(2)->create(['assigned_to' => null]);

    $this->actingAs($agent)
        ->get(route('customers.index', ['owner' => 'unassigned']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 2));
});

it('filters the index by a specific owner id and combines with status', function () {
    $agentA = userWithGlobalView();
    $agentB = User::factory()->create();
    Customer::factory()->create(['assigned_to' => $agentB->id, 'status' => CustomerStatus::Lead]);
    Customer::factory()->create(['assigned_to' => $agentB->id, 'status' => CustomerStatus::Active]);
    Customer::factory()->create(['assigned_to' => null, 'status' => CustomerStatus::Lead]);

    $this->actingAs($agentA)
        ->get(route('customers.index', ['owner' => (string) $agentB->id, 'status' => 'lead']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.status', 'lead')
            ->where('filters.owner', (string) $agentB->id));
});

it('ignores an unknown owner filter value', function () {
    Customer::factory()->count(3)->create();

    $this->actingAs(userWithGlobalView())
        ->get(route('customers.index', ['owner' => 'not-a-scope']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 3)
            ->where('filters.owner', null));
});

it('quick-reassigns and clears the owner via the owner endpoint', function () {
    $agent = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => null]);

    $this->actingAs(userWithGlobalView())
        ->from(route('customers.show', $customer))
        ->patch(route('customers.owner', $customer), ['assigned_to' => $agent->id])
        ->assertRedirect(route('customers.show', $customer))
        ->assertSessionHas('success');

    expect($customer->fresh()->assigned_to)->toBe($agent->id);

    $this->actingAs(userWithGlobalView())
        ->patch(route('customers.owner', $customer), ['assigned_to' => null])
        ->assertSessionHas('success');

    expect($customer->fresh()->assigned_to)->toBeNull();
});

it('validates the owner on quick-reassign', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithGlobalView())
        ->from(route('customers.show', $customer))
        ->patch(route('customers.owner', $customer), ['assigned_to' => 999999])
        ->assertSessionHasErrors('assigned_to');
});

it('forbids a roleless user from reassigning the owner', function () {
    $agent = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $agent->id]);

    $this->actingAs(User::factory()->create())
        ->patch(route('customers.owner', $customer), ['assigned_to' => null])
        ->assertForbidden();

    expect($customer->fresh()->assigned_to)->toBe($agent->id);
});

it('keeps access org-wide regardless of assignment', function () {
    $ownerAgent = User::factory()->create();
    $customer = Customer::factory()->create(['assigned_to' => $ownerAgent->id]);
    [$nonOwner] = supportAssignedToOwnerOf($customer, 'cs');

    // A non-owner with a CRM role can still view the 360 page...
    $this->actingAs($nonOwner)
        ->get(route('customers.show', $customer))
        ->assertOk();

    // ...and still edit the customer — assignment is attribution, not a lock.
    $this->actingAs($nonOwner)
        ->put(route('customers.update', $customer), [
            'name' => 'Diedit Non-Owner',
        ])
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Diedit Non-Owner']);
});
