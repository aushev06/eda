<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Accepted => 'Принят',
            self::Preparing => 'Готовится',
            self::Ready => 'Готов',
            self::Delivering => 'В пути',
            self::Delivered => 'Доставлен',
            self::Cancelled => 'Отменён',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Accepted => 'info',
            self::Preparing => 'info',
            self::Ready => 'success',
            self::Delivering => 'info',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
