<?php

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
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
            'assigned_to' => null,
            'name' => fake()->name(),
            // Realistic Indonesian mobile so the phone_normalized mutator resolves.
            'phone' => fake()->optional(weight: 0.9)->numerify('08##########'),
            'email' => fake()->optional(weight: 0.85)->safeEmail(),
            'address' => fake()->optional(weight: 0.7)->address(),
            'status' => CustomerStatus::Active,
            'source' => fake()->optional(weight: 0.6)->randomElement(CustomerSource::cases()),
        ];
    }

    /**
     * Attribute the customer to a creator. created_by is guarded (not fillable),
     * so it is set after creation rather than via the attribute array.
     */
    public function createdBy(User|int $user): static
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->afterCreating(
            fn (Customer $customer) => $customer->forceFill(['created_by' => $id])->save()
        );
    }
}
