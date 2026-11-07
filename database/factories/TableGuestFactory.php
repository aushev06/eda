<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\TableGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableGuest>
 */
class TableGuestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => null,
            'name' => fake()->firstName(),
            'revoked_at' => null,
        ];
    }
}
