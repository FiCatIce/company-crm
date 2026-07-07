<?php

use App\Models\Customer;
use App\Models\Reseller;

it('allows customers to be created with null phone, email, and address', function () {
    $reseller = Reseller::create(['name' => 'Acme Distribution']);

    $customer = Customer::create([
        'reseller_id' => $reseller->id,
        'name' => 'Jane Doe',
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);

    expect($customer->fresh())
        ->phone->toBeNull()
        ->email->toBeNull()
        ->address->toBeNull();

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Jane Doe',
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);
});
