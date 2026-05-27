<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'customer_id',
        'courier_id',
        'delivery_zone_id',
        'table_id',
        'table_number_snapshot',
        'status',
        'delivery_type',
        'payment_method',
        'payment_status',
        'customer_name',
        'customer_phone',
        'delivery_street',
        'delivery_apartment',
        'delivery_entrance',
        'delivery_floor',
        'delivery_intercom',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_instructions',
        'subtotal',
        'modifiers_total',
        'delivery_fee',
        'discount_total',
        'bonus_used_amount',
        'bonus_earned_amount',
        'total',
        'customer_comment',
        'scheduled_for',
        'accepted_at',
        'ready_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'promo_code',
        'promo_code_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_type' => DeliveryType::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'modifiers_total' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'bonus_used_amount' => 'decimal:2',
            'bonus_earned_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'scheduled_for' => 'datetime',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('created_at');
    }

    public function recalculateTotals(): void
    {
        $this->loadMissing('items.modifiers');

        $subtotal = 0;
        $modifiersTotal = 0;

        foreach ($this->items as $item) {
            $subtotal += (float) $item->unit_price * $item->quantity;
            $modifiersTotal += (float) $item->modifiers_total;
        }

        $this->subtotal = $subtotal;
        $this->modifiers_total = $modifiersTotal;
        $this->total = $subtotal + $modifiersTotal + (float) $this->delivery_fee - (float) $this->discount_total;
    }
}
