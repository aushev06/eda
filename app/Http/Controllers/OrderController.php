<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\StartOnlinePayment;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrder $action, StartOnlinePayment $startPayment): HttpResponse
    {
        $data = $request->validated();
        $data['authenticated_customer_id'] = $request->user('customer')?->id;

        $order = $action->handle($data);

        // Stop tying further orders to this table once the order is placed —
        // the QR session has served its purpose for this visit.
        $request->session()->forget('table_id');

        if ($order->payment_method === PaymentMethod::CardOnline) {
            return PaymentController::redirectToGateway($order, $startPayment);
        }

        return redirect()->route('orders.thanks', ['number' => $order->number]);
    }

    public function thanks(string $number, StartOnlinePayment $startPayment): Response
    {
        $order = Order::query()
            ->with(['items.modifiers', 'deliveryZone'])
            ->where('number', $number)
            ->firstOrFail();

        return Inertia::render('catalog/order-thanks', [
            'order' => $order,
            'can_pay_online' => $startPayment->canPay($order),
        ]);
    }
}
