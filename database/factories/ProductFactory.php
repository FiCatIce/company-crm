<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $catalog = [
            'AC Split 1/2 PK',
            'AC Split 1 PK',
            'Kulkas 2 Pintu',
            'Mesin Cuci Front Loading',
            'TV LED 43 inci',
            'TV LED 55 inci',
            'Water Heater Listrik',
            'Dispenser Galon Bawah',
            'Rice Cooker 2 Liter',
            'Microwave 23 Liter',
            'Kipas Angin Berdiri',
            'Vacuum Cleaner',
        ];

        return [
            'name' => fake()->randomElement($catalog),
            'warranty_months' => fake()->randomElement([0, 6, 12, 24, 36]),
        ];
    }
}
