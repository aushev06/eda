<?php

namespace App\Actions\Payments;

use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentIntent;
use App\Support\Settings;

class StartOnlinePayment
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * Open a new payment attempt for the order with the active gateway.
     * Each attempt gets its own reference, so a customer who abandoned the
     * payment form can simply start again.
     *
     * @throws PaymentGatewayException
     */
    public function handle(Order $order): OnlinePayment
    {
        if (! $this->canPay($order)) {
            throw new PaymentGatewayException('Этот заказ нельзя оплатить онлайн.');
        }

        $key = $this->gateways->activeKey()
            ?? throw new PaymentGatewayException('Онлайн-оплата сейчас недоступна.');

        $payment = $order->onlinePayments()->create([
            'gateway' => $key,
            'status' => OnlinePaymentStatus::Pending,
            'amount' => $order->total,
        ]);
        $payment->update(['reference' => $order->number.'-'.$payment->id]);

        $intent = new PaymentIntent(
            reference: $payment->reference,
            amountInMinorUnits: $payment->amountInMinorUnits(),
            description: 'Заказ '.$order->number.' — '.Settings::get('general.name'),
            returnUrl: route('orders.payment.return', ['number' => $order->number]),
            notificationUrl: route('payments.webhook', ['gateway' => $key]),
            customerPhone: $order->customer_phone,
        );

        try {
            $created = $this->gateways->make($key)->createPayment($payment, $intent);
        } catch (PaymentGatewayException $e) {
            $payment->update([
                'status' => OnlinePaymentStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 255),
            ]);

            throw $e;
        }

        $payment->update([
            'external_id' => $created->externalId,
            'confirmation_url' => $created->confirmationUrl,
            'payload' => $created->payload,
        ]);

        return $payment;
    }

    public function canPay(Order $order): bool
    {
        return $order->payment_method === PaymentMethod::CardOnline
            && $order->payment_status === PaymentStatus::Pending
            && $order->status !== OrderStatus::Cancelled
            && (float) $order->total > 0;
    }
}
