<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractPaymentGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config  Credentials keyed as in credentialFields().
     */
    public function __construct(
        protected array $config,
        protected bool $testMode = false,
    ) {}

    public function isConfigured(): bool
    {
        foreach (static::credentialFields() as $key => $field) {
            if (($field['required'] ?? true) && blank($this->config[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function webhookResponse(): Response
    {
        return response('OK');
    }

    protected function credential(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        return blank($value) ? $default : (string) $value;
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }

    /**
     * Run a provider request and turn transport failures into a gateway error.
     *
     * @param  callable(PendingRequest): HttpResponse  $send
     */
    protected function send(callable $send): HttpResponse
    {
        try {
            $response = $send($this->http());
        } catch (ConnectionException $e) {
            report($e);

            throw new PaymentGatewayException('Платёжный сервис недоступен. Попробуйте позже.', previous: $e);
        }

        if ($response->serverError()) {
            throw new PaymentGatewayException('Платёжный сервис вернул ошибку. Попробуйте позже.');
        }

        return $response;
    }

    protected function requestPaymentId(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
