<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic demo data: staff agents, a product catalog, a pool of
     * customers attributed to agents, their purchases, and interaction history.
     * (The reseller tree was removed in L2-D — ownership now flows through
     * assigned_to/created_by, not a distributor hierarchy.)
     */
    public function run(): void
    {
        // Roles are a prerequisite for agents; RoleSeeder is idempotent so this is
        // safe both standalone and after DatabaseSeeder already ran it.
        $this->call(RoleSeeder::class);

        $products = $this->seedProducts();
        $agents = $this->seedAgents();

        Customer::factory()
            ->count(40)
            ->create([
                // Closure attributes are evaluated per row, so ownership + status
                // vary across the pool instead of being stamped identically.
                'assigned_to' => fn () => fake()->boolean(65) ? $agents->random()->id : null,
                'status' => fn () => fake()->randomElement([
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
    }

    /**
     * Seed a small pool of staff agents across the roles (one of each new role
     * so later batches have realistic fixtures). PBX extensions let CTI ingest
     * map agent_extension -> user_id.
     *
     * @return Collection<int, User>
     */
    protected function seedAgents(): Collection
    {
        $agents = collect([
            $this->makeAgent('Sinta Wijaya', '1001', RoleName::Supervisor),
        ]);

        $cs = ['Dewi Lestari' => '1002', 'Rangga Pratama' => '1003', 'Putri Anggraini' => '1004'];
        foreach ($cs as $name => $extension) {
            $agents->push($this->makeAgent($name, $extension, RoleName::Cs));
        }

        // New-role examples. Created but not yet exercised — their row-scoping
        // lands in B1 (DESIGN_RBAC.md §8).
        $agents->push($this->makeAgent('Bayu Saputra', '1005', RoleName::Sales));
        $agents->push($this->makeAgent('Maya Kusuma', '1006', RoleName::Maintenance));

        return $agents;
    }

    /**
     * Create a staff user and provision it from its role preset.
     */
    protected function makeAgent(string $name, string $extension, RoleName $role): User
    {
        $user = User::factory()->create(['name' => $name, 'extension' => $extension]);
        RolePresets::assign($user, $role);

        return $user;
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
}
