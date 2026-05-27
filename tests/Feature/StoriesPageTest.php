<?php

use App\Models\Story;
use App\Models\StoryGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('exposes active story groups to the catalog page in sort order', function () {
    $second = StoryGroup::factory()->create(['title' => 'Акции', 'sort_order' => 20]);
    Story::factory()->for($second, 'group')->create(['sort_order' => 0]);

    $first = StoryGroup::factory()->create(['title' => 'Новинки', 'sort_order' => 10]);
    Story::factory()->for($first, 'group')->create(['sort_order' => 1]);
    Story::factory()->for($first, 'group')->create(['sort_order' => 0]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/index')
            ->has('story_groups', 2)
            ->where('story_groups.0.title', 'Новинки')
            ->where('story_groups.1.title', 'Акции')
            ->has('story_groups.0.stories', 2));
});

it('hides inactive groups and inactive stories from the catalog page', function () {
    $inactiveGroup = StoryGroup::factory()->inactive()->create();
    Story::factory()->for($inactiveGroup, 'group')->create();

    $visibleGroup = StoryGroup::factory()->create();
    Story::factory()->for($visibleGroup, 'group')->create(['sort_order' => 0]);
    Story::factory()->for($visibleGroup, 'group')->inactive()->create(['sort_order' => 1]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('story_groups', 1)
            ->has('story_groups.0.stories', 1));
});

it('omits groups that have no active stories', function () {
    $empty = StoryGroup::factory()->create();
    Story::factory()->for($empty, 'group')->inactive()->create();

    $populated = StoryGroup::factory()->create();
    Story::factory()->for($populated, 'group')->create();

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('story_groups', 1)
            ->where('story_groups.0.id', $populated->id));
});

it('exposes media_url and preview_url accessors for the player', function () {
    $group = StoryGroup::factory()->create(['preview_path' => 'story-previews/abc.jpg']);
    Story::factory()->for($group, 'group')->create([
        'media_type' => 'video',
        'media_path' => 'stories/clip.mp4',
        'duration_ms' => 7000,
        'cta_label' => 'Открыть',
        'cta_url' => '/loyalty',
    ]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('story_groups.0.preview_url', fn ($url) => is_string($url) && str_contains($url, 'story-previews/abc.jpg'))
            ->where('story_groups.0.stories.0.media_url', fn ($url) => is_string($url) && str_contains($url, 'stories/clip.mp4'))
            ->where('story_groups.0.stories.0.media_type', 'video')
            ->where('story_groups.0.stories.0.duration_ms', 7000)
            ->where('story_groups.0.stories.0.cta_label', 'Открыть')
            ->where('story_groups.0.stories.0.cta_url', '/loyalty'));
});
