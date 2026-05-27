<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'image_path',
        'weight_grams',
        'calories',
        'is_active',
        'in_stop_list',
        'sort_order',
    ];

    /** @var array<int, string> */
    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'weight_grams' => 'integer',
            'calories' => 'integer',
            'is_active' => 'boolean',
            'in_stop_list' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Public URL for the product image, or null if no image was uploaded.
     * Encapsulates the storage layout so the frontend doesn't have to know
     * the disk or path conventions.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->image_path;
            if (! $path) {
                return null;
            }

            return Storage::disk('public')->url($path);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
