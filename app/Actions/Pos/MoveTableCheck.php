<?php

namespace App\Actions\Pos;

use App\Events\PosTicketsUpdated;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveTableCheck
{
    public function __construct(
        protected CloseGuestOrdering $closeGuestOrdering,
    ) {}

    /**
     * Seat an open check at another, free table. Both table rows are locked in
     * id order (the same lock OpenTableCheck takes), so a move can't race a
     * cashier opening the target table or another move in the opposite way.
     *
     * Phone ordering is closed: guest sessions are bound to the old table's QR
     * code, and the waiter hands out a fresh PIN at the new one if needed.
     */
    public function handle(Order $order, Table $target): Order
    {
        return DB::transaction(function () use ($order, $target) {
            Table::query()
                ->whereKey(array_filter([$order->table_id, $target->id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $order->refresh();

            if (! $order->isOpenTableCheck()) {
                throw ValidationException::withMessages([
                    'order' => 'Счёт уже закрыт — пересадить его нельзя.',
                ]);
            }

            if ($order->table_id === $target->id) {
                throw ValidationException::withMessages([
                    'table_id' => 'Счёт уже за этим столом.',
                ]);
            }

            if (! $target->is_active) {
                throw ValidationException::withMessages([
                    'table_id' => 'Стол не используется.',
                ]);
            }

            if (Order::query()->openTableCheck($target->id)->exists()) {
                throw ValidationException::withMessages([
                    'table_id' => "Стол {$target->number} занят.",
                ]);
            }

            $order->table_id = $target->id;
            $order->table_number_snapshot = (string) $target->number;
            $order->save();

            if ($order->guest_pin !== null) {
                $this->closeGuestOrdering->handle($order);
            } else {
                // KDS tickets show the table number.
                PosTicketsUpdated::dispatch($order->id);
            }

            return $order;
        });
    }
}
