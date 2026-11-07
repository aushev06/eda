<?php

namespace App\Services\Payments\Gateways;

use App\Enums\OnlinePaymentStatus;
use App\Models\OnlinePayment;
use App\Services\Payments\AbstractPaymentGateway;
use App\Services\Payments\CreatedPayment;
use App\Services\Payments\GatewayPaymentState;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentIntent;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * YooKassa API v3. Test and live shops use the same endpoint; the shop id
 * and secret key decide which one is charged.
 */
class YooKassaGateway extends AbstractPaymentGateway
{
    protected const BASE_URL = 'https://api.yookassa.ru/v3/';

    public static function label(): string
    {
        return 'ЮKassa';
    }

    public static function credentialFields(): array
    {
        return [
            'shop_id' => ['label' => 'shopId'],
            'secret_key' => ['label' => 'Секретный ключ', 'secret' => true],
        ];
    }

    public function createPayment(OnlinePayment $payment, PaymentIntent $intent): CreatedPayment
    {
        $response = $this->send(fn ($http) => $http
            ->withBasicAuth((string) $this->credential('shop_id'), (string) $this->credential('secret_key'))
            ->withHeaders(['Idempotence-Key' => $intent->reference])
            ->post(self::BASE_URL.'payments', [
                'amount' => [
                    'value' => number_format($intent->amountInMinorUnits / 100, 2, '.', ''),
                    'currency' => 'RUB',
                ],
                'capture' => true,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $intent->returnUrl,
                ],
                'description' => mb_substr($intent->description, 0, 128),
                'metadata' => ['reference' => $intent->reference],
            ]));

        $result = $this->decode($response);
        $url = $result['confirmation']['confirmation_url'] ?? null;

        if (blank($result['id'] ?? null) || blank($url)) {
            throw new PaymentGatewayException('ЮKassa не вернула ссылку на оплату.');
        }

        return new CreatedPayment((string) $result['id'], (string) $url, $result);
    }

    public function fetchState(OnlinePayment $payment): GatewayPaymentState
    {
        $response = $this->send(fn ($http) => $http
            ->withBasicAuth((string) $this->credential('shop_id'), (string) $this->credential('secret_key'))
            ->get(self::BASE_URL.'payments/'.rawurlencode((string) $payment->external_id)));

        $result = $this->decode($response);

        $status = match ($result['status'] ?? null) {
            'succeeded' => OnlinePaymentStatus::Succeeded,
            'canceled' => OnlinePaymentStatus::Cancelled,
            default => OnlinePaymentStatus::Pending,
        };

        return new GatewayPaymentState(
            $status,
            $result,
            $status === OnlinePaymentStatus::Cancelled ? ($result['cancellation_details']['reason'] ?? null) : null,
        );
    }

    public function webhookPaymentId(Request $request): ?string
    {
        $id = $request->input('object.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        $result = $response->json() ?? [];

        if ($response->failed() || ($result['type'] ?? null) === 'error') {
            throw new PaymentGatewayException($result['description'] ?? 'ЮKassa отклонила запрос.');
        }

        return $result;
    }
}
