<?php

namespace App\Models;

use Database\Factories\StoryGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StoryGroup extends Model
{
    /** @use HasFactory<StoryGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'preview_path',
        'sort_order',
        'is_active',
    ];

    /** @var array<int, string> */
    protected $appends = ['preview_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Public URL for the preview thumbnail shown on the home page.
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->preview_path;
            if (! $path) {
                return null;
            }

            return Storage::disk('public')->url($path);
        });
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
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
