<?php

namespace App\Services\Payments;

use App\Support\Settings;
use InvalidArgumentException;

/**
 * Resolves gateways from config/payments.php with credentials from the
 * admin settings ("payments.*").
 */
class PaymentGatewayManager
{
    /**
     * @return array<string, class-string<PaymentGateway>>
     */
    public function gateways(): array
    {
        return config('payments.gateways', []);
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return array_map(fn (string $class) => $class::label(), $this->gateways());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->gateways());
    }

    public function make(string $key): PaymentGateway
    {
        $class = $this->gateways()[$key] ?? throw new InvalidArgumentException("Unknown payment gateway [{$key}].");

        $config = [];
        foreach (array_keys($class::credentialFields()) as $field) {
            $config[$field] = Settings::get("payments.{$key}.{$field}");
        }

        return new $class($config, (bool) Settings::get('payments.test_mode', false));
    }

    /**
     * Key of the gateway new payments go through, or null when online
     * payment is switched off or not fully configured.
     */
    public function activeKey(): ?string
    {
        $key = Settings::get('payments.gateway');

        if (! Settings::get('payments.enabled', false) || ! is_string($key) || ! $this->has($key)) {
            return null;
        }

        return $this->make($key)->isConfigured() ? $key : null;
    }

    public function isOnlinePaymentAvailable(): bool
    {
        return $this->activeKey() !== null;
    }
}
