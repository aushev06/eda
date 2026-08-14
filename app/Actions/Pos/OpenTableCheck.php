<?php

namespace App\Actions\Pos;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OpenTableCheck
{
    public function __construct(
        protected TransitionOrderStatus $transition,
    ) {}

    /**
     * Return the table's open check, opening a fresh empty one if none exists.
     * One open check per table is guaranteed by locking the Table row for the
     * duration of the transaction, so two cashiers tapping the same table race
     * cleanly: the second sees the first's check instead of creating a second.
     *
     * The empty check is created directly (not through CreateOrder, whose
     * customer/promo/bonus path assumes a non-empty order). No Customer is
     * attached — a phantom "Гость" would otherwise accrue cashback.
     */
    public function handle(Table $table, User $cashier): Order
    {
        return DB::transaction(function () use ($table, $cashier) {
            // Serialize opens on the table row, which always exists.
            Table::query()->whereKey($table->id)->lockForUpdate()->first();

            $existing = Order::query()->openTableCheck($table->id)->first();
            if ($existing) {
                return $existing;
            }

            $order = Order::create([
                'number' => Order::generateNumber(),
                'source' => OrderSource::Pos,
                'delivery_type' => DeliveryType::DineIn,
                'table_id' => $table->id,
                'table_number_snapshot' => (string) $table->number,
                'status' => OrderStatus::New,
                'payment_method' => null,
                'payment_status' => PaymentStatus::Pending,
                'customer_id' => null,
                'customer_name' => 'Гость',
                'customer_phone' => '',
                'opened_at' => now(),
                'subtotal' => 0,
                'modifiers_total' => 0,
                'delivery_fee' => 0,
                'discount_total' => 0,
                'bonus_used_amount' => 0,
                'total' => 0,
            ]);

            // New -> Accepted keeps the event trail and pins the check into the
            // "open" status set the floor view and KDS query rely on.
            return $this->transition->handle($order, OrderStatus::Accepted, $cashier);
        });
    }
}
