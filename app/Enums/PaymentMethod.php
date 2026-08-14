<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case CardOnline = 'card_online';
    case CardCourier = 'card_courier';
    case Split = 'split';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Наличные',
            self::CardOnline => 'Карта онлайн',
            self::CardCourier => 'Карта курьеру',
            self::Split => 'Смешанная',
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
