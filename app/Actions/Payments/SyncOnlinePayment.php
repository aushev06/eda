<?php

namespace App\Actions\Payments;

use App\Enums\OnlinePaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PosTicketsUpdated;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncOnlinePayment
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * Pull the payment's state from its gateway and apply it. Safe to call
     * any number of times — from the return page and from every webhook
     * retry — because the order is only marked paid once.
     *
     * @throws PaymentGatewayException
     */
    public function handle(OnlinePayment $payment): OnlinePayment
    {
        if ($payment->external_id === null || $payment->status === OnlinePaymentStatus::Refunded) {
            return $payment;
        }

        $state = $this->gateways->make($payment->gateway)->fetchState($payment);

        DB::transaction(function () use ($payment, $state) {
            $locked = OnlinePayment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status === $state->status) {
                return;
            }

            $previous = $locked->status;

            $locked->fill([
                'status' => $state->status,
                'payload' => $state->payload,
                'error_message' => $state->errorMessage ? mb_substr($state->errorMessage, 0, 255) : null,
            ]);

            if ($state->status === OnlinePaymentStatus::Succeeded) {
                $locked->paid_at = now();
            }

            $locked->save();

            if ($state->status === OnlinePaymentStatus::Succeeded) {
                $this->markOrderPaid($locked);
            } elseif ($state->status === OnlinePaymentStatus::Refunded && $previous === OnlinePaymentStatus::Succeeded) {
                Order::query()->whereKey($locked->order_id)->update(['payment_status' => PaymentStatus::Refunded]);
            }
        });

        return $payment->refresh();
    }

    /**
     * Refresh every unfinished attempt of the order, e.g. when the customer
     * comes back from the payment page before the webhook has arrived.
     */
    public function syncPending(Order $order): void
    {
        $order->onlinePayments()
            ->where('status', OnlinePaymentStatus::Pending)
            ->whereNotNull('external_id')
            ->get()
            ->each(function (OnlinePayment $payment) {
                try {
                    $this->handle($payment);
                } catch (Throwable $e) {
                    report($e);
                }
            });
    }

    protected function markOrderPaid(OnlinePayment $payment): void
    {
        $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();

        // A second successful attempt for an already paid order is recorded
        // on the payment only — the operator refunds it from the gateway.
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        $order->payments()->create([
            'method' => PaymentMethod::CardOnline,
            'amount' => $payment->amount,
            'received_amount' => $payment->amount,
        ]);

        $order->payment_method = PaymentMethod::CardOnline;
        $order->payment_status = PaymentStatus::Paid;
        $order->paid_at = now();
        $order->save();

        PosTicketsUpdated::dispatch($order->id);
    }
}
