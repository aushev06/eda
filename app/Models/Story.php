<?php

namespace App\Models;

use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory;

    protected $fillable = [
        'story_group_id',
        'media_type',
        'media_path',
        'duration_ms',
        'cta_label',
        'cta_url',
        'sort_order',
        'is_active',
    ];

    /** @var array<int, string> */
    protected $appends = ['media_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_ms' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Public URL for the uploaded media file.
     */
    protected function mediaUrl(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->media_path;
            if (! $path) {
                return null;
            }

            return Storage::disk('public')->url($path);
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StoryGroup::class, 'story_group_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
