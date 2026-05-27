<?php

namespace App\Models;

use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'polygon',
        'delivery_fee',
        'min_order_amount',
        'estimated_minutes_min',
        'estimated_minutes_max',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'delivery_fee' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'estimated_minutes_min' => 'integer',
            'estimated_minutes_max' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Determine whether a (lat, lng) point lies inside this zone's polygon.
     *
     * Uses the ray-casting algorithm — works for arbitrary simple polygons,
     * no spatial extension needed. Polygon stored as array of [lat, lng] pairs.
     */
    public function containsPoint(float $lat, float $lng): bool
    {
        $polygon = $this->polygon ?? [];
        $vertexCount = count($polygon);

        if ($vertexCount < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $vertexCount - 1; $i < $vertexCount; $j = $i++) {
            [$latI, $lngI] = $polygon[$i];
            [$latJ, $lngJ] = $polygon[$j];

            $intersects = (($lngI > $lng) !== ($lngJ > $lng))
                && ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 1e-12) + $latI);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @return array{fee: float, min_order: float, min_minutes: int, max_minutes: int}
     */
    public function tariff(): array
    {
        return [
            'fee' => (float) $this->delivery_fee,
            'min_order' => (float) $this->min_order_amount,
            'min_minutes' => $this->estimated_minutes_min,
            'max_minutes' => $this->estimated_minutes_max,
        ];
    }
}
