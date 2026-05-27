<?php

namespace App\Models;

use Database\Factories\LoyaltyLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyLevel extends Model
{
    /** @use HasFactory<LoyaltyLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'min_lifetime_spend',
        'cashback_percent',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_lifetime_spend' => 'decimal:2',
            'cashback_percent' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('min_lifetime_spend')->orderBy('sort_order');
    }

    /**
     * Find the level a given lifetime spend qualifies for. Returns the
     * highest level whose threshold is reached, or null if no levels exist.
     */
    public static function forLifetimeSpend(float $lifetimeSpend): ?self
    {
        return self::query()
            ->where('min_lifetime_spend', '<=', $lifetimeSpend)
            ->orderByDesc('min_lifetime_spend')
            ->first();
    }
}
