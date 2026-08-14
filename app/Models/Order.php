<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'source',
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
        'opened_at',
        'paid_at',
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
            'source' => OrderSource::class,
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
            'opened_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    /**
     * Change owed to the guest: cash received beyond what cash covered on the
     * bill. Card tenders are exact and never produce change.
     */
    public function changeDue(): float
    {
        return (float) $this->payments
            ->where('method', PaymentMethod::Cash)
            ->sum(fn (OrderPayment $p) => (float) ($p->received_amount ?? $p->amount) - (float) $p->amount);
    }

    /**
     * Recompute money fields from the live (non-voided) line items.
     * Voided items stay in the table for the shift report but never count
     * toward what the guest pays.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('items.modifiers');

        $subtotal = 0;
        $modifiersTotal = 0;

        foreach ($this->items as $item) {
            if ($item->voided_at !== null) {
                continue;
            }
            $subtotal += (float) $item->unit_price * $item->quantity;
            $modifiersTotal += (float) $item->modifiers_total;
        }

        $this->subtotal = $subtotal;
        $this->modifiers_total = $modifiersTotal;
        $this->total = $subtotal + $modifiersTotal + (float) $this->delivery_fee - (float) $this->discount_total;
    }

    /**
     * The open table check for a dine-in POS table, if one exists: a pos
     * dine-in order still being served and not yet paid.
     */
    public function scopeOpenTableCheck(Builder $query, int $tableId): Builder
    {
        return $query
            ->where('table_id', $tableId)
            ->where('source', OrderSource::Pos)
            ->where('delivery_type', DeliveryType::DineIn)
            ->whereIn('status', [OrderStatus::Accepted, OrderStatus::Preparing])
            ->where('payment_status', PaymentStatus::Pending);
    }

    /**
     * Whether this order is an open, unpaid dine-in POS table check.
     */
    public function isOpenTableCheck(): bool
    {
        return $this->source === OrderSource::Pos
            && $this->delivery_type === DeliveryType::DineIn
            && $this->payment_status === PaymentStatus::Pending
            && in_array($this->status, [OrderStatus::Accepted, OrderStatus::Preparing], true);
    }

    public static function generateNumber(): string
    {
        do {
            $candidate = 'A-'.Str::upper(Str::random(6));
        } while (static::query()->where('number', $candidate)->exists());

        return $candidate;
    }
}
