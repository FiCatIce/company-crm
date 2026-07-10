<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic demo data: staff agents, a reseller tree, a product catalog,
     * customers per leaf reseller, their purchases, and interaction history.
     */
    public function run(): void
    {
        // Roles are a prerequisite for agents; RoleSeeder is idempotent so this is
        // safe both standalone and after DatabaseSeeder already ran it.
        $this->call(RoleSeeder::class);

        $products = $this->seedProducts();
        $agents = $this->seedAgents();
        $leafResellers = $this->seedResellerTree();

        $leafResellers->each(function (Reseller $reseller) use ($products, $agents) {
            Customer::factory()
                ->count(fake()->numberBetween(3, 6))
                ->create([
                    'reseller_id' => $reseller->id,
                    'assigned_to' => fake()->boolean(65) ? $agents->random()->id : null,
                    'status' => fake()->randomElement([
                        CustomerStatus::Active, CustomerStatus::Active, CustomerStatus::Active,
                        CustomerStatus::Lead, CustomerStatus::Inactive, CustomerStatus::Churned,
                    ]),
                ])
                ->each(function (Customer $customer) use ($products, $agents) {
                    foreach (range(1, fake()->numberBetween(1, 3)) as $ignored) {
                        Transaction::factory()
                            ->forCustomer($customer)
                            ->create(['product_id' => $products->random()->id]);
                    }

                    $interactionCount = fake()->numberBetween(0, 8);

                    for ($i = 0; $i < $interactionCount; $i++) {
                        Interaction::factory()
                            ->forCustomer($customer)
                            ->create(['user_id' => $agents->random()->id]);
                    }
                });
        });
    }

    /**
     * Seed a small pool of staff agents (one supervisor + a few CS).
     *
     * @return Collection<int, User>
     */
    protected function seedAgents(): Collection
    {
        // PBX extensions so CTI ingest can map agent_extension -> user_id.
        $agents = collect([
            User::factory()->create(['name' => 'Sinta Wijaya', 'extension' => '1001'])->assignRole('supervisor'),
        ]);

        $roster = ['Dewi Lestari' => '1002', 'Rangga Pratama' => '1003', 'Putri Anggraini' => '1004'];
        foreach ($roster as $name => $extension) {
            $agents->push(User::factory()->create(['name' => $name, 'extension' => $extension])->assignRole('cs'));
        }

        return $agents;
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
