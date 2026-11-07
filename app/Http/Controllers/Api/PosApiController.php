<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\PriceOrderItems;
use App\Actions\Pos\AddItemsToCheck;
use App\Actions\Pos\BuildFloorPlan;
use App\Actions\Pos\BuildKdsTickets;
use App\Actions\Pos\BuildPosCatalog;
use App\Actions\Pos\BumpStationTicket;
use App\Actions\Pos\CloseGuestOrdering;
use App\Actions\Pos\CloseTableCheck;
use App\Actions\Pos\MoveTableCheck;
use App\Actions\Pos\OpenGuestOrdering;
use App\Actions\Pos\OpenTableCheck;
use App\Actions\Pos\PlacePosOrder;
use App\Actions\Pos\RemoveOrVoidItem;
use App\Actions\Pos\SerializeTableCheck;
use App\Actions\Pos\SettleOrder;
use App\Enums\PrinterType;
use App\Enums\Station;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddCheckItemsRequest;
use App\Http\Requests\MoveTableCheckRequest;
use App\Http\Requests\PosQuoteRequest;
use App\Http\Requests\SettlePaymentRequest;
use App\Http\Requests\StorePosOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Table;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * JSON twin of PosController for the POS mobile app: same actions, same
 * validation, but payloads instead of Inertia pages and redirects.
 */
class PosApiController extends Controller
{
    public function catalog(BuildPosCatalog $catalog): JsonResponse
    {
        return response()->json([
            'establishment_name' => Settings::get('general.name'),
            'categories' => $catalog->handle(),
            'tables' => Table::query()->active()->orderBy('number')->get(['id', 'number', 'label']),
        ]);
    }

    /**
     * Active printers configured in the admin («Оборудование»), grouped by type.
     */
    public function printers(): JsonResponse
    {
        $printers = Printer::query()->active()->orderBy('name')->get();

        return response()->json([
            'receipt_printers' => $printers
                ->where('type', PrinterType::Receipt)
                ->map->toPosPayload()
                ->values(),
        ]);
    }

    public function quote(PosQuoteRequest $request, PriceOrderItems $pricer): JsonResponse
    {
        [$subtotal, $modifiersTotal] = $pricer->handle($request->validated()['items']);

        return response()->json(['total' => round($subtotal + $modifiersTotal, 2)]);
    }

    public function store(StorePosOrderRequest $request, PlacePosOrder $place, SettleOrder $settle): JsonResponse
    {
        $data = $request->validated();
        $cashier = $request->user();

        // Same pay-before-fire transaction as the web cashier screen.
        $order = DB::transaction(function () use ($data, $cashier, $place, $settle) {
            $order = $place->create($data, $cashier);
            $settle->handle($order, $data['tenders'], $cashier);

            return $place->fire($order, $cashier);
        });

        return response()->json([
            'order_number' => $order->number,
            'change_due' => $order->changeDue(),
        ], 201);
    }

    public function tables(BuildFloorPlan $floor): JsonResponse
    {
        return response()->json(['tables' => $floor->handle()]);
    }

    /**
     * Open (or load) a table's check.
     */
    public function openCheck(Request $request, Table $table, OpenTableCheck $open, SerializeTableCheck $serializer): JsonResponse
    {
        $order = $open->handle($table, $request->user());

        return response()->json([
            'table' => ['id' => $table->id, 'number' => $table->number, 'label' => $table->label],
            'check' => $serializer->handle($order),
        ]);
    }

    public function showCheck(Order $order, SerializeTableCheck $serializer): JsonResponse
    {
        return response()->json(['check' => $serializer->handle($order)]);
    }

    public function addItems(Order $order, AddCheckItemsRequest $request, AddItemsToCheck $action, SerializeTableCheck $serializer): JsonResponse
    {
        $order = $action->handle($order, $request->validated()['items']);

        return response()->json(['check' => $serializer->handle($order)]);
    }

    public function removeItem(Request $request, OrderItem $item, RemoveOrVoidItem $action, SerializeTableCheck $serializer): JsonResponse
    {
        $action->handle($item, $request->user(), $request->input('reason'));

        return response()->json(['check' => $serializer->handle($item->order()->firstOrFail())]);
    }

    /**
     * Seat the check at another free table; answers like openCheck.
     */
    public function move(Order $order, MoveTableCheckRequest $request, MoveTableCheck $action, SerializeTableCheck $serializer): JsonResponse
    {
        $table = Table::query()->findOrFail($request->validated()['table_id']);
        $order = $action->handle($order, $table);

        return response()->json([
            'table' => ['id' => $table->id, 'number' => $table->number, 'label' => $table->label],
            'check' => $serializer->handle($order),
        ]);
    }

    public function openGuestOrdering(Order $order, OpenGuestOrdering $action, SerializeTableCheck $serializer): JsonResponse
    {
        return response()->json(['check' => $serializer->handle($action->handle($order))]);
    }

    public function closeGuestOrdering(Order $order, CloseGuestOrdering $action, SerializeTableCheck $serializer): JsonResponse
    {
        return response()->json(['check' => $serializer->handle($action->handle($order))]);
    }

    public function close(Order $order, SettlePaymentRequest $request, CloseTableCheck $action): JsonResponse
    {
        $order = $action->handle($order, $request->validated()['tenders'], $request->user());

        return response()->json([
            'order_number' => $order->number,
            'change_due' => $order->changeDue(),
        ]);
    }

    public function kds(string $station, BuildKdsTickets $tickets): JsonResponse
    {
        $station = Station::from($station);

        return response()->json([
            'station' => $station->value,
            'station_label' => $station->label(),
            'tickets' => $tickets->handle($station),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function bump(Request $request, Order $order, string $station, BumpStationTicket $action): JsonResponse
    {
        $action->handle($order, Station::from($station), $request->user());

        return response()->json(status: 204);
    }
}
