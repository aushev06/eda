<?php

namespace App\Actions\Orders;

use App\Actions\Loyalty\SpendBonuses;
use App\Actions\Promo\ApplyPromoCode;
use App\Enums\DeliveryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function __construct(
        protected ApplyPromoCode $applyPromoCode,
        protected SpendBonuses $spendBonuses,
        protected PriceOrderItems $priceOrderItems,
    ) {}

    /**
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     delivery_type: string,
     *     payment_method?: ?string,
     *     customer_comment?: ?string,
     *     items: array<int, array{product_id: int, quantity: int, modifier_ids: array<int, int>}>,
     *     delivery?: array{
     *         zone_id?: ?int,
     *         street?: ?string,
     *         apartment?: ?string,
     *         entrance?: ?string,
     *         floor?: ?string,
     *         intercom?: ?string,
     *         instructions?: ?string,
     *     },
     *     promo_code?: ?string,
     *     bonus_to_use?: int|float|null,
     *     table_id?: int|null,
     *     authenticated_customer_id?: int|null,
     *     source?: ?string,
     *     payment_status?: ?string
     * }  $data
     */
    public function handle(array $data): Order
    {
        $deliveryType = DeliveryType::from($data['delivery_type']);
        $paymentMethod = isset($data['payment_method']) ? PaymentMethod::from($data['payment_method']) : null;
        $source = isset($data['source']) ? OrderSource::from($data['source']) : OrderSource::Site;
        $paymentStatus = isset($data['payment_status']) ? PaymentStatus::from($data['payment_status']) : PaymentStatus::Pending;

        $zone = null;
        if ($deliveryType === DeliveryType::Delivery) {
            $zoneId = $data['delivery']['zone_id'] ?? null;
            $zone = $zoneId ? DeliveryZone::query()->active()->find($zoneId) : null;
            if (! $zone) {
                throw ValidationException::withMessages([
                    'delivery.zone_id' => 'Выбранная зона доставки недоступна.',
                ]);
            }
        }

        $table = null;
        if ($deliveryType === DeliveryType::DineIn) {
            $tableId = $data['table_id'] ?? null;
            $table = $tableId ? Table::query()->active()->find($tableId) : null;
            if (! $table) {
                throw ValidationException::withMessages([
                    'table_id' => 'Столик не найден. Отсканируйте QR-код заново.',
                ]);
            }
        }

        [$subtotal, $modifiersTotal, $itemRows] = $this->priceOrderItems->handle($data['items']);

        $deliveryFee = $zone ? (float) $zone->delivery_fee : 0.0;

        $promo = null;
        $discountTotal = 0.0;
        $promoCodeString = isset($data['promo_code']) && trim((string) $data['promo_code']) !== ''
            ? trim((string) $data['promo_code'])
            : null;

        if ($promoCodeString !== null) {
            $applied = $this->applyPromoCode->handle(
                code: $promoCodeString,
                subtotal: $subtotal + $modifiersTotal,
                customerPhone: $data['customer_phone'] ?? null,
            );
            $promo = $applied['promo'];
            $discountTotal = $applied['discount'];
        }

        $bonusRequested = isset($data['bonus_to_use']) ? (float) $data['bonus_to_use'] : 0.0;
        $bonusUsed = 0.0;
        $authedCustomer = null;
        if ($bonusRequested > 0) {
            $authedCustomerId = $data['authenticated_customer_id'] ?? null;
            if (! $authedCustomerId) {
                throw ValidationException::withMessages([
                    'bonus_to_use' => 'Войдите в аккаунт, чтобы списать бонусы.',
                ]);
            }
            $authedCustomer = Customer::query()->findOrFail($authedCustomerId);
            $bonusUsed = $this->spendBonuses->quote($authedCustomer, $bonusRequested, $subtotal + $modifiersTotal);
        }

        $total = $subtotal + $modifiersTotal + $deliveryFee - $discountTotal - $bonusUsed;

        if ($zone && $subtotal + $modifiersTotal < (float) $zone->min_order_amount) {
            throw ValidationException::withMessages([
                'items' => "Минимальная сумма для зоны «{$zone->name}» — ".number_format((float) $zone->min_order_amount, 0, ',', ' ').' ₽.',
            ]);
        }

        return DB::transaction(function () use (
            $data, $deliveryType, $paymentMethod, $zone, $table, $itemRows, $promo,
            $subtotal, $modifiersTotal, $deliveryFee, $discountTotal, $total,
            $bonusUsed, $authedCustomer, $source, $paymentStatus
        ) {
            // Walk-up POS orders carry no guest identity — linking them all to a
            // shared phantom "Гость" customer would pollute loyalty with cashback.
            $customer = $authedCustomer;
            if (! $customer && $source !== OrderSource::Pos) {
                $customer = Customer::query()->firstOrCreate(
                    ['phone' => $data['customer_phone']],
                    ['name' => $data['customer_name']],
                );
            }

            $deliveryFields = $deliveryType === DeliveryType::Delivery
                ? [
                    'delivery_street' => $data['delivery']['street'] ?? null,
                    'delivery_apartment' => $data['delivery']['apartment'] ?? null,
                    'delivery_entrance' => $data['delivery']['entrance'] ?? null,
                    'delivery_floor' => $data['delivery']['floor'] ?? null,
                    'delivery_intercom' => $data['delivery']['intercom'] ?? null,
                    'delivery_instructions' => $data['delivery']['instructions'] ?? null,
                ]
                : [];

            $order = Order::create(array_merge([
                'number' => Order::generateNumber(),
                'source' => $source,
                'customer_id' => $customer?->id,
                'delivery_zone_id' => $zone?->id,
                'table_id' => $table?->id,
                'table_number_snapshot' => $table ? (string) $table->number : null,
                'promo_code' => $promo?->code,
                'promo_code_id' => $promo?->id,
                'status' => OrderStatus::New,
                'delivery_type' => $deliveryType,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'subtotal' => $subtotal,
                'modifiers_total' => $modifiersTotal,
                'delivery_fee' => $deliveryFee,
                'discount_total' => $discountTotal,
                'bonus_used_amount' => $bonusUsed,
                'total' => $total,
                'customer_comment' => $data['customer_comment'] ?? null,
            ], $deliveryFields));

            foreach ($itemRows as $row) {
                $item = $order->items()->create([
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'modifiers_total' => $row['modifiers_total'],
                    'line_total' => $row['line_total'],
                ]);

                foreach ($row['modifiers'] as $modSnapshot) {
                    $item->modifiers()->create($modSnapshot);
                }
            }

            $order->statusEvents()->create([
                'from_status' => null,
                'to_status' => OrderStatus::New,
                'created_at' => now(),
            ]);

            if ($promo) {
                $promo->usages()->create([
                    'customer_id' => $customer?->id,
                    'order_id' => $order->id,
                    'discount_amount' => $discountTotal,
                    'created_at' => now(),
                ]);
            }

            if ($bonusUsed > 0) {
                $this->spendBonuses->commit($customer, $order, $bonusUsed);
            }

            return $order->refresh();
        });
    }
}
