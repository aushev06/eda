<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Spend = 'spend';
    case Refund = 'refund';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'Начисление',
            self::Spend => 'Списание',
            self::Refund => 'Возврат',
            self::Adjust => 'Корректировка',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Earn => 'success',
            self::Spend => 'warning',
            self::Refund => 'info',
            self::Adjust => 'gray',
        };
    }
}
