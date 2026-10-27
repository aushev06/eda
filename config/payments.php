<?php

use App\Services\Payments\Gateways\AlfabankGateway;
use App\Services\Payments\Gateways\SberbankGateway;
use App\Services\Payments\Gateways\TinkoffGateway;
use App\Services\Payments\Gateways\YooKassaGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Online Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Every gateway the admin can choose from. The key is stored with each
    | payment and used in the webhook URL (/payments/{key}/webhook), so do
    | not rename a key once payments have gone through it. To connect a new
    | provider, add a class implementing App\Services\Payments\PaymentGateway.
    |
    */

    'gateways' => [
        'yookassa' => YooKassaGateway::class,
        'tinkoff' => TinkoffGateway::class,
        'alfabank' => AlfabankGateway::class,
        'sberbank' => SberbankGateway::class,
    ],

];
