<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 100, 5000),
            'image_path' => null,
            'weight_grams' => fake()->numberBetween(150, 800),
            'calories' => fake()->numberBetween(150, 1200),
            'is_active' => true,
            'in_stop_list' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inStopList(): static
    {
        return $this->state(fn () => ['in_stop_list' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
