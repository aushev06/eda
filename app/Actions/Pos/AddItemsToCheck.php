<?php

namespace App\Actions\Pos;

use App\Actions\Orders\PriceOrderItems;
use App\Events\PosTicketsUpdated;
use App\Models\Order;
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
     * @param  array<int, array{product_id: int, quantity: int, modifier_ids?: array<int, int>}>  $items
     */
    public function handle(Order $order, array $items): Order
    {
        if (! $order->isOpenTableCheck()) {
            throw ValidationException::withMessages([
                'order' => 'Счёт стола закрыт — позиции добавить нельзя.',
            ]);
        }

        [, , $itemRows] = $this->priceOrderItems->handle($items);

        return DB::transaction(function () use ($order, $itemRows) {
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

            $order->load('items.modifiers');
            $order->recalculateTotals();
            $order->save();

            PosTicketsUpdated::dispatch($order->id);

            return $order->refresh();
        });
    }
}
