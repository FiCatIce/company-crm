<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            // Keep the transaction's reseller consistent with the customer's reseller.
            'reseller_id' => fn (array $attributes) => Customer::whereKey($attributes['customer_id'])->value('reseller_id'),
            'purchased_at' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            // Nullable (some legacy rows have no recorded value); whole rupiah.
            'amount' => fake()->optional(weight: 0.85)->numberBetween(150_000, 60_000_000),
        ];
    }

    /**
     * Attach the transaction to an existing customer, inheriting its reseller.
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => [
            'customer_id' => $customer->id,
            'reseller_id' => $customer->reseller_id,
        ]);
    }
}
