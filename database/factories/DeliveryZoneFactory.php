<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Центр', 'Север', 'Юг', 'Восток', 'Запад']).' '.fake()->unique()->numberBetween(1, 9999),
            'color' => fake()->hexColor(),
            // Простой квадрат вокруг центра Москвы — подменяй в state().
            'polygon' => [
                [55.7458, 37.6073],
                [55.7458, 37.6273],
                [55.7658, 37.6273],
                [55.7658, 37.6073],
            ],
            'delivery_fee' => fake()->randomFloat(2, 100, 400),
            'min_order_amount' => fake()->randomFloat(2, 500, 2000),
            'estimated_minutes_min' => 30,
            'estimated_minutes_max' => 60,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $polygon
     */
    public function withPolygon(array $polygon): static
    {
        return $this->state(fn () => ['polygon' => $polygon]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
