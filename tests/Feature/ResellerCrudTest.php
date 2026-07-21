<?php

use App\Models\Customer;
use App\Models\Reseller;
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

it('redirects guests from the resellers index to login', function () {
    $this->get(route('resellers.index'))->assertRedirect(route('login'));
});

it('forbids authenticated users without an allowed role', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('resellers.index'))
        ->assertForbidden();
});

it('allows admin, supervisor, and cs to view the index', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('resellers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Resellers/Index'));
})->with(['supervisor', 'cs'])
    ->skip('L2-B removed the reseller UI pages; the route + this test go in L2-C.');

// ---------------------------------------------------------------------------
// Tree view
// ---------------------------------------------------------------------------

it('returns the reseller hierarchy as a nested tree', function () {
    $parent = Reseller::factory()->create(['name' => 'Parent Co']);
    Reseller::factory()->count(2)->create(['parent_id' => $parent->id]);

    $this->actingAs(userWithRole('supervisor'))
        ->get(route('resellers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Resellers/Index')
            ->has('tree', 1)
            ->where('tree.0.name', 'Parent Co')
            ->has('tree.0.children', 2)
            ->where('tree.0.children.0.parent_id', $parent->id));
})->skip('L2-B removed the reseller UI pages; the route + this test go in L2-C.');

// ---------------------------------------------------------------------------
// Create / store
// ---------------------------------------------------------------------------

it('opens the create page with parent options', function () {
    Reseller::factory()->count(2)->create();

    $this->actingAs(userWithRole('cs'))
        ->get(route('resellers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Resellers/Create')
            ->has('parentOptions', 2));
})->skip('L2-B removed the reseller UI pages; the route + this test go in L2-C.');

it('stores a top-level reseller', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->post(route('resellers.store'), ['name' => 'Root Co', 'parent_id' => null])
        ->assertRedirect(route('resellers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('resellers', ['name' => 'Root Co', 'parent_id' => null]);
});

it('stores a child reseller under a parent', function () {
    $parent = Reseller::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->post(route('resellers.store'), ['name' => 'Child Co', 'parent_id' => $parent->id])
        ->assertRedirect(route('resellers.index'));

    $this->assertDatabaseHas('resellers', ['name' => 'Child Co', 'parent_id' => $parent->id]);
});

it('validates that the reseller name is required', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('resellers.create'))
        ->post(route('resellers.store'), ['name' => '', 'parent_id' => null])
        ->assertSessionHasErrors('name');
});

it('rejects a non-existent parent when storing', function () {
    $this->actingAs(userWithRole('supervisor'))
        ->from(route('resellers.create'))
        ->post(route('resellers.store'), ['name' => 'X', 'parent_id' => 999999])
        ->assertSessionHasErrors('parent_id');
});

it('forbids users without a role from storing', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('resellers.store'), ['name' => 'X'])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Edit / update
// ---------------------------------------------------------------------------

it('excludes self and descendants from the edit parent options', function () {
    $a = Reseller::factory()->create();
    $b = Reseller::factory()->create(['parent_id' => $a->id]);
    $c = Reseller::factory()->create(['parent_id' => $b->id]);

    // Editing the root: self + both descendants are excluded -> no valid parents.
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('resellers.edit', $a))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Resellers/Edit')
            ->where('reseller.id', $a->id)
            ->has('parentOptions', 0));

    // Editing the leaf: only itself is excluded -> the other two remain.
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('resellers.edit', $c))
        ->assertInertia(fn (Assert $page) => $page->has('parentOptions', 2));
})->skip('L2-B removed the reseller UI pages; the route + this test go in L2-C.');

