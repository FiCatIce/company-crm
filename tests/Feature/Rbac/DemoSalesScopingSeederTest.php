<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RolePresets;
use Database\Seeders\DemoSalesScopingSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('provisions sales2 with the sales role and 5 owned customers', function () {
    $this->seed(DemoSalesScopingSeeder::class);

    $sales2 = User::where('email', 'sales2@crm.test')->first();

    expect($sales2)->not->toBeNull()
        ->and($sales2->hasRole('sales'))->toBeTrue()
        ->and($sales2->extension)->toBe('1007')
        ->and($sales2->can(PermissionName::CustomerViewOwn->value))->toBeTrue()
        ->and($sales2->can(PermissionName::CustomerViewAll->value))->toBeFalse()
        ->and(Customer::where('created_by', $sales2->id)->count())->toBe(5);
});

it('gives sales2 customers recorded money and a call log', function () {
    $this->seed(DemoSalesScopingSeeder::class);

    $ownedIds = Customer::where('created_by', User::where('email', 'sales2@crm.test')->value('id'))->pluck('id');

    expect(Transaction::whereIn('customer_id', $ownedIds)->whereNotNull('amount')->exists())->toBeTrue()
        ->and(Interaction::whereIn('customer_id', $ownedIds)->exists())->toBeTrue();
});

it('is idempotent — re-seeding never duplicates the user or its customers', function () {
    $this->seed(DemoSalesScopingSeeder::class);
    $this->seed(DemoSalesScopingSeeder::class);

    $sales2Id = User::where('email', 'sales2@crm.test')->value('id');

    expect(User::where('email', 'sales2@crm.test')->count())->toBe(1)
        ->and(Customer::where('created_by', $sales2Id)->count())->toBe(5);
});

it('shows sales2 exactly their 5 seeded customers on the REAL dashboard', function () {
    // The end-to-end guard for the "seeder ok but dashboard reads 0" class of bug:
    // seed real data, hit the actual dashboard route as sales2, and assert the
    // personal-band count the UI receives — not an in-memory factory fixture.
    $this->withoutVite();
    $this->seed(DemoSalesScopingSeeder::class);

    $sales2 = User::where('email', 'sales2@crm.test')->first();

    // The 5 seeded customers are attributed via created_by (assigned_to null), so
    // this only passes when the dashboard counts ownership, not assigned_to alone.
    expect(Customer::where('created_by', $sales2->id)->count())->toBe(5);

    $this->actingAs($sales2)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('me.myCustomers', 5));
});

it('scopes sales2 to only its own customers, isolated from another sales rep', function () {
    $this->seed(DemoSalesScopingSeeder::class);
    $sales2 = User::where('email', 'sales2@crm.test')->first();

    // A different sales rep with their own customer.
    $sales1 = User::factory()->create();
    RolePresets::assign($sales1, RoleName::Sales);
    $other = Customer::factory()->createdBy($sales1)->create();

    $visibleToSales2 = Customer::visibleTo($sales2)->pluck('id');
    $visibleToSales1 = Customer::visibleTo($sales1)->pluck('id');
    $sales2Owned = Customer::where('created_by', $sales2->id)->pluck('id');

    expect($visibleToSales2)->toHaveCount(5)
        ->and($visibleToSales2->contains($other->id))->toBeFalse()       // sales2 can't see sales1's
        ->and($visibleToSales1->contains($other->id))->toBeTrue()
        ->and($visibleToSales1->intersect($sales2Owned)->isEmpty())->toBeTrue(); // sales1 can't see sales2's
});
