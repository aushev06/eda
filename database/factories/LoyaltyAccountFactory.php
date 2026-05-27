<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAccount>
 */
class LoyaltyAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_spent_on_orders' => 0,
        ];
    }
}
