<?php

namespace App\Models;

use App\Enums\OnlinePaymentStatus;
use Database\Factories\OnlinePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay an order through an online payment gateway.
 */
class OnlinePayment extends Model
{
    /** @use HasFactory<OnlinePaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'gateway',
        'reference',
        'external_id',
        'status',
        'amount',
        'confirmation_url',
        'error_message',
        'payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OnlinePaymentStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Amount in kopecks, the unit every supported gateway expects.
     */
    public function amountInMinorUnits(): int
    {
        return (int) round((float) $this->amount * 100);
    }
}
