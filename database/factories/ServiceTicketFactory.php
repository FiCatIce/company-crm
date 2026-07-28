<?php

namespace Database\Factories;

use App\Enums\TicketCoverage;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceTicket>
 */
class ServiceTicketFactory extends Factory
{
    protected $model = ServiceTicket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'transaction_id' => null,
            'product_id' => null,
            'item_description' => fake()->randomElement([
                'TV LED 43 inci', 'Kulkas 2 pintu', 'Mesin cuci front loading',
                'AC split 1 PK', 'Microwave 23 liter',
            ]),
            'serial_no' => fake()->optional(weight: 0.7)->bothify('SN-####-????'),
            'complaint' => fake()->sentence(8),
            'work_notes' => null,
            'resolution' => null,
            'coverage' => fake()->randomElement(TicketCoverage::cases()),
            // Normal-heavy mix: priority is the exception, not the rule.
            'priority' => fake()->randomElement([
                TicketPriority::Normal, TicketPriority::Normal, TicketPriority::Normal,
                TicketPriority::Low, TicketPriority::High,
            ]),
            'status' => TicketStatus::Received,
            'assigned_to' => null,
            'created_by' => User::factory(),
            'received_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Claimed and being worked on.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::InProgress,
            'assigned_to' => User::factory(),
            'started_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'work_notes' => fake()->sentence(6),
        ]);
    }

    /**
     * Finished, with the resolution the flow will require on transition (M2).
     */
    public function done(): static
    {
        return $this->state(function (array $attributes): array {
            $started = fake()->dateTimeBetween('-14 days', '-2 days');

            return [
                'status' => TicketStatus::Done,
                'assigned_to' => User::factory(),
                'started_at' => $started,
                'finished_at' => fake()->dateTimeBetween($started, 'now'),
                'work_notes' => fake()->sentence(6),
                'resolution' => fake()->sentence(10),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Cancelled,
            'cancelled_at' => fake()->dateTimeBetween('-14 days', 'now'),
            'work_notes' => 'Dibatalkan: '.fake()->sentence(5),
        ]);
    }
}
