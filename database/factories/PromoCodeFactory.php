<?php

namespace Database\Factories;

use App\Enums\PromoDiscountType;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'description' => null,
            'discount_type' => PromoDiscountType::Percent,
            'value' => 10,
            'max_discount_amount' => null,
            'min_order_amount' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'max_uses_global' => null,
            'max_uses_per_customer' => null,
            'first_order_only' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => [
            'discount_type' => PromoDiscountType::Fixed,
            'value' => $amount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function firstOrderOnly(): static
    {
        return $this->state(fn () => ['first_order_only' => true]);
    }
}
