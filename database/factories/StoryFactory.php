<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\StoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_group_id' => StoryGroup::factory(),
            'media_type' => 'image',
            'media_path' => 'stories/sample.jpg',
            'duration_ms' => 5000,
            'cta_label' => null,
            'cta_url' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'media_type' => 'video',
            'media_path' => 'stories/sample.mp4',
            'duration_ms' => 8000,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
