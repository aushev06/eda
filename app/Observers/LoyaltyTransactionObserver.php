<?php

namespace App\Observers;

use App\Enums\LoyaltyTransactionType;
use App\Enums\NotificationType;
use App\Models\CustomerNotification;
use App\Models\LoyaltyTransaction;

class LoyaltyTransactionObserver
{
    public function created(LoyaltyTransaction $transaction): void
    {
        $account = $transaction->account()->with('customer')->first();
        if (! $account?->customer) {
            return;
        }

        [$notificationType, $title, $body] = $this->resolveContent($transaction);
        if ($notificationType === null) {
            return;
        }

        CustomerNotification::create([
            'customer_id' => $account->customer_id,
            'type' => $notificationType,
            'title' => $title,
            'body' => $body,
            'action_url' => route('account.bonuses'),
            'data' => [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => (float) $transaction->amount,
                'balance_after' => (float) $transaction->balance_after,
            ],
        ]);
    }

    /**
     * @return array{0: NotificationType|null, 1: string, 2: string|null}
     */
    private function resolveContent(LoyaltyTransaction $transaction): array
    {
        $amount = abs((float) $transaction->amount);
        $balance = (float) $transaction->balance_after;
        $balanceFormatted = number_format($balance, 0, ',', ' ');
        $amountFormatted = number_format($amount, 0, ',', ' ');

        return match ($transaction->type) {
            LoyaltyTransactionType::Earn => [
                NotificationType::LoyaltyEarn,
                "Начислено {$amountFormatted} бонусов",
                $transaction->note ?: "Баланс: {$balanceFormatted} ₽",
            ],
            LoyaltyTransactionType::Spend => [
                NotificationType::LoyaltySpend,
                "Списано {$amountFormatted} бонусов",
                $transaction->note ?: "Остаток: {$balanceFormatted} ₽",
            ],
            LoyaltyTransactionType::Refund => [
                NotificationType::LoyaltyEarn,
                "Возврат {$amountFormatted} бонусов",
                $transaction->note ?: "Баланс: {$balanceFormatted} ₽",
            ],
            LoyaltyTransactionType::Adjust => [null, '', null],
        };
    }
}
