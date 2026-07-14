<?php

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * B1 isolation (DESIGN_RBAC.md §7.1): a Sales user (customer.view.own) may reach
 * ONLY the customers they created or own (D1-B). Every read + write path is
 * checked so none leaks another rep's book.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

/**
 * Seed A's book (one created, one assigned) + B's customer, and return them.
 *
 * @return array{a: User, b: User, created: Customer, assigned: Customer, theirs: Customer}
 */
function scopedBooks(): array
{
    $a = userWithRole('sales');
    $b = userWithRole('sales');

    return [
        'a' => $a,
        'b' => $b,
        'created' => Customer::factory()->createdBy($a)->create(['name' => 'A Created', 'phone' => '081200000001']),
        'assigned' => Customer::factory()->create(['name' => 'A Assigned', 'assigned_to' => $a->id]),
        'theirs' => Customer::factory()->createdBy($b)->create(['name' => 'B Secret', 'phone' => '081299999999', 'assigned_to' => $b->id]),
    ];
}

// ---------------------------------------------------------------------------
// Sales isolation — the security core
// ---------------------------------------------------------------------------

it('lists only the sales user\'s own customers (created OR assigned), never another rep\'s', function () {
    ['a' => $a, 'created' => $created, 'assigned' => $assigned, 'theirs' => $theirs] = scopedBooks();

    $this->actingAs($a)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 2) // created + assigned (D1-B)
            ->where('customers.data', function ($rows) use ($created, $assigned, $theirs) {
                $ids = collect($rows)->pluck('id');

                return $ids->contains($created->id)
                    && $ids->contains($assigned->id)
                    && ! $ids->contains($theirs->id);
            }));
});

it('forbids a sales user from opening another rep\'s customer by URL', function () {
    ['a' => $a, 'created' => $created, 'theirs' => $theirs] = scopedBooks();

    $this->actingAs($a)->get(route('customers.show', $theirs))->assertForbidden();
    $this->actingAs($a)->get(route('customers.show', $created))->assertOk();
});

it('returns nothing when a sales user searches for another rep\'s customer', function () {
    ['a' => $a] = scopedBooks();

    // By name and by phone — both must stay within scope.
    $this->actingAs($a)
        ->get(route('customers.index', ['search' => 'B Secret']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));

    $this->actingAs($a)
        ->get(route('customers.index', ['search' => '081299999999']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
});

it('cannot bypass the scope via the owner filter', function () {
    ['a' => $a, 'b' => $b, 'theirs' => $theirs] = scopedBooks();

    // Filtering by B's id must not reveal B's customer to A.
    $this->actingAs($a)
        ->get(route('customers.index', ['owner' => (string) $b->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 0)
            ->where('customers.data', fn ($rows) => ! collect($rows)->pluck('id')->contains($theirs->id)));
});

it('forbids a sales user from updating another rep\'s customer (write IDOR)', function () {
    ['a' => $a, 'created' => $created, 'theirs' => $theirs] = scopedBooks();

    $payload = fn (Customer $c) => [
        'reseller_id' => $c->reseller_id,
        'name' => 'Diedit',
    ];

    // Cannot write to B's customer...
    $this->actingAs($a)->put(route('customers.update', $theirs), $payload($theirs))->assertForbidden();
    $this->assertDatabaseMissing('customers', ['id' => $theirs->id, 'name' => 'Diedit']);

    // ...but can update their own.
    $this->actingAs($a)->put(route('customers.update', $created), $payload($created))
        ->assertRedirect(route('customers.index'));
    $this->assertDatabaseHas('customers', ['id' => $created->id, 'name' => 'Diedit']);
});

it('scopes the header total count to the sales user\'s book', function () {
    ['a' => $a] = scopedBooks(); // 2 of the 3 customers are A's

    $this->actingAs($a)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.total', 2));
});

it('does not leak the staff directory to a sales user', function () {
    ['a' => $a] = scopedBooks();

    // Sales (no customer.reassign) gets an empty owner dropdown...
    $this->actingAs($a)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users', 0));

    // ...a manager (customer.reassign) still gets the directory.
    $this->actingAs(userWithRole('supervisor'))
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => count($users) > 0));
});

// ---------------------------------------------------------------------------
// Other roles unchanged (scoping only affects own-scoped users this batch)
// ---------------------------------------------------------------------------

it('still lets view-all roles see every customer', function (string $role) {
    scopedBooks(); // 3 customers total, owned by two different sales reps

    $this->actingAs(userWithRole($role))
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 3));
})->with(['admin', 'supervisor', 'cs', 'maintenance']);

it('still lets a view-all role open any customer by URL', function () {
    ['theirs' => $theirs] = scopedBooks();

    $this->actingAs(userWithRole('cs'))
        ->get(route('customers.show', $theirs))
        ->assertOk();
});
