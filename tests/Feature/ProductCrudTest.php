<?php

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

it('redirects guests from the products index to login', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

it('forbids authenticated users without an allowed role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('products.index'))
        ->assertForbidden();
});

it('allows admin, supervisor, and cs to view the index', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Products/Index'));
})->with(['admin', 'supervisor', 'cs']);

// ---------------------------------------------------------------------------
// Create / store
// ---------------------------------------------------------------------------

it('opens the create page for an authorized user', function () {
    $this->actingAs(userWithRole('cs'))
        ->get(route('products.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Products/Create'));
});

it('stores a product and redirects with a success flash', function () {
    $response = $this->actingAs(userWithRole('admin'))
        ->post(route('products.store'), [
            'name' => 'AC Split 1 PK',
            'warranty_months' => 12,
        ]);

    $response->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('products', [
        'name' => 'AC Split 1 PK',
        'warranty_months' => 12,
    ]);
});

it('allows a zero-warranty product', function () {
    $this->actingAs(userWithRole('admin'))
        ->post(route('products.store'), [
            'name' => 'Kabel HDMI',
            'warranty_months' => 0,
        ])
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', ['name' => 'Kabel HDMI', 'warranty_months' => 0]);
});

it('validates required fields when storing', function () {
    $this->actingAs(userWithRole('admin'))
        ->from(route('products.create'))
        ->post(route('products.store'), [
            'name' => '',
            'warranty_months' => null,
        ])
        ->assertSessionHasErrors(['name', 'warranty_months']);
});

it('rejects a negative warranty period', function () {
    $this->actingAs(userWithRole('admin'))
        ->from(route('products.create'))
        ->post(route('products.store'), [
            'name' => 'Bad Warranty',
            'warranty_months' => -5,
        ])
        ->assertSessionHasErrors('warranty_months');
});

it('rejects a non-integer warranty period', function () {
    $this->actingAs(userWithRole('admin'))
        ->from(route('products.create'))
        ->post(route('products.store'), [
            'name' => 'Fractional Warranty',
            'warranty_months' => 'abc',
        ])
        ->assertSessionHasErrors('warranty_months');
});

it('forbids users without a role from storing', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('products.store'), [
            'name' => 'X',
            'warranty_months' => 6,
        ])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Edit / update
// ---------------------------------------------------------------------------

it('shows the edit page with the product loaded', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Edit')
            ->where('product.id', $product->id));
});

it('updates a product and redirects with a success flash', function () {
    $product = Product::factory()->create(['warranty_months' => 6]);

    $this->actingAs(userWithRole('admin'))
        ->put(route('products.update', $product), [
            'name' => 'Nama Baru',
            'warranty_months' => 24,
        ])
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Nama Baru',
        'warranty_months' => 24,
    ]);
});

it('validates when updating', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWithRole('admin'))
        ->from(route('products.edit', $product))
        ->put(route('products.update', $product), [
            'name' => '',
            'warranty_months' => 12,
        ])
        ->assertSessionHasErrors('name');
});

// ---------------------------------------------------------------------------
// Delete (authorization matrix)
// ---------------------------------------------------------------------------

it('lets admins and supervisors delete a product', function (string $role) {
    $product = Product::factory()->create();

    $this->actingAs(userWithRole($role))
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
})->with(['admin', 'supervisor']);

it('forbids cs from deleting a product', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWithRole('cs'))
        ->delete(route('products.destroy', $product))
        ->assertForbidden();

    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

it('blocks deleting a product that still has transactions', function () {
    $product = Product::factory()->create();
    Transaction::factory()->create(['product_id' => $product->id]);

    $this->actingAs(userWithRole('admin'))
        ->from(route('products.index'))
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

// ---------------------------------------------------------------------------
// Search / pagination
// ---------------------------------------------------------------------------

it('filters the index by search term', function () {
    Product::factory()->create(['name' => 'Zebra Unique Product']);
    Product::factory()->create(['name' => 'Common Product']);

    $this->actingAs(userWithRole('admin'))
        ->get(route('products.index', ['search' => 'Zebra']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Zebra Unique Product'));
});

it('paginates the index at 10 per page', function () {
    Product::factory()->count(15)->create();

    $this->actingAs(userWithRole('admin'))
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 10)
            ->where('products.total', 15));
});
