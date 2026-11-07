<?php

namespace App\Actions\Pos;

use App\Actions\Orders\PriceOrderItems;
use App\Events\PosTicketsUpdated;
use App\Models\Order;
use App\Models\TableGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddItemsToCheck
{
    public function __construct(
        protected PriceOrderItems $priceOrderItems,
    ) {}

    /**
     * Append a round of items to an open table check. New items fire to their
     * stations immediately (PosTicketsUpdated), so the kitchen/bar see them at
     * once. Pricing goes through the shared PriceOrderItems so it matches the
     * cashier and site flows exactly.
     *
     * The order row is locked because guests order concurrently from their own
     * phones: without it two rounds could overwrite each other's total, or a
     * round could land on a check the cashier has just settled. A guest's access
     * is rechecked under the same lock, and $guest records who ordered the items.
     *
     * @param  array<int, array{product_id: int, quantity: int, modifier_ids?: array<int, int>}>  $items
     */
    public function handle(Order $order, array $items, ?TableGuest $guest = null): Order
    {
        [, , $itemRows] = $this->priceOrderItems->handle($items);

        return DB::transaction(function () use ($order, $itemRows, $guest) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenTableCheck()) {
                throw ValidationException::withMessages([
                    'order' => 'Счёт стола закрыт — позиции добавить нельзя.',
                ]);
            }

            if ($guest && ($locked->guest_pin === null || $guest->fresh()?->revoked_at !== null)) {
                throw ValidationException::withMessages([
                    'items' => 'Заказы с телефона за этим столом закрыты. Обратитесь к официанту.',
                ]);
            }

            foreach ($itemRows as $row) {
                $item = $locked->items()->create([
                    'table_guest_id' => $guest?->id,
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

            $locked->load('items.modifiers');
            $locked->recalculateTotals();
            $locked->save();

            PosTicketsUpdated::dispatch($locked->id);

            return $locked->refresh();
        });
    }
}
