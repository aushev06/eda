<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLevel;
use App\Models\Order;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

class GrantOrderBonuses
{
    /**
     * Credit cashback to the customer's loyalty account based on their
     * current tier, computed from lifetime_spent_on_orders. Idempotent —
     * never credits the same order twice.
     *
     * Cashback is calculated on the amount actually paid (total), not on
     * subtotal+modifiers — bonuses for bonuses would compound.
     */
    public function handle(Order $order): void
    {
        if ((bool) Settings::get('loyalty.enabled', true) === false) {
            return;
        }

        if (! $order->customer_id) {
            return;
        }

        if ((float) $order->bonus_earned_amount > 0) {
            // Already granted.
            return;
        }

        $cashbackBase = (float) $order->total;
        if ($cashbackBase <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $cashbackBase) {
            $account = LoyaltyAccount::forCustomer($order->customer);
            $account->refresh();

            // Update lifetime first so this order's tier reflects the
            // customer's standing AFTER they've paid for it.
            $account->lifetime_spent_on_orders = (float) $account->lifetime_spent_on_orders + $cashbackBase;

            $level = LoyaltyLevel::forLifetimeSpend((float) $account->lifetime_spent_on_orders);
            $percent = $level ? (float) $level->cashback_percent : 0.0;

            $cashback = round($cashbackBase * $percent / 100, 2);
            if ($cashback <= 0) {
                $account->save();

                return;
            }

            $account->balance = (float) $account->balance + $cashback;
            $account->lifetime_earned = (float) $account->lifetime_earned + $cashback;
            $account->save();

            $account->transactions()->create([
                'order_id' => $order->id,
                'type' => LoyaltyTransactionType::Earn,
                'amount' => $cashback,
                'balance_after' => $account->balance,
                'note' => $level
                    ? "Кэшбек {$percent}% за заказ {$order->number} ({$level->name})"
                    : "Кэшбек за заказ {$order->number}",
                'created_at' => now(),
            ]);

            $order->bonus_earned_amount = $cashback;
            $order->save();
        });
    }
}
