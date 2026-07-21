<?php

use App\Models\Customer;

it('allows customers to be created with null phone, email, and address', function () {
    $customer = Customer::create([
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
