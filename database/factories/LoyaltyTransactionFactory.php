<?php

namespace Database\Factories;

use App\Enums\LoyaltyTransactionType;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyTransaction>
 */
class LoyaltyTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'order_id' => null,
            'type' => LoyaltyTransactionType::Earn,
            'amount' => 100,
            'balance_after' => 100,
            'note' => null,
            'created_at' => now(),
        ];
    }
}
