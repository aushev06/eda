<?php

namespace Database\Factories;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCodeUsage>
 */
class PromoCodeUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promo_code_id' => PromoCode::factory(),
            'customer_id' => null,
            'order_id' => null,
            'discount_amount' => 100,
            'created_at' => now(),
        ];
    }
}
