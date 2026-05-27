<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+7'.fake()->unique()->numerify('9#########'),
            'name' => fake()->firstName(),
            'email' => fake()->optional()->safeEmail(),
            'note' => null,
        ];
    }
}
