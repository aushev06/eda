<?php

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Widgets\OrdersStatsOverview;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the orders list page', function () {
    Order::factory()->count(3)->create();

    $this->get(OrderResource::getUrl('index'))->assertSuccessful();
});

it('renders the order view page with items', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertSuccessful();
});

it('allows new → accepted transition', function () {
    $order = Order::factory()->status(OrderStatus::New)->create();

    app(TransitionOrderStatus::class)->handle($order, OrderStatus::Accepted);

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted)
        ->and($order->fresh()->accepted_at)->not->toBeNull()
        ->and($order->statusEvents()->count())->toBe(1);
});

it('blocks invalid transition new → delivered', function () {
    $order = Order::factory()->status(OrderStatus::New)->create();

    expect(fn () => app(TransitionOrderStatus::class)->handle($order, OrderStatus::Delivered))
        ->toThrow(InvalidArgumentException::class);
});

it('blocks transitioning out of terminal status', function () {
    $order = Order::factory()->status(OrderStatus::Delivered)->create();

    expect(fn () => app(TransitionOrderStatus::class)->handle($order, OrderStatus::New))
        ->toThrow(InvalidArgumentException::class);
});

it('stores cancellation reason and cancelled_at on cancel', function () {
    $order = Order::factory()->status(OrderStatus::Preparing)->create();

    app(TransitionOrderStatus::class)->handle($order, OrderStatus::Cancelled, note: 'Клиент не отвечает');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($order->cancellation_reason)->toBe('Клиент не отвечает');
});

it('logs status events with from/to and actor', function () {
    $order = Order::factory()->status(OrderStatus::New)->create();
    $actor = User::factory()->create();

    app(TransitionOrderStatus::class)->handle($order, OrderStatus::Accepted, actor: $actor);

    $event = $order->statusEvents()->first();

    expect($event->from_status)->toBe(OrderStatus::New)
        ->and($event->to_status)->toBe(OrderStatus::Accepted)
        ->and($event->changed_by_user_id)->toBe($actor->id);
});

it('recalculates totals from items', function () {
    $order = Order::factory()->create([
        'subtotal' => 0,
        'modifiers_total' => 0,
        'delivery_fee' => 100,
        'discount_total' => 0,
        'total' => 0,
    ]);

    OrderItem::factory()->for($order)->create([
        'unit_price' => 500,
        'quantity' => 2,
        'modifiers_total' => 50,
        'line_total' => 1050,
    ]);

    $order->load('items.modifiers');
    $order->recalculateTotals();

    expect((float) $order->subtotal)->toBe(1000.00)
        ->and((float) $order->modifiers_total)->toBe(50.00)
        ->and((float) $order->total)->toBe(1150.00);
});

it('keeps snapshot fields when product is deleted', function () {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create([
        'product_name' => 'Маргарита',
    ]);

    $item->product->delete();

    $item->refresh();

    expect($item->product_id)->toBeNull()
        ->and($item->product_name)->toBe('Маргарита');
});

it('view page shows accept and cancel actions for a new order', function () {
    $order = Order::factory()->status(OrderStatus::New)->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionExists('transition_accepted')
        ->assertActionExists('transition_cancelled')
        ->assertActionDoesNotExist('transition_delivered');
});

it('view page calls transition action and updates the order', function () {
    $order = Order::factory()->status(OrderStatus::New)->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('transition_accepted')
        ->assertHasNoActionErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Accepted);
});

it('renders the stats widget on the dashboard', function () {
    Order::factory()->status(OrderStatus::New)->count(2)->create();
    Order::factory()->status(OrderStatus::Preparing)->create();

    Livewire::test(OrdersStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Новые заказы')
        ->assertSee('В работе')
        ->assertSee('Выручка за сегодня');
});
