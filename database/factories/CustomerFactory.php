<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reseller_id' => Reseller::factory(),
            'name' => fake()->name(),
            // Optional fields exercise the nullable columns realistically.
            'phone' => fake()->optional(weight: 0.9)->phoneNumber(),
            'email' => fake()->optional(weight: 0.85)->safeEmail(),
            'address' => fake()->optional(weight: 0.7)->address(),
        ];
    }
}
