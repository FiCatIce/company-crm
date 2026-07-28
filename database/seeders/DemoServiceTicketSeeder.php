<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * M1 (DESIGN_MAINTENANCE.md) — demo service tickets over EXISTING demo customers
 * and maintenance agents. DORMANT: deliberately NOT wired into DatabaseSeeder
 * until the module has a surface (M4) — until then it is only ever run by hand.
 * Idempotent: tops up to the target count, never duplicates.
 */
class DemoServiceTicketSeeder extends Seeder
{
    private const TARGET = 12;

    public function run(): void
    {
        $existing = ServiceTicket::query()->count();
        if ($existing >= self::TARGET) {
            return;
        }

        $customers = Customer::query()->inRandomOrder()->limit(self::TARGET)->get();
        if ($customers->isEmpty()) {
            return; // nothing meaningful to attach tickets to yet
        }

        $technicians = User::role('maintenance')->where('is_active', true)->get();
        $creators = User::role('cs')->where('is_active', true)->get();

        $needed = self::TARGET - $existing;

        foreach ($customers->take($needed) as $index => $customer) {
            $factory = ServiceTicket::factory();

            // A realistic mix: mostly resolved history, a working queue, a pool.
            $factory = match ($index % 4) {
                0, 1 => $factory->done(),
                2 => $factory->inProgress(),
                default => $factory, // received (pool)
            };

            $transaction = $customer->transactions()->inRandomOrder()->first();

            $factory->create([
                'customer_id' => $customer->id,
                'transaction_id' => $transaction?->id,
                'product_id' => $transaction?->product_id,
                'item_description' => $transaction === null ? 'Unit lama (di luar catatan pembelian)' : null,
                'assigned_to' => $technicians->isNotEmpty() && $index % 4 !== 3
                    ? $technicians->random()->id
                    : null,
                'created_by' => $creators->isNotEmpty() ? $creators->random()->id : null,
            ]);
        }
    }
}
