<?php

namespace App\Enums;

enum PrinterConnectionType: string
{
    case Network = 'tcp';
    case Bluetooth = 'bluetooth';
    case Usb = 'usb';
    case Com = 'com';

    public function label(): string
    {
        return match ($this) {
            self::Network => 'Сеть (TCP/IP)',
            self::Bluetooth => 'Bluetooth',
            self::Usb => 'USB',
            self::Com => 'COM-порт',
        };
    }

    /**
     * Hint for the address field: what the POS app expects to find there.
     */
    public function addressHint(): string
    {
        return match ($this) {
            self::Network => 'IP-адрес принтера, например 192.168.1.50',
            self::Bluetooth => 'MAC-адрес, например 00:11:22:33:44:55',
            self::Usb => 'Идентификатор устройства (vendor:product), можно оставить пустым',
            self::Com => 'Имя порта, например ttyMT1',
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
