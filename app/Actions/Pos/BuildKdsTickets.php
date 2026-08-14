<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\Station;
use App\Models\Order;

class BuildKdsTickets
{
    /**
     * Minutes before a scheduled order becomes visible on the station screens.
     */
    public const SCHEDULED_LEAD_MINUTES = 30;

    /**
     * Tickets visible on a station screen: accepted/preparing orders that
     * still have incomplete items routed to this station. Scheduled orders
     * stay hidden until SCHEDULED_LEAD_MINUTES before their time, and their
     * age counts from scheduled_for so the overdue alarm doesn't scream all day.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Station $station): array
    {
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Accepted, OrderStatus::Preparing])
            ->where(function ($query) {
                $query->whereNull('scheduled_for')
                    ->orWhere('scheduled_for', '<=', now()->addMinutes(self::SCHEDULED_LEAD_MINUTES));
            })
            ->with(['items.modifiers', 'items.product.category'])
            ->orderByRaw('COALESCE(scheduled_for, accepted_at, created_at)')
            ->get();

        return $orders
            ->map(function (Order $order) use ($station) {
                $stationItems = $order->items->filter(
                    fn ($item) => $item->completed_at === null
                        && $item->voided_at === null
                        && $item->product?->resolvedStation() === $station
                );

                if ($stationItems->isEmpty()) {
                    return null;
                }

                // Age from the earliest still-pending item of this station, so a
                // later round added to an open table check isn't born "overdue".
                $earliestItemAt = $stationItems->min(fn ($item) => $item->created_at);

                $ageBasis = $order->scheduled_for
                    ?? $earliestItemAt
                    ?? $order->accepted_at
                    ?? $order->created_at;

                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'source' => $order->source->value,
                    'source_label' => $order->source->label(),
                    'delivery_type' => $order->delivery_type->value,
                    'table_number' => $order->table_number_snapshot,
                    'comment' => $order->customer_comment,
                    'age_basis' => $ageBasis->toIso8601String(),
                    'scheduled_for' => $order->scheduled_for?->toIso8601String(),
                    'items' => $stationItems->values()->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'modifiers' => $item->modifiers->pluck('modifier_name')->all(),
                    ])->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
