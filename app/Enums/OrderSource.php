<?php

namespace App\Enums;

enum OrderSource: string
{
    case Site = 'site';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Сайт',
            self::Pos => 'Касса',
        };
    }
}
