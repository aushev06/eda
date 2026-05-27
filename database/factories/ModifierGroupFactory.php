<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Размер', 'Соус', 'Добавки', 'Тесто', 'Прожарка']).' '.fake()->unique()->numberBetween(1, 99999),
            'min_select' => 0,
            'max_select' => 1,
            'is_required' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => [
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);
    }

    public function multiSelect(int $max = 5): static
    {
        return $this->state(fn () => [
            'min_select' => 0,
            'max_select' => $max,
        ]);
    }
}
