<?php

use App\Actions\Orders\TransitionOrderStatus;
use App\Actions\Pos\BuildKdsTickets;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Station;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();

    $kitchenCategory = Category::factory()->create();
    $barCategory = Category::factory()->bar()->create();

    $this->sandwich = Product::factory()->for($kitchenCategory)->create(['name' => 'Сэндвич', 'price' => 350]);
    $this->latte = Product::factory()->for($barCategory)->create(['name' => 'Латте', 'price' => 240]);
});

function placePosOrder(array $overrides = []): TestResponse
{
    // Cart is 2×350 + 1×240 = 940; pay it in full cash unless overridden.
    return test()->actingAs(test()->staff)->post(route('pos.orders.store'), array_merge([
        'delivery_type' => 'pickup',
        'items' => [
            ['product_id' => test()->sandwich->id, 'quantity' => 2, 'modifier_ids' => []],
            ['product_id' => test()->latte->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
        'tenders' => [['method' => 'cash', 'amount' => 940]],
    ], $overrides));
}

it('requires authentication for the pos screens', function () {
    $this->get(route('pos.index'))->assertRedirect();
    $this->post(route('pos.orders.store'))->assertRedirect();
});

it('renders the cashier screen with products and stations', function () {
    $this->actingAs($this->staff)
        ->get(route('pos.index'))
        ->assertSuccessful();
});

it('places a walk-up order without creating a phantom customer', function () {
    placePosOrder()->assertSessionHasNoErrors();

    $order = Order::sole();

    expect($order->source)->toBe(OrderSource::Pos)
        ->and($order->customer_id)->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Accepted)
        ->and($order->accepted_at)->not->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and((float) $order->total)->toBe(940.0)
        ->and(Customer::count())->toBe(0);
});

it('requires a table for dine-in pos orders', function () {
    placePosOrder(['delivery_type' => 'dine_in'])
        ->assertSessionHasErrors('table_id');

    $table = Table::factory()->create();

    placePosOrder(['delivery_type' => 'dine_in', 'table_id' => $table->id])
        ->assertSessionHasNoErrors();

    expect(Order::sole()->table_id)->toBe($table->id);
});

it('splits an order between kitchen and bar station screens', function () {
    placePosOrder()->assertSessionHasNoErrors();

    $kitchen = app(BuildKdsTickets::class)->handle(Station::Kitchen);
    $bar = app(BuildKdsTickets::class)->handle(Station::Bar);

    expect($kitchen)->toHaveCount(1)
        ->and(collect($kitchen[0]['items'])->pluck('name')->all())->toBe(['Сэндвич'])
        ->and($bar)->toHaveCount(1)
        ->and(collect($bar[0]['items'])->pluck('name')->all())->toBe(['Латте']);
});

it('keeps the order active until every station bumps, then auto-delivers pos orders', function () {
    placePosOrder()->assertSessionHasNoErrors();
    $order = Order::sole();

    $this->actingAs($this->staff)
        ->post(route('pos.orders.bump', ['order' => $order->id, 'station' => 'kitchen']))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Accepted)
        ->and(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toBeEmpty()
        ->and(app(BuildKdsTickets::class)->handle(Station::Bar))->toHaveCount(1);

    $this->actingAs($this->staff)
        ->post(route('pos.orders.bump', ['order' => $order->id, 'station' => 'bar']))
        ->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->items->every(fn ($item) => $item->completed_at !== null))->toBeTrue()
        ->and((float) $order->bonus_earned_amount)->toBe(0.0);
});

it('does not fail the bump when an admin already advanced the order', function () {
    placePosOrder()->assertSessionHasNoErrors();
    $order = Order::sole();

    $transition = app(TransitionOrderStatus::class);
    $transition->handle($order, OrderStatus::Preparing, $this->staff);
    $transition->handle($order, OrderStatus::Ready, $this->staff);
    $transition->handle($order, OrderStatus::Delivered, $this->staff);

    $this->actingAs($this->staff)
        ->post(route('pos.orders.bump', ['order' => $order->id, 'station' => 'kitchen']))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Delivered)
        ->and($order->items->where('completed_at', null)
            ->filter(fn ($item) => $item->product_id === $this->sandwich->id))->toBeEmpty();
});

it('hides new, cancelled, and far-future scheduled site orders from the kds', function () {
    // A site order still pending acceptance must not reach the kitchen.
    placePosOrder()->assertSessionHasNoErrors();
    $posOrder = Order::sole();

    $newSiteOrder = Order::factory()->create(['status' => OrderStatus::New]);
    $newSiteOrder->items()->create([
        'product_id' => $this->sandwich->id,
        'product_name' => $this->sandwich->name,
        'quantity' => 1,
        'unit_price' => 350,
        'modifiers_total' => 0,
        'line_total' => 350,
    ]);

    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toHaveCount(1);

    // Scheduled far in the future: hidden until the lead window opens.
    $scheduled = Order::factory()->status(OrderStatus::Accepted)->create([
        'accepted_at' => now(),
        'scheduled_for' => now()->addHours(5),
    ]);
    $scheduled->items()->create([
        'product_id' => $this->sandwich->id,
        'product_name' => $this->sandwich->name,
        'quantity' => 1,
        'unit_price' => 350,
        'modifiers_total' => 0,
        'line_total' => 350,
    ]);

    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toHaveCount(1);

    // Cancellation removes the ticket.
    app(TransitionOrderStatus::class)->handle($posOrder, OrderStatus::Cancelled, $this->staff);

    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toBeEmpty();
});

it('respects the product-level station override', function () {
    // Дижестив живёт в кухонной категории, но готовится за баром.
    $this->sandwich->update(['station' => Station::Bar]);

    placePosOrder()->assertSessionHasNoErrors();

    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toBeEmpty()
        ->and(app(BuildKdsTickets::class)->handle(Station::Bar))->toHaveCount(1);
});
