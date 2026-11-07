<?php

namespace App\Enums;

/**
 * Kind of printer under «Оборудование». Mirrors FusionPOS `printers.type`
 * (local = чековый); kitchen/bar ticket printers can be added here later.
 */
enum PrinterType: string
{
    case Receipt = 'receipt';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Чековый принтер',
        };
    }
}
