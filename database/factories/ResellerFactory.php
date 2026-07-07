<?php

namespace Database\Factories;

use App\Models\Reseller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reseller>
 */
class ResellerFactory extends Factory
{
    protected $model = Reseller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->company(),
        ];
    }

    /**
     * Make this reseller a child of the given reseller (one level down the tree).
     */
    public function childOf(Reseller $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
