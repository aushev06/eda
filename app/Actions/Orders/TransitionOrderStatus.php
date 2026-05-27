<?php

namespace App\Actions\Orders;

use App\Actions\Loyalty\GrantOrderBonuses;
use App\Actions\Loyalty\RefundOrderBonuses;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransitionOrderStatus
{
    public function __construct(
        protected GrantOrderBonuses $grantBonuses,
        protected RefundOrderBonuses $refundBonuses,
    ) {}

    /**
     * Allowed transitions from each status.
     *
     * @var array<string, list<OrderStatus>>
     */
    public const TRANSITIONS = [
        'new' => [OrderStatus::Accepted, OrderStatus::Cancelled],
        'accepted' => [OrderStatus::Preparing, OrderStatus::Cancelled],
        'preparing' => [OrderStatus::Ready, OrderStatus::Cancelled],
        'ready' => [OrderStatus::Delivering, OrderStatus::Delivered, OrderStatus::Cancelled],
        'delivering' => [OrderStatus::Delivered, OrderStatus::Cancelled],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function handle(Order $order, OrderStatus $to, ?User $actor = null, ?string $note = null): Order
    {
        $from = $order->status;

        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Cannot transition order from [{$from->value}] to [{$to->value}]"
            );
        }

        return DB::transaction(function () use ($order, $from, $to, $actor, $note) {
            $order->status = $to;

            match ($to) {
                OrderStatus::Accepted => $order->accepted_at = now(),
                OrderStatus::Ready => $order->ready_at = now(),
                OrderStatus::Delivered => $order->delivered_at = now(),
                OrderStatus::Cancelled => $order->cancelled_at = now(),
                default => null,
            };

            if ($to === OrderStatus::Cancelled && filled($note)) {
                $order->cancellation_reason = $note;
            }

            $order->save();

            $order->statusEvents()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by_user_id' => $actor?->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            if ($to === OrderStatus::Delivered) {
                $this->grantBonuses->handle($order);
            } elseif ($to === OrderStatus::Cancelled) {
                $this->refundBonuses->handle($order);
            }

            return $order;
        });
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<OrderStatus>
     */
    public function allowedNext(OrderStatus $from): array
    {
        return self::TRANSITIONS[$from->value];
    }
}
