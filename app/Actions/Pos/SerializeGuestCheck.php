<?php

namespace App\Actions\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableGuest;

class SerializeGuestCheck
{
    /**
     * What guests at the table may see. Deliberately excludes the PIN, the
     * check number and voided lines, which stay staff-only.
     *
     * @return array{
     *     total: float,
     *     channel: string,
     *     guests: array<int, array{id: int, name: string}>,
     *     items: array<int, array{id: int, name: string, quantity: int, line_total: float, ready: bool, ordered_by: ?string, is_mine: bool, modifiers: array<int, string>}>
     * }
     */
    public function handle(Order $order, TableGuest $viewer): array
    {
        $order->load([
            'items' => fn ($query) => $query->whereNull('voided_at')->orderBy('id'),
            'items.modifiers',
            'items.guest',
            'guests' => fn ($query) => $query->whereNull('revoked_at')->orderBy('id'),
        ]);

        return [
            'total' => (float) $order->total,
            'channel' => 'table-check.'.$order->guest_channel_token,
            'guests' => $order->guests
                ->map(fn (TableGuest $guest) => ['id' => $guest->id, 'name' => $guest->name])
                ->all(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'line_total' => (float) $item->line_total,
                'ready' => $item->completed_at !== null,
                'ordered_by' => $item->guest?->name,
                'is_mine' => $item->table_guest_id === $viewer->id,
                'modifiers' => $item->modifiers->pluck('modifier_name')->all(),
            ])->all(),
        ];
    }
}
