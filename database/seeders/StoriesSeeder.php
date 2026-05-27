<?php

namespace Database\Seeders;

use App\Models\Story;
use App\Models\StoryGroup;
use Illuminate\Database\Seeder;

class StoriesSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'title' => 'Новинки',
                'stories' => [
                    ['type' => 'image', 'duration' => 5000],
                    ['type' => 'image', 'duration' => 5000],
                ],
            ],
            [
                'title' => 'Акции',
                'stories' => [
                    ['type' => 'image', 'duration' => 6000, 'cta_label' => 'Открыть меню', 'cta_url' => '/'],
                    ['type' => 'image', 'duration' => 5000],
                ],
            ],
            [
                'title' => 'Бонусы',
                'stories' => [
                    ['type' => 'image', 'duration' => 5000, 'cta_label' => 'Подробнее', 'cta_url' => '/loyalty'],
                ],
            ],
        ];

        foreach ($groups as $sort => $data) {
            $group = StoryGroup::create([
                'title' => $data['title'],
                'preview_path' => null,
                'sort_order' => $sort,
                'is_active' => true,
            ]);

            foreach ($data['stories'] as $i => $story) {
                Story::create([
                    'story_group_id' => $group->id,
                    'media_type' => $story['type'],
                    'media_path' => 'stories/sample-'.($i + 1).'.jpg',
                    'duration_ms' => $story['duration'],
                    'cta_label' => $story['cta_label'] ?? null,
                    'cta_url' => $story['cta_url'] ?? null,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
        }
    }
}
