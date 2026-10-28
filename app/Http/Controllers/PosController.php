<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PriceOrderItems;
use App\Actions\Pos\AddItemsToCheck;
use App\Actions\Pos\BuildFloorPlan;
use App\Actions\Pos\BuildKdsTickets;
use App\Actions\Pos\BuildPosCatalog;
use App\Actions\Pos\BumpStationTicket;
use App\Actions\Pos\CloseTableCheck;
use App\Actions\Pos\OpenTableCheck;
use App\Actions\Pos\PlacePosOrder;
use App\Actions\Pos\RemoveOrVoidItem;
use App\Actions\Pos\SerializeTableCheck;
use App\Actions\Pos\SettleOrder;
use App\Enums\PaymentMethod;
use App\Enums\Station;
use App\Http\Requests\AddCheckItemsRequest;
use App\Http\Requests\PosQuoteRequest;
use App\Http\Requests\SettlePaymentRequest;
use App\Http\Requests\StorePosOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(BuildPosCatalog $catalog): Response
    {
        $tables = Table::query()
            ->active()
            ->orderBy('number')
            ->get(['id', 'number', 'label']);

        return Inertia::render('pos/index', [
            'categories' => $catalog->handle(),
            'tables' => $tables,
            'establishment_name' => Settings::get('general.name'),
        ]);
    }

    public function store(StorePosOrderRequest $request, PlacePosOrder $place, SettleOrder $settle): RedirectResponse
    {
        $data = $request->validated();
        $cashier = $request->user();

        // Pay before fire: create unpaid, settle (validates Σ == server total),
        // then fire to stations. A payment mismatch rolls everything back, so
        // the kitchen never sees an unpaid order.
        $order = DB::transaction(function () use ($data, $cashier, $place, $settle) {
            $order = $place->create($data, $cashier);
            $settle->handle($order, $data['tenders'], $cashier);

            return $place->fire($order, $cashier);
        });

        return back()
            ->with('placed_order_number', $order->number)
            ->with('change_due', $order->changeDue());
    }

    /**
     * Authoritative server-side total for a cart, so the payment screen and
     * settlement agree on the same number (the order doesn't exist yet).
     */
    public function quote(PosQuoteRequest $request, PriceOrderItems $pricer): JsonResponse
    {
        [$subtotal, $modifiersTotal] = $pricer->handle($request->validated()['items']);

        return response()->json(['total' => round($subtotal + $modifiersTotal, 2)]);
    }

    public function kds(string $station, BuildKdsTickets $tickets): Response
    {
        $station = Station::from($station);

        return Inertia::render('pos/kds', [
            'station' => $station->value,
            'station_label' => $station->label(),
            'tickets' => $tickets->handle($station),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function bump(Order $order, string $station, BumpStationTicket $action): RedirectResponse
    {
        $action->handle($order, Station::from($station), auth()->user());

        return back();
    }

    /**
     * Floor view: every active table with its open-check total and age.
     */
    public function tables(BuildFloorPlan $floor): Response
    {
        return Inertia::render('pos/tables', [
            'tables' => $floor->handle(),
            'establishment_name' => Settings::get('general.name'),
        ]);
    }

    /**
     * Open (or load) a table's check and render the editor.
     */
    public function check(Table $table, OpenTableCheck $open, BuildPosCatalog $catalog, SerializeTableCheck $serializer): Response
    {
        $order = $open->handle($table, auth()->user());

        return Inertia::render('pos/check', [
            'table' => ['id' => $table->id, 'number' => $table->number, 'label' => $table->label],
            'categories' => $catalog->handle(),
            'check' => $serializer->handle($order),
            'payment_methods' => [
                ['value' => PaymentMethod::Cash->value, 'label' => 'Наличные'],
                ['value' => PaymentMethod::CardOnline->value, 'label' => 'Карта'],
            ],
        ]);
    }

    public function addItems(Order $order, AddCheckItemsRequest $request, AddItemsToCheck $action): RedirectResponse
    {
        $action->handle($order, $request->validated()['items']);

        return back();
    }

    public function removeItem(OrderItem $item, RemoveOrVoidItem $action): RedirectResponse
    {
        $action->handle($item, auth()->user(), request()->input('reason'));

        return back();
    }

    public function close(Order $order, SettlePaymentRequest $request, CloseTableCheck $action): RedirectResponse
    {
        $order = $action->handle($order, $request->validated()['tenders'], auth()->user());

        return redirect()->route('pos.tables')
            ->with('closed_order_number', $order->number)
            ->with('change_due', $order->changeDue());
    }
}
