<?php

namespace App\Enums;

enum Station: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';

    public function label(): string
    {
        return match ($this) {
            self::Kitchen => 'Кухня',
            self::Bar => 'Бар',
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
