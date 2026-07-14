<?php

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('stamps created_by with the authenticated user on store', function () {
    $actor = userWithRole('cs');
    $reseller = Reseller::factory()->create();

    $this->actingAs($actor)
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Customer Baru',
        ])
        ->assertRedirect(route('customers.index'));

    expect(Customer::sole()->created_by)->toBe($actor->id);
});

it('ignores a forged created_by in the request payload', function () {
    $actor = userWithRole('admin');
    $victim = User::factory()->create();
    $reseller = Reseller::factory()->create();

    $this->actingAs($actor)
        ->post(route('customers.store'), [
            'reseller_id' => $reseller->id,
            'name' => 'Tidak Bisa Dipalsukan',
            'created_by' => $victim->id, // attacker-supplied — must be ignored
        ])
        ->assertRedirect(route('customers.index'));

    expect(Customer::sole()->created_by)->toBe($actor->id)
        ->not->toBe($victim->id);
});

it('does not allow created_by to be mass-assigned', function () {
    $user = User::factory()->create();

    $customer = new Customer(['created_by' => $user->id, 'name' => 'X']);

    expect($customer->created_by)->toBeNull();
});
