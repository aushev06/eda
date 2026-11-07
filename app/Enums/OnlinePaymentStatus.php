<?php

namespace App\Enums;

enum OnlinePaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает оплаты',
            self::Succeeded => 'Оплачен',
            self::Failed => 'Ошибка оплаты',
            self::Cancelled => 'Отменён',
            self::Refunded => 'Возврат',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Cancelled, self::Refunded => 'gray',
        };
    }

    /**
     * Whether the gateway will not move this payment any further.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
