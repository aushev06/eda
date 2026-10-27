<?php

namespace Database\Factories;

use App\Enums\OnlinePaymentStatus;
use App\Models\OnlinePayment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OnlinePayment>
 */
class OnlinePaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'yookassa',
            'reference' => Str::upper(Str::random(10)),
            'external_id' => (string) Str::uuid(),
            'status' => OnlinePaymentStatus::Pending,
            'amount' => fake()->randomFloat(2, 300, 3000),
            'confirmation_url' => fake()->url(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => OnlinePaymentStatus::Succeeded,
            'paid_at' => now(),
        ]);
    }
}
