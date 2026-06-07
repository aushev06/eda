<?php

namespace App\Observers;

use App\Enums\NotificationType;
use App\Models\CustomerNotification;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLevel;

class LoyaltyAccountObserver
{
    /**
     * Detect when the customer crosses into a higher loyalty tier by
     * comparing the level computed from the original lifetime spend vs.
     * the new lifetime spend.
     */
    public function updated(LoyaltyAccount $account): void
    {
        if (! $account->wasChanged('lifetime_spent_on_orders')) {
            return;
        }

        $previousSpend = (float) ($account->getOriginal('lifetime_spent_on_orders') ?? 0);
        $currentSpend = (float) $account->lifetime_spent_on_orders;

        if ($currentSpend <= $previousSpend) {
            return;
        }

        $previousLevel = LoyaltyLevel::forLifetimeSpend($previousSpend);
        $currentLevel = LoyaltyLevel::forLifetimeSpend($currentSpend);

        if (! $currentLevel) {
            return;
        }
        if ($previousLevel && $previousLevel->id === $currentLevel->id) {
            return;
        }

        CustomerNotification::create([
            'customer_id' => $account->customer_id,
            'type' => NotificationType::LoyaltyLevelUp,
            'title' => "Новый уровень: {$currentLevel->name}",
            'body' => 'Поздравляем! Теперь кэшбек '.rtrim(rtrim(number_format((float) $currentLevel->cashback_percent, 2, ',', ''), '0'), ',').'% с каждого заказа.',
            'action_url' => route('account.bonuses'),
            'data' => [
                'level_id' => $currentLevel->id,
                'cashback_percent' => (float) $currentLevel->cashback_percent,
            ],
        ]);
    }
}
