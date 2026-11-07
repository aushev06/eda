<?php

namespace App\Services\Payments\Gateways;

class SberbankGateway extends RbsGateway
{
    public static function label(): string
    {
        return 'Сбербанк';
    }

    protected function productionUrl(): string
    {
        return 'https://securepayments.sberbank.ru/payment/rest/';
    }

    protected function testUrl(): string
    {
        return 'https://3dsec.sberbank.ru/payment/rest/';
    }
}
