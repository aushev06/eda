<?php

namespace App\Http\Controllers;

use App\Actions\Payments\StartOnlinePayment;
use App\Actions\Payments\SyncOnlinePayment;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaymentController extends Controller
{
    /**
     * Send the customer to the gateway's payment form, starting a fresh
     * attempt (used by the "Оплатить" button on the thanks page).
     */
    public function pay(string $number, StartOnlinePayment $action): Response
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        return $this->redirectToGateway($order, $action);
    }

    /**
     * Where the gateway sends the customer back, whether the payment went
     * through or not — the real outcome is fetched from the gateway.
     */
    public function return(string $number, SyncOnlinePayment $sync): RedirectResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        $sync->syncPending($order);

        return redirect()->route('orders.thanks', ['number' => $order->number]);
    }

    /**
     * Server-to-server notification from a gateway.
     */
    public function webhook(Request $request, string $gateway, PaymentGatewayManager $gateways, SyncOnlinePayment $sync): Response
    {
        abort_unless($gateways->has($gateway), 404);

        $driver = $gateways->make($gateway);
        $externalId = $driver->webhookPaymentId($request);

        $payment = $externalId === null ? null : OnlinePayment::query()
            ->where('gateway', $gateway)
            ->where('external_id', $externalId)
            ->first();

        if ($payment) {
            try {
                $sync->handle($payment);
            } catch (Throwable $e) {
                report($e);

                // Ask the gateway to retry the notification later.
                return response('Retry', 503);
            }
        }

        return $driver->webhookResponse();
    }

    public static function redirectToGateway(Order $order, StartOnlinePayment $action): Response
    {
        try {
            $payment = $action->handle($order);
        } catch (PaymentGatewayException $e) {
            return redirect()
                ->route('orders.thanks', ['number' => $order->number])
                ->with('payment_error', $e->getMessage());
        }

        return Inertia::location($payment->confirmation_url);
    }
}
