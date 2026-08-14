<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class AdvanceOrderStatus
{
    /**
     * The forward fulfilment chain. Walking it one allowed hop at a time
     * (through TransitionOrderStatus, so events and side effects fire)
     * lets a single "done" action move an order from wherever it is up to
     * a target, while silently stopping if an admin already pushed it past.
     *
     * @var list<OrderStatus>
     */
    protected const CHAIN = [
        OrderStatus::Accepted,
        OrderStatus::Preparing,
        OrderStatus::Ready,
        OrderStatus::Delivered,
    ];

    public function __construct(
        protected TransitionOrderStatus $transition,
    ) {}

    /**
     * Walk the order forward one allowed hop at a time until it reaches the
     * target. Stops quietly when no allowed path continues (already at or
     * past the target, or in a terminal state) — never throws on a race.
     */
    public function handle(Order $order, OrderStatus $target, ?User $actor = null): Order
    {
        // Bounded by the chain length; guards against cycles.
        for ($hop = 0; $hop < count(self::CHAIN); $hop++) {
            if ($order->status === $target) {
                return $order;
            }

            $next = $this->nextHopToward($order->status, $target);
            if ($next === null) {
                return $order;
            }

            $this->transition->handle($order, $next, $actor);
        }

        return $order;
    }

    protected function nextHopToward(OrderStatus $from, OrderStatus $target): ?OrderStatus
    {
        $fromIdx = array_search($from, self::CHAIN, true);
        $targetIdx = array_search($target, self::CHAIN, true);

        if ($fromIdx === false || $targetIdx === false || $fromIdx >= $targetIdx) {
            return null;
        }

        $next = self::CHAIN[$fromIdx + 1];

        return $this->transition->canTransition($from, $next) ? $next : null;
    }
}
