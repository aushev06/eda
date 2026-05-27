<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrder $action): RedirectResponse
    {
        $data = $request->validated();
        $data['authenticated_customer_id'] = $request->user('customer')?->id;

        $order = $action->handle($data);

        // Stop tying further orders to this table once the order is placed —
        // the QR session has served its purpose for this visit.
        $request->session()->forget('table_id');

        return redirect()->route('orders.thanks', ['number' => $order->number]);
    }

    public function thanks(string $number): Response
    {
        $order = Order::query()
            ->with(['items.modifiers', 'deliveryZone'])
            ->where('number', $number)
            ->firstOrFail();

        return Inertia::render('catalog/order-thanks', [
            'order' => $order,
        ]);
    }
}
