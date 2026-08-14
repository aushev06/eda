<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CloseTableCheck
{
    public function __construct(
        protected SettleOrder $settle,
    ) {}

    /**
     * Settle and close an open table check via the shared SettleOrder pipeline:
     * record the payment breakdown, mark it paid, and drive the status to
     * Delivered so the table frees up.
     *
     * @param  array<int, array{method: string, amount: int|float, received_amount?: int|float|null}>  $tenders
     */
    public function handle(Order $order, array $tenders, User $cashier): Order
    {
        if (! $order->isOpenTableCheck()) {
            throw ValidationException::withMessages([
                'order' => 'Этот счёт уже закрыт.',
            ]);
        }

        return $this->settle->handle($order, $tenders, $cashier, OrderStatus::Delivered);
    }
}
