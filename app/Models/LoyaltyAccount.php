<?php

namespace App\Models;

use Database\Factories\LoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    /** @use HasFactory<LoyaltyAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'balance',
        'lifetime_earned',
        'lifetime_spent_on_orders',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'lifetime_earned' => 'decimal:2',
            'lifetime_spent_on_orders' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class)->orderByDesc('created_at');
    }

    public function currentLevel(): ?LoyaltyLevel
    {
        return LoyaltyLevel::forLifetimeSpend((float) $this->lifetime_spent_on_orders);
    }

    public function nextLevel(): ?LoyaltyLevel
    {
        return LoyaltyLevel::query()
            ->where('min_lifetime_spend', '>', $this->lifetime_spent_on_orders)
            ->orderBy('min_lifetime_spend')
            ->first();
    }

    public static function forCustomer(Customer $customer): self
    {
        return self::firstOrCreate(['customer_id' => $customer->id]);
    }
}
