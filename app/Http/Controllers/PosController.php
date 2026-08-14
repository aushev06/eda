<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PriceOrderItems;
use App\Actions\Pos\AddItemsToCheck;
use App\Actions\Pos\BuildKdsTickets;
use App\Actions\Pos\BumpStationTicket;
use App\Actions\Pos\CloseTableCheck;
use App\Actions\Pos\OpenTableCheck;
use App\Actions\Pos\PlacePosOrder;
use App\Actions\Pos\RemoveOrVoidItem;
use App\Actions\Pos\SettleOrder;
use App\Enums\DeliveryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Station;
use App\Http\Requests\AddCheckItemsRequest;
use App\Http\Requests\PosQuoteRequest;
use App\Http\Requests\SettlePaymentRequest;
use App\Http\Requests\StorePosOrderRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        $tables = Table::query()
            ->active()
            ->orderBy('number')
            ->get(['id', 'number', 'label']);

        return Inertia::render('pos/index', [
            'categories' => $this->catalog(),
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
    public function tables(): Response
    {
        // One open check per table; key by table_id for the floor lookup.
        $openChecks = Order::query()
            ->whereNotNull('table_id')
            ->where('source', OrderSource::Pos)
            ->where('delivery_type', DeliveryType::DineIn)
            ->whereIn('status', [OrderStatus::Accepted, OrderStatus::Preparing])
            ->where('payment_status', PaymentStatus::Pending)
            ->get()
            ->keyBy('table_id');

        $floor = Table::query()
            ->active()
            ->orderBy('number')
            ->get(['id', 'number', 'label'])
            ->map(function (Table $table) use ($openChecks) {
                $check = $openChecks->get($table->id);

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'label' => $table->label,
                    'check' => $check ? [
                        'id' => $check->id,
                        'total' => (float) $check->total,
                        'opened_at' => $check->opened_at?->toIso8601String(),
                    ] : null,
                ];
            });

        return Inertia::render('pos/tables', [
            'tables' => $floor,
            'establishment_name' => Settings::get('general.name'),
        ]);
    }

    /**
     * Open (or load) a table's check and render the editor.
     */
    public function check(Table $table, OpenTableCheck $open): Response
    {
        $order = $open->handle($table, auth()->user());

        return Inertia::render('pos/check', [
            'table' => ['id' => $table->id, 'number' => $table->number, 'label' => $table->label],
            'categories' => $this->catalog(),
            'check' => $this->serializeCheck($order),
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

    /**
     * Shared product catalog payload for the cashier screen and check editor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function catalog(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($q) => $q
                    ->where('is_active', true)
                    ->where('in_stop_list', false)
                    ->orderBy('sort_order'),
                'products.category',
                'products.modifierGroups' => fn ($q) => $q->orderBy('sort_order'),
                'products.modifierGroups.modifiers' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'station' => $product->resolvedStation()->value,
                    'modifier_groups' => $product->modifierGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'min_select' => $group->min_select,
                        'max_select' => $group->max_select,
                        'modifiers' => $group->modifiers->map(fn ($modifier) => [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'price_delta' => (float) $modifier->price_delta,
                        ]),
                    ]),
                ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCheck(Order $order): array
    {
        $order->load(['items' => fn ($q) => $q->orderBy('id'), 'items.modifiers', 'items.product.category']);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'total' => (float) $order->total,
            'opened_at' => $order->opened_at?->toIso8601String(),
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'line_total' => (float) $item->line_total,
                'station' => $item->product?->resolvedStation()->value,
                'completed' => $item->completed_at !== null,
                'voided' => $item->voided_at !== null,
                'void_reason' => $item->void_reason,
                'modifiers' => $item->modifiers->map(fn ($m) => [
                    'name' => $m->modifier_name,
                    'price_delta' => (float) $m->price_delta,
                ]),
            ]),
        ];
    }
}
