<?php

namespace App\Services\Payments;

use App\Models\OnlinePayment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract every online payment provider implements. To connect a new
 * provider, implement this interface (or extend AbstractPaymentGateway)
 * and register the class in config/payments.php — the checkout, the
 * webhook route and the admin settings page pick it up automatically.
 */
interface PaymentGateway
{
    /**
     * Human-readable provider name shown in the admin panel.
     */
    public static function label(): string;

    /**
     * Credentials the provider needs, rendered as fields in the admin panel
     * and stored in settings under "payments.{gateway}.{key}".
     *
     * @return array<string, array{label: string, secret?: bool, required?: bool, placeholder?: string, help?: string}>
     */
    public static function credentialFields(): array;

    /**
     * Whether every required credential is filled in.
     */
    public function isConfigured(): bool;

    /**
     * Register the payment with the provider and return where to send the
     * customer to pay.
     *
     * @throws PaymentGatewayException
     */
    public function createPayment(OnlinePayment $payment, PaymentIntent $intent): CreatedPayment;

    /**
     * Ask the provider for the payment's current state. Status changes are
     * always taken from here, never from redirect or webhook parameters,
     * so a forged callback cannot mark an order paid.
     *
     * @throws PaymentGatewayException
     */
    public function fetchState(OnlinePayment $payment): GatewayPaymentState;

    /**
     * Pull the provider's payment id out of an incoming notification, or
     * null when the request is not a notification about a payment.
     */
    public function webhookPaymentId(Request $request): ?string;

    /**
     * Body the provider expects in reply to a notification.
     */
    public function webhookResponse(): Response;
}
