<?php

namespace App\Actions\Pos;

use App\Actions\Orders\AdvanceOrderStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Station;
use App\Events\PosTicketsUpdated;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BumpStationTicket
{
    public function __construct(
        protected AdvanceOrderStatus $advance,
    ) {}

    /**
     * Mark the station's part of an order as done. When every item across
     * all stations is completed, a quick (pickup) POS order is auto-advanced
     * to Ready and then Delivered. An OPEN TABLE CHECK is deliberately NOT
     * advanced — bumping only records completion; the check stays open
     * (Accepted/Preparing, unpaid) until the cashier closes it via
     * CloseTableCheck, otherwise the first full bump would settle the table
     * before the guest has paid.
     *
     * The advance walks from the order's CURRENT status with a silent skip,
     * so a race with an admin who already moved the order never fails.
     */
    public function handle(Order $order, Station $station, User $actor): Order
    {
        return DB::transaction(function () use ($order, $station, $actor) {
            $order->loadMissing('items.product.category');

            $order->items
                ->filter(fn ($item) => $item->completed_at === null
                    && $item->voided_at === null
                    && $item->product?->resolvedStation() === $station)
                ->each(fn ($item) => $item->update(['completed_at' => now()]));

            if (! $order->isOpenTableCheck()) {
                $activeItems = $order->items->filter(fn ($item) => $item->voided_at === null);
                $allDone = $activeItems->isNotEmpty()
                    && $activeItems->every(fn ($item) => $item->completed_at !== null);

                if ($allDone) {
                    $this->advance->handle($order, OrderStatus::Ready, $actor);

                    if ($order->source === OrderSource::Pos) {
                        $this->advance->handle($order, OrderStatus::Delivered, $actor);
                    }
                }
            }

            PosTicketsUpdated::dispatch($order->id);

            return $order->refresh();
        });
    }
}
