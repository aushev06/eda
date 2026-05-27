<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'street' => fake()->streetAddress(),
            'apartment' => (string) fake()->numberBetween(1, 200),
            'entrance' => (string) fake()->numberBetween(1, 8),
            'floor' => (string) fake()->numberBetween(1, 20),
            'intercom' => (string) fake()->numberBetween(1, 200),
            'latitude' => fake()->latitude(55.5, 56.0),
            'longitude' => fake()->longitude(37.3, 37.9),
            'instructions' => null,
            'is_default' => false,
        ];
    }
}
