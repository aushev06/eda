<?php

namespace App\Actions\Pos;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class PlacePosOrder
{
    public function __construct(
        protected CreateOrder $createOrder,
        protected TransitionOrderStatus $transition,
    ) {}

    /**
     * Create a walk-up order WITHOUT firing it to the stations. The order is
     * left unpaid (New / Pending, no payment method) so payment can be taken
     * first — the kitchen must not see an unpaid order. Firing happens only
     * after settlement via fire().
     *
     * @param  array{
     *     delivery_type: string,
     *     items: array<int, array{product_id: int, quantity: int, modifier_ids: array<int, int>}>,
     *     table_id?: int|null,
     *     customer_comment?: ?string
     * }  $data
     */
    public function create(array $data, User $cashier): Order
    {
        return $this->createOrder->handle([
            'customer_name' => 'Гость',
            'customer_phone' => '',
            'delivery_type' => $data['delivery_type'],
            'source' => OrderSource::Pos->value,
            'customer_comment' => $data['customer_comment'] ?? null,
            'items' => $data['items'],
            'table_id' => $data['table_id'] ?? null,
        ]);
    }

    /**
     * Fire a created order to the stations. The Accepted transition broadcasts
     * PosTicketsUpdated to the kitchen/bar screens.
     */
    public function fire(Order $order, User $cashier): Order
    {
        return $this->transition->handle($order, OrderStatus::Accepted, $cashier);
    }
}
