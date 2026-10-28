<?php

namespace App\Actions\Pos;

use App\Enums\DeliveryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Collection;

class BuildFloorPlan
{
    /**
     * Floor view: every active table with its open-check total and age.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(): Collection
    {
        // One open check per table; key by table_id for the floor lookup.
        $openChecks = Order::query()
            ->whereNotNull('table_id')
            ->where('source', OrderSource::Pos)
            ->where('delivery_type', DeliveryType::DineIn)
            ->whereIn('status', [OrderStatus::Accepted, OrderStatus::Preparing])
            ->where('payment_status', PaymentStatus::Pending)
            ->get()
            ->keyBy('table_id');

        return Table::query()
            ->active()
            ->orderBy('number')
            ->get(['id', 'number', 'label'])
            ->map(function (Table $table) use ($openChecks) {
                $check = $openChecks->get($table->id);

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'label' => $table->label,
                    'check' => $check ? [
                        'id' => $check->id,
                        'total' => (float) $check->total,
                        'opened_at' => $check->opened_at?->toIso8601String(),
                    ] : null,
                ];
            });
    }
}
