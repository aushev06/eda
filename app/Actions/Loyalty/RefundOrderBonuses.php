<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Models\LoyaltyAccount;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class RefundOrderBonuses
{
    /**
     * When an order is cancelled we undo bonus activity tied to it:
     *  - re-credit any bonuses that were spent on the order
     *  - revoke any cashback that had already been granted (e.g. if
     *    the order was cancelled after delivery in edge cases)
     *  - subtract the order amount from lifetime_spent_on_orders so the
     *    customer's tier doesn't get "inflated" by a refunded purchase
     *
     * Idempotent: skips if there's nothing to undo.
     */
    public function handle(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        $spent = (float) $order->bonus_used_amount;
        $earned = (float) $order->bonus_earned_amount;
        $countedAsLifetime = $earned > 0 ? (float) $order->total : 0.0;

        if ($spent <= 0 && $earned <= 0 && $countedAsLifetime <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $spent, $earned, $countedAsLifetime) {
            $account = LoyaltyAccount::forCustomer($order->customer);
            $account->refresh();

            if ($spent > 0) {
                $account->balance = (float) $account->balance + $spent;
                $account->transactions()->create([
                    'order_id' => $order->id,
                    'type' => LoyaltyTransactionType::Refund,
                    'amount' => $spent,
                    'balance_after' => $account->balance,
                    'note' => "Возврат бонусов по отменённому заказу {$order->number}",
                    'created_at' => now(),
                ]);
                $order->bonus_used_amount = 0;
            }

            if ($earned > 0) {
                $account->balance = max(0, (float) $account->balance - $earned);
                $account->lifetime_earned = max(0, (float) $account->lifetime_earned - $earned);
                $account->transactions()->create([
                    'order_id' => $order->id,
                    'type' => LoyaltyTransactionType::Adjust,
                    'amount' => -$earned,
                    'balance_after' => $account->balance,
                    'note' => "Списание кэшбека по отменённому заказу {$order->number}",
                    'created_at' => now(),
                ]);
                $order->bonus_earned_amount = 0;
            }

            if ($countedAsLifetime > 0) {
                $account->lifetime_spent_on_orders = max(0, (float) $account->lifetime_spent_on_orders - $countedAsLifetime);
            }

            $account->save();
            $order->save();
        });
    }
}
