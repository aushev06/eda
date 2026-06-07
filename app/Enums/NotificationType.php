<?php

namespace App\Enums;

enum NotificationType: string
{
    case Promo = 'promo';
    case LoyaltyEarn = 'loyalty_earn';
    case LoyaltySpend = 'loyalty_spend';
    case LoyaltyLevelUp = 'loyalty_level_up';
    case OrderStatus = 'order_status';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Promo => 'Промокод',
            self::LoyaltyEarn => 'Начисление бонусов',
            self::LoyaltySpend => 'Списание бонусов',
            self::LoyaltyLevelUp => 'Повышение уровня',
            self::OrderStatus => 'Статус заказа',
            self::System => 'Системное',
        };
    }
}
