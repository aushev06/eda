<?php

namespace App\Actions\Pos;

use App\Models\TableGuest;
use Illuminate\Http\Request;

class ResolveTableGuest
{
    /**
     * The guest this browser session may order as: not revoked, seated at the
     * session's current table, on a check that is still open for guest orders.
     */
    public function handle(Request $request): ?TableGuest
    {
        $guestId = $request->session()->get('table_guest_id');
        $tableId = $request->session()->get('table_id');

        if (! $guestId || ! $tableId) {
            return null;
        }

        $guest = TableGuest::query()->with('order')->whereNull('revoked_at')->find($guestId);

        if (! $guest || $guest->order->table_id !== $tableId) {
            return null;
        }

        return $guest->order->isOpenTableCheck() && $guest->order->guest_pin !== null ? $guest : null;
    }
}