it('updates a reseller name', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->put(route('resellers.update', $reseller), ['name' => 'Nama Baru', 'parent_id' => null])
        ->assertRedirect(route('resellers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('resellers', ['id' => $reseller->id, 'name' => 'Nama Baru']);
});

// ---------------------------------------------------------------------------
// Cycle prevention (the headline requirement)
// ---------------------------------------------------------------------------

it('prevents a reseller from being its own parent', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->from(route('resellers.edit', $reseller))
        ->put(route('resellers.update', $reseller), [
            'name' => $reseller->name,
            'parent_id' => $reseller->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

it('prevents re-parenting a reseller under its own descendant', function () {
    $a = Reseller::factory()->create();
    $b = Reseller::factory()->create(['parent_id' => $a->id]);
    $c = Reseller::factory()->create(['parent_id' => $b->id]);

    $user = userWithRole('supervisor');

    // A cannot be parented under its direct child B...
    $this->actingAs($user)
        ->from(route('resellers.edit', $a))
        ->put(route('resellers.update', $a), ['name' => $a->name, 'parent_id' => $b->id])
        ->assertSessionHasErrors('parent_id');

    // ...nor under its grandchild C.
    $this->actingAs($user)
        ->from(route('resellers.edit', $a))
        ->put(route('resellers.update', $a), ['name' => $a->name, 'parent_id' => $c->id])
        ->assertSessionHasErrors('parent_id');

    expect($a->fresh()->parent_id)->toBeNull();
});

it('allows re-parenting a reseller under an unrelated reseller', function () {
    $a = Reseller::factory()->create();
    Reseller::factory()->create(['parent_id' => $a->id]);
    $unrelated = Reseller::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->put(route('resellers.update', $a), ['name' => $a->name, 'parent_id' => $unrelated->id])
        ->assertRedirect(route('resellers.index'))
        ->assertSessionHasNoErrors();

    expect($a->fresh()->parent_id)->toBe($unrelated->id);
});

// ---------------------------------------------------------------------------
// Delete (guard + authorization)
// ---------------------------------------------------------------------------

it('deletes a reseller without dependents', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('supervisor'))
        ->delete(route('resellers.destroy', $reseller))
        ->assertRedirect(route('resellers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('resellers', ['id' => $reseller->id]);
});

it('re-parents children to the top level when their parent is deleted', function () {
    $parent = Reseller::factory()->create();
    $child = Reseller::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(userWithRole('supervisor'))
        ->delete(route('resellers.destroy', $parent))
        ->assertRedirect(route('resellers.index'));

    $this->assertDatabaseMissing('resellers', ['id' => $parent->id]);
    expect($child->fresh()->parent_id)->toBeNull();
});

it('blocks deleting a reseller that still has customers', function () {
    $reseller = Reseller::factory()->create();
    Customer::factory()->create(['reseller_id' => $reseller->id]);

    $this->actingAs(userWithRole('supervisor'))
        ->from(route('resellers.index'))
        ->delete(route('resellers.destroy', $reseller))
        ->assertRedirect(route('resellers.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('resellers', ['id' => $reseller->id]);
});

it('blocks deleting a reseller that still has transactions', function () {
    $reseller = Reseller::factory()->create();
    $otherReseller = Reseller::factory()->create();
    $customer = Customer::factory()->create(['reseller_id' => $otherReseller->id]);
    Transaction::factory()->create([
        'customer_id' => $customer->id,
        'reseller_id' => $reseller->id,
    ]);

    $this->actingAs(userWithRole('supervisor'))
        ->from(route('resellers.index'))
        ->delete(route('resellers.destroy', $reseller))
        ->assertRedirect(route('resellers.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('resellers', ['id' => $reseller->id]);
});

it('lets admins and supervisors delete a reseller', function (string $role) {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole($role))
        ->delete(route('resellers.destroy', $reseller))
        ->assertRedirect(route('resellers.index'));

    $this->assertDatabaseMissing('resellers', ['id' => $reseller->id]);
})->with(['supervisor']);

it('forbids cs from deleting a reseller', function () {
    $reseller = Reseller::factory()->create();

    $this->actingAs(userWithRole('cs'))
        ->delete(route('resellers.destroy', $reseller))
        ->assertForbidden();

    $this->assertDatabaseHas('resellers', ['id' => $reseller->id]);
});
