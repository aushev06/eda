<?php

namespace App\Services\Payments\Gateways;

class AlfabankGateway extends RbsGateway
{
    public static function label(): string
    {
        return 'Альфа-Банк';
    }

    protected function productionUrl(): string
    {
        return 'https://payment.alfabank.ru/payment/rest/';
    }

    protected function testUrl(): string
    {
        return 'https://alfa.rbsuat.com/payment/rest/';
    }
}
