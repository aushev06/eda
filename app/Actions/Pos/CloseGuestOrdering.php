<?php

namespace App\Actions\Pos;

use App\Events\PosTicketsUpdated;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CloseGuestOrdering
{
    /**
     * Stop phone ordering at this table and revoke everyone who joined, so
     * reopening with a new PIN also removes an unwanted guest. Items already
     * sent stay on the check: the waiter removes those individually.
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->guest_pin = null;
            $order->save();

            $order->guests()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            PosTicketsUpdated::dispatch($order->id);

            return $order;
        });
    }
}
