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
 * T-Bank (Tinkoff) internet acquiring API v2. A test terminal (key ending
 * in "DEMO") works against the same endpoint.
 */
class TinkoffGateway extends AbstractPaymentGateway
{
    protected const BASE_URL = 'https://securepay.tinkoff.ru/v2/';

    public static function label(): string
    {
        return 'Т-Банк (Тинькофф)';
    }

    public static function credentialFields(): array
    {
        return [
            'terminal_key' => ['label' => 'TerminalKey'],
            'password' => ['label' => 'Пароль терминала', 'secret' => true],
        ];
    }

    public function createPayment(OnlinePayment $payment, PaymentIntent $intent): CreatedPayment
    {
        $result = $this->call('Init', [
            'Amount' => $intent->amountInMinorUnits,
            'OrderId' => $intent->reference,
            'Description' => mb_substr($intent->description, 0, 140),
            'SuccessURL' => $intent->returnUrl,
            'FailURL' => $intent->returnUrl,
            'NotificationURL' => $intent->notificationUrl,
        ]);

        if (blank($result['PaymentId'] ?? null) || blank($result['PaymentURL'] ?? null)) {
            throw new PaymentGatewayException('Т-Банк не вернул ссылку на оплату.');
        }

        return new CreatedPayment((string) $result['PaymentId'], (string) $result['PaymentURL'], $result);
    }

    public function fetchState(OnlinePayment $payment): GatewayPaymentState
    {
        $result = $this->call('GetState', ['PaymentId' => $payment->external_id]);

        $status = match ($result['Status'] ?? null) {
            'CONFIRMED' => OnlinePaymentStatus::Succeeded,
            'REJECTED', 'AUTH_FAIL' => OnlinePaymentStatus::Failed,
            'CANCELED', 'DEADLINE_EXPIRED', 'REVERSED' => OnlinePaymentStatus::Cancelled,
            'REFUNDED', 'PARTIAL_REFUNDED' => OnlinePaymentStatus::Refunded,
            default => OnlinePaymentStatus::Pending,
        };

        return new GatewayPaymentState(
            $status,
            $result,
            $status === OnlinePaymentStatus::Failed ? ($result['Message'] ?? null) : null,
        );
    }

    /**
     * Notifications are signed with the terminal password; unsigned or
     * mis-signed ones are ignored.
     */
    public function webhookPaymentId(Request $request): ?string
    {
        $params = $request->json()->all();

        if (! isset($params['Token']) || ! hash_equals($this->sign($params), (string) $params['Token'])) {
            return null;
        }

        return $this->requestPaymentId($request, 'PaymentId');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params): array
    {
        $params = ['TerminalKey' => (string) $this->credential('terminal_key')] + $params;
        $params['Token'] = $this->sign($params);

        $response = $this->send(fn ($http) => $http->post(self::BASE_URL.$method, $params));
        $result = $response->json() ?? [];

        if (($result['Success'] ?? false) !== true) {
            throw new PaymentGatewayException(
                trim(($result['Message'] ?? 'Т-Банк отклонил запрос.').' '.($result['Details'] ?? '')),
            );
        }

        return $result;
    }

    /**
     * Request token: root-level scalar values plus the password, sorted by
     * key, concatenated and hashed with SHA-256.
     *
     * @param  array<string, mixed>  $params
     */
    protected function sign(array $params): string
    {
        unset($params['Token']);
        $params['Password'] = (string) $this->credential('password');
        ksort($params);

        $concatenated = collect($params)
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->implode('');

        return hash('sha256', $concatenated);
    }
}
