<?php

namespace Database\Factories;

use App\Enums\InteractionDirection;
use App\Enums\InteractionOutcome;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interaction>
 */
class InteractionFactory extends Factory
{
    protected $model = Interaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Call-heavy mix (CTI focus): call weighted above the other channels.
        $type = fake()->randomElement([
            InteractionType::Call, InteractionType::Call, InteractionType::Call,
            InteractionType::WhatsApp, InteractionType::WhatsApp,
            InteractionType::Email, InteractionType::Note, InteractionType::Visit,
        ]);

        $isCall = $type === InteractionType::Call;

        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'type' => $type,
            'direction' => $type === InteractionType::Note
                ? null
                : fake()->randomElement([InteractionDirection::In, InteractionDirection::Out]),
            'subject' => fake()->optional()->sentence(4),
            'body' => fake()->optional(weight: 0.7)->paragraph(),
            'outcome' => $isCall ? fake()->randomElement(InteractionOutcome::cases()) : null,
            'duration_sec' => $isCall ? fake()->numberBetween(20, 1800) : null,
            'occurred_at' => fake()->dateTimeBetween('-90 days', 'now'),
            'source' => InteractionSource::Manual,
            'external_ref' => null,
        ];
    }

    /**
     * Attach the interaction to an existing customer.
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => ['customer_id' => $customer->id]);
    }

    /**
     * A phone call (type=call with a call outcome + duration).
     */
    public function call(): static
    {
        return $this->state(fn () => [
            'type' => InteractionType::Call,
            'direction' => fake()->randomElement([InteractionDirection::In, InteractionDirection::Out]),
            'outcome' => fake()->randomElement(InteractionOutcome::cases()),
            'duration_sec' => fake()->numberBetween(20, 1800),
        ]);
    }
}
