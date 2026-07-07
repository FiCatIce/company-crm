<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic demo data: a reseller tree, a product catalog,
     * customers per leaf reseller, and their purchase transactions.
     */
    public function run(): void
    {
        $products = $this->seedProducts();
        $leafResellers = $this->seedResellerTree();

        $leafResellers->each(function (Reseller $reseller) use ($products) {
            Customer::factory()
                ->count(fake()->numberBetween(3, 6))
                ->create(['reseller_id' => $reseller->id])
                ->each(function (Customer $customer) use ($products) {
                    foreach (range(1, fake()->numberBetween(1, 3)) as $ignored) {
                        Transaction::factory()
                            ->forCustomer($customer)
                            ->create(['product_id' => $products->random()->id]);
                    }
                });
        });
    }

    /**
     * Seed a curated, realistic product catalog.
     *
     * @return Collection<int, Product>
     */
    protected function seedProducts(): Collection
    {
        $catalog = [
            ['name' => 'AC Split 1/2 PK', 'warranty_months' => 12],
            ['name' => 'AC Split 1 PK', 'warranty_months' => 12],
            ['name' => 'Kulkas 2 Pintu', 'warranty_months' => 24],
            ['name' => 'Mesin Cuci Front Loading', 'warranty_months' => 24],
            ['name' => 'TV LED 43 inci', 'warranty_months' => 24],
            ['name' => 'Water Heater Listrik', 'warranty_months' => 36],
            ['name' => 'Dispenser Galon Bawah', 'warranty_months' => 12],
            ['name' => 'Rice Cooker 2 Liter', 'warranty_months' => 6],
            ['name' => 'Microwave 23 Liter', 'warranty_months' => 12],
            ['name' => 'Kipas Angin Berdiri', 'warranty_months' => 6],
        ];

        return collect($catalog)->map(fn (array $product) => Product::factory()->create($product));
    }

    /**
     * Seed a three-level reseller tree (national -> regional -> city).
     *
     * @return Collection<int, Reseller> the leaf (city-level) resellers
     */
    protected function seedResellerTree(): Collection
    {
        $leaves = collect();

        Reseller::factory()->count(2)->create()->each(function (Reseller $national) use ($leaves) {
            Reseller::factory()
                ->count(fake()->numberBetween(2, 3))
                ->create(['parent_id' => $national->id])
                ->each(function (Reseller $regional) use ($leaves) {
                    $cities = Reseller::factory()
                        ->count(fake()->numberBetween(1, 3))
                        ->create(['parent_id' => $regional->id]);

                    $leaves->push(...$cities);
                });
        });

        return $leaves;
    }
}
