<?php

namespace App\Services\Payments\Gateways;

use App\Enums\OnlinePaymentStatus;
use App\Models\OnlinePayment;
use App\Services\Payments\AbstractPaymentGateway;
use App\Services\Payments\CreatedPayment;
use App\Services\Payments\GatewayPaymentState;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentIntent;
use Illuminate\Http\Request;

/**
 * RBS acquiring protocol (register.do / getOrderStatusExtended.do), shared
 * by Alfa-Bank, Sberbank and other banks running the same platform.
 */
abstract class RbsGateway extends AbstractPaymentGateway
{
    /** ISO 4217 numeric code for the rouble. */
    protected const CURRENCY_RUB = '643';

    abstract protected function productionUrl(): string;

    abstract protected function testUrl(): string;

    public static function credentialFields(): array
    {
        return [
            'username' => ['label' => 'Логин API (…-api)', 'required' => false],
            'password' => ['label' => 'Пароль API', 'secret' => true, 'required' => false],
            'token' => [
                'label' => 'Токен',
                'secret' => true,
                'required' => false,
                'help' => 'Вместо логина и пароля, если банк выдал токен.',
            ],
            'api_url' => [
                'label' => 'Адрес API',
                'required' => false,
                'placeholder' => 'по умолчанию',
                'help' => 'Оставьте пустым, чтобы использовать адрес банка для выбранного режима.',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        return filled($this->credential('token'))
            || (filled($this->credential('username')) && filled($this->credential('password')));
    }

    public function createPayment(OnlinePayment $payment, PaymentIntent $intent): CreatedPayment
    {
        $result = $this->call('register.do', [
            'orderNumber' => $intent->reference,
            'amount' => $intent->amountInMinorUnits,
            'currency' => self::CURRENCY_RUB,
            'returnUrl' => $intent->returnUrl,
            'failUrl' => $intent->returnUrl,
            'dynamicCallbackUrl' => $intent->notificationUrl,
            'description' => mb_substr($intent->description, 0, 512),
        ]);

        if (blank($result['orderId'] ?? null) || blank($result['formUrl'] ?? null)) {
            throw new PaymentGatewayException('Банк не вернул ссылку на оплату.');
        }

        return new CreatedPayment((string) $result['orderId'], (string) $result['formUrl'], $result);
    }

    public function fetchState(OnlinePayment $payment): GatewayPaymentState
    {
        $result = $this->call('getOrderStatusExtended.do', ['orderId' => $payment->external_id]);

        $status = match ((int) ($result['orderStatus'] ?? -1)) {
            2 => OnlinePaymentStatus::Succeeded,
            3 => OnlinePaymentStatus::Cancelled,
            4 => OnlinePaymentStatus::Refunded,
            6 => OnlinePaymentStatus::Failed,
            default => OnlinePaymentStatus::Pending,
        };

        return new GatewayPaymentState(
            $status,
            $result,
            $status === OnlinePaymentStatus::Failed ? ($result['actionCodeDescription'] ?? null) : null,
        );
    }

    public function webhookPaymentId(Request $request): ?string
    {
        return $this->requestPaymentId($request, 'mdOrder');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params): array
    {
        $response = $this->send(fn ($http) => $http
            ->asForm()
            ->post($this->baseUrl().$method, $this->authParams() + $params));

        $result = $response->json() ?? [];

        if ((int) ($result['errorCode'] ?? 0) !== 0) {
            throw new PaymentGatewayException($result['errorMessage'] ?? 'Банк отклонил запрос.');
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    protected function authParams(): array
    {
        if (filled($this->credential('token'))) {
            return ['token' => $this->credential('token')];
        }

        return [
            'userName' => (string) $this->credential('username'),
            'password' => (string) $this->credential('password'),
        ];
    }

    protected function baseUrl(): string
    {
        $url = $this->credential('api_url') ?? ($this->testMode ? $this->testUrl() : $this->productionUrl());

        return rtrim($url, '/').'/';
    }
}
