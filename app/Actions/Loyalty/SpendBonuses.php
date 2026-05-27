<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Order;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpendBonuses
{
    /**
     * Compute the maximum bonus amount that may be spent on a given
     * order subtotal, given the configured percent cap.
     */
    public function allowedCap(float $orderSubtotal): float
    {
        $percent = (float) Settings::get('loyalty.max_spend_percent_per_order', 30);

        return round($orderSubtotal * $percent / 100, 2);
    }

    /**
     * Validate that the requested spend is acceptable. Returns the final
     * spend amount (clamped to balance/cap), or throws on misuse.
     *
     * @throws ValidationException
     */
    public function quote(Customer $customer, float $requested, float $orderSubtotal): float
    {
        if ((bool) Settings::get('loyalty.enabled', true) === false) {
            throw ValidationException::withMessages([
                'bonus_to_use' => 'Бонусная программа сейчас отключена.',
            ]);
        }

        $requested = max(0, $requested);
        if ($requested === 0.0) {
            return 0.0;
        }

        $account = LoyaltyAccount::forCustomer($customer);
        $balance = (float) $account->balance;
        $cap = $this->allowedCap($orderSubtotal);

        if ($requested > $balance) {
            throw ValidationException::withMessages([
                'bonus_to_use' => 'У вас недостаточно бонусов.',
            ]);
        }

        if ($requested > $cap) {
            throw ValidationException::withMessages([
                'bonus_to_use' => "Максимум {$cap} ₽ бонусов на этот заказ.",
            ]);
        }

        return round($requested, 2);
    }

    /**
     * Actually debit the customer's loyalty account for the given order.
     * Caller is responsible for using this inside a DB transaction.
     */
    public function commit(Customer $customer, Order $order, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $account = LoyaltyAccount::forCustomer($customer);

        DB::transaction(function () use ($account, $order, $amount) {
            $account->refresh();
            $account->balance = max(0, (float) $account->balance - $amount);
            $account->save();

            $account->transactions()->create([
                'order_id' => $order->id,
                'type' => LoyaltyTransactionType::Spend,
                'amount' => -$amount,
                'balance_after' => $account->balance,
                'note' => "Заказ {$order->number}",
                'created_at' => now(),
            ]);
        });
    }
}
