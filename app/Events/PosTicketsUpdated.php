<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosTicketsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Fired whenever the set of visible KDS tickets may have changed:
     * a POS or site order accepted, a station bumped, an order cancelled.
     * The payload intentionally carries no ticket data — station screens
     * re-query their list, so a lost message only delays, never corrupts.
     * Guests at a shared table hear the same signal on their check's channel.
     */
    public function __construct(public int $orderId) {}

    /**
     * The guest channel is public because guests have no user to authorize, so
     * it relies on an unguessable name and on this event carrying no check data.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('pos.tickets')];

        $guestChannelToken = Order::query()->whereKey($this->orderId)->value('guest_channel_token');

        if ($guestChannelToken !== null) {
            $channels[] = new Channel('table-check.'.$guestChannelToken);
        }

        return $channels;
    }
}
