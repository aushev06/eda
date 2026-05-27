<?php

namespace Database\Factories;

use App\Models\LoyaltyLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyLevel>
 */
class LoyaltyLevelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'min_lifetime_spend' => 0,
            'cashback_percent' => 10,
            'sort_order' => 0,
        ];
    }
}
