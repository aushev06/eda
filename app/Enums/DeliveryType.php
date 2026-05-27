<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
    case DineIn = 'dine_in';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Доставка',
            self::Pickup => 'Самовывоз',
            self::DineIn => 'В зале',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivery => 'primary',
            self::Pickup => 'gray',
            self::DineIn => 'success',
        };
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
