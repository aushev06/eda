<?php

namespace App\Actions\Pos;

use App\Events\PosTicketsUpdated;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenGuestOrdering
{
    /**
     * Let guests at this table order from their phones. The table QR code is
     * static and can be photographed, so joining also needs this PIN, which the
     * waiter gives to the people actually sitting there. Calling it again keeps
     * the current PIN, so a double-tap can't invalidate one already handed out.
     */
    public function handle(Order $order): Order
    {
        if (! $order->isOpenTableCheck()) {
            throw ValidationException::withMessages([
                'order' => 'Счёт стола закрыт — заказы гостей открыть нельзя.',
            ]);
        }

        if ($order->guest_pin !== null) {
            return $order;
        }

        $order->guest_pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $order->guest_channel_token = Str::random(40);
        $order->save();

        PosTicketsUpdated::dispatch($order->id);

        return $order;
    }
}
