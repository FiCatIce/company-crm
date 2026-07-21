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
use Illuminate\Support\Facades\Hash;

/**
 * A SECOND Sales rep (sales2@crm.test) plus a handful of customers they OWN
 * (created_by = sales2), so RBAC row-scoping can be exercised against real data:
 * a Sales user must see only their own customers — and only those customers'
 * money and call log — never another rep's book (Customer::scopeVisibleTo, the
 * customer.view.own gate; DESIGN_RBAC.md §4.2a).
 *
 * The customers are attributed purely via created_by (assigned_to left null), so
 * they exercise the "I entered this customer" half of the visibility gate — the
 * key Sales scoping rule. Each customer also carries some transactions (amount)
 * and some calls, so "Sales sees own money" and "Sales sees own call log" are
 * both testable.
 *
 * Idempotent: the login is upserted by email/extension, and owned customers are
 * topped up to a target count — a re-seed never duplicates them. Password:
 * "password". Wired into DatabaseSeeder and runnable on demand:
 *   php artisan db:seed --class=DemoSalesScopingSeeder
 */
class DemoSalesScopingSeeder extends Seeder
{
    private const EMAIL = 'sales2@crm.test';

    private const EXTENSION = '1007';

    private const TARGET_CUSTOMERS = 5;

    public function run(): void
    {
        $sales2 = $this->upsertSalesUser();

        $product = Product::query()->inRandomOrder()->first()
            ?? Product::factory()->create(['name' => 'Demo Sales 2 Product', 'warranty_months' => 12]);

        // Top up to the target so re-seeding is idempotent (never duplicates).
        $existing = Customer::where('created_by', $sales2->id)->count();

        for ($index = $existing; $index < self::TARGET_CUSTOMERS; $index++) {
            $customer = Customer::factory()
                ->createdBy($sales2)
                ->create([
                    'assigned_to' => null, // visible to sales2 purely via created_by
                    'status' => $index === 0 ? CustomerStatus::Lead : CustomerStatus::Active,
                ]);

            // Purchases with a recorded amount → "Sales sees own customer's money".
            if ($index < 3) {
                foreach (range(1, $index === 0 ? 2 : 1) as $ignored) {
                    Transaction::factory()->forCustomer($customer)->create([
                        'product_id' => $product->id,
                        'amount' => fake()->numberBetween(1_500_000, 25_000_000),
                    ]);
                }
            }

            // Logged calls → "Sales sees own customer's call log".
            if ($index !== 2) {
                Interaction::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->forCustomer($customer)
                    ->call()
                    ->create(['user_id' => $sales2->id]);
            }
        }
    }

    /**
     * Create or update the sales2 QA login and (re)provision its Sales preset.
     * Reuses the row with the same email or PBX extension, so it never duplicates.
     */
    private function upsertSalesUser(): User
    {
        $user = User::where('email', self::EMAIL)->first()
            ?? User::where('extension', self::EXTENSION)->first()
            ?? new User;

        $user->forceFill([
            'name' => 'Sales Demo 2',
            'email' => self::EMAIL,
            'password' => Hash::make('password'),
            'extension' => self::EXTENSION,
            'email_verified_at' => now(),
        ])->save();

        RolePresets::assign($user, RoleName::Sales);

        return $user;
    }
}
