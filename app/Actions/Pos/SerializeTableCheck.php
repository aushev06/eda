<?php

namespace App\Actions\Pos;

use App\Models\Order;
use App\Models\OrderItem;

class SerializeTableCheck
{
    /**
     * Open table check with every round, including voided lines.
     *
     * @return array<string, mixed>
     */
    public function handle(Order $order): array
    {
        $order->load(['items' => fn ($q) => $q->orderBy('id'), 'items.modifiers', 'items.product.category']);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'total' => (float) $order->total,
            'opened_at' => $order->opened_at?->toIso8601String(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'line_total' => (float) $item->line_total,
                'station' => $item->product?->resolvedStation()->value,
                'completed' => $item->completed_at !== null,
                'voided' => $item->voided_at !== null,
                'void_reason' => $item->void_reason,
                'modifiers' => $item->modifiers->map(fn ($m) => [
                    'name' => $m->modifier_name,
                    'price_delta' => (float) $m->price_delta,
                ]),
            ]),
        ];
    }
}
