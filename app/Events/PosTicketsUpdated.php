<?php

namespace App\Events;

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
     */
    public function __construct(public int $orderId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pos.tickets'),
        ];
    }
}
