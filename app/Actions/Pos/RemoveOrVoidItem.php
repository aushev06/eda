<?php

namespace App\Actions\Pos;

use App\Events\PosTicketsUpdated;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveOrVoidItem
{
    /**
     * Remove a line from an open check. If the kitchen hasn't made it yet
     * (completed_at is null) it is hard-deleted for free. If it was already
     * completed, it is voided with a mandatory reason: the line stays in the
     * data as a tracked loss but drops out of the guest's total and the KDS.
     */
    public function handle(OrderItem $item, User $actor, ?string $reason = null): void
    {
        $order = $item->order;

        if (! $order->isOpenTableCheck()) {
            throw ValidationException::withMessages([
                'order' => 'Счёт стола закрыт — позицию изменить нельзя.',
            ]);
        }

        if ($item->voided_at !== null) {
            return;
        }

        $alreadyMade = $item->completed_at !== null;

        if ($alreadyMade && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Укажите причину списания готовой позиции.',
            ]);
        }

        DB::transaction(function () use ($item, $order, $alreadyMade, $reason) {
            if ($alreadyMade) {
                $item->update([
                    'voided_at' => now(),
                    'void_reason' => $reason,
                ]);
            } else {
                $item->modifiers()->delete();
                $item->delete();
            }

            $order->load('items.modifiers');
            $order->recalculateTotals();
            $order->save();

            PosTicketsUpdated::dispatch($order->id);
        });
    }
}
