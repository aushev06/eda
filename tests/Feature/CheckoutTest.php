<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::flush();
});

function squareZonePolygon(): array
{
    return [[0.0, 0.0], [0.0, 1.0], [1.0, 1.0], [1.0, 0.0]];
}

function makeProductWithModifiers(): array
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create([
        'price' => 500,
        'is_active' => true,
        'in_stop_list' => false,
    ]);

    $group = ModifierGroup::factory()->create([
        'min_select' => 1,
        'max_select' => 1,
        'is_required' => true,
    ]);
    $modifier = Modifier::factory()->for($group, 'modifierGroup')->create([
        'price_delta' => 150,
        'is_active' => true,
    ]);
    $product->modifierGroups()->attach($group, ['sort_order' => 0]);

    return [$product, $group, $modifier];
}

it('checkout page renders for a guest with active zones', function () {
    DeliveryZone::factory()->withPolygon(squareZonePolygon())->create(['name' => 'Центр']);
    DeliveryZone::factory()->withPolygon(squareZonePolygon())->inactive()->create(['name' => 'Скрытая']);

    $this->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/checkout')
            ->has('zones', 1)
            ->where('zones.0.name', 'Центр'));
});

it('creates a pickup order and redirects to thanks', function () {
    [$product] = makeProductWithModifiers();
    $modifier = $product->modifierGroups->first()->modifiers->first();

    $response = $this->post('/orders', [
        'customer_name' => 'Иван',
        'customer_phone' => '+79991234567',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'modifier_ids' => [$modifier->id]],
        ],
    ]);

    $response->assertRedirect();
    $order = Order::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($response->headers->get('Location'))->toContain($order->number)
        ->and($order->status)->toBe(OrderStatus::New)
        ->and($order->delivery_type)->toBe(DeliveryType::Pickup)
        ->and($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and((float) $order->subtotal)->toBe(1000.0)
        ->and((float) $order->modifiers_total)->toBe(300.0)
        ->and((float) $order->delivery_fee)->toBe(0.0)
        ->and((float) $order->total)->toBe(1300.0)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->modifiers)->toHaveCount(1)
        ->and($order->statusEvents()->count())->toBe(1);

    expect(Customer::where('phone', '+79991234567')->exists())->toBeTrue();
});

it('creates a delivery order and applies zone fee + snapshots address', function () {
    [$product] = makeProductWithModifiers();
    $modifier = $product->modifierGroups->first()->modifiers->first();
    $zone = DeliveryZone::factory()->withPolygon(squareZonePolygon())->create([
        'name' => 'Центр',
        'delivery_fee' => 200,
        'min_order_amount' => 500,
    ]);

    $this->post('/orders', [
        'customer_name' => 'Анна',
        'customer_phone' => '+79992223344',
        'delivery_type' => DeliveryType::Delivery->value,
        'payment_method' => PaymentMethod::CardCourier->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$modifier->id]],
        ],
        'delivery' => [
            'zone_id' => $zone->id,
            'street' => 'Тверская, 1',
            'apartment' => '42',
        ],
    ])->assertRedirect();

    $order = Order::query()->latest('id')->first();

    expect($order->delivery_zone_id)->toBe($zone->id)
        ->and((float) $order->delivery_fee)->toBe(200.0)
        ->and($order->delivery_street)->toBe('Тверская, 1')
        ->and($order->delivery_apartment)->toBe('42')
        ->and((float) $order->total)->toBe(500.0 + 150.0 + 200.0);
});

it('rejects an order when accepting_orders is off', function () {
    [$product] = makeProductWithModifiers();
    Settings::set('mode.accepting_orders', false);
    Settings::set('mode.closed_reason', 'Перерыв');

    $this->post('/orders', [
        'customer_name' => 'Иван',
        'customer_phone' => '+79991234567',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])
        ->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('rejects when delivery zone subtotal is below the zone minimum', function () {
    [$product] = makeProductWithModifiers();
    $modifier = $product->modifierGroups->first()->modifiers->first();
    $zone = DeliveryZone::factory()->withPolygon(squareZonePolygon())->create([
        'min_order_amount' => 10000,
    ]);

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Delivery->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$modifier->id]],
        ],
        'delivery' => [
            'zone_id' => $zone->id,
            'street' => 'X',
        ],
    ])
        ->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('rejects when modifier does not belong to the product', function () {
    [$product] = makeProductWithModifiers();
    $strayGroup = ModifierGroup::factory()->create();
    $strayModifier = Modifier::factory()->for($strayGroup, 'modifierGroup')->create();

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$strayModifier->id]],
        ],
    ])
        ->assertSessionHasErrors();

    expect(Order::count())->toBe(0);
});

it('rejects when required modifier group is not selected', function () {
    [$product] = makeProductWithModifiers();

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])
        ->assertSessionHasErrors();

    expect(Order::count())->toBe(0);
});

it('uses server-side prices, ignoring whatever client might send', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 999]);

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])->assertRedirect();

    $order = Order::query()->latest('id')->first();
    expect((float) $order->subtotal)->toBe(999.0)
        ->and((float) $order->items->first()->unit_price)->toBe(999.0);
});

it('thanks page shows the order summary', function () {
    [$product] = makeProductWithModifiers();
    $modifier = $product->modifierGroups->first()->modifiers->first();

    $this->post('/orders', [
        'customer_name' => 'Иван',
        'customer_phone' => '+79991234567',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$modifier->id]],
        ],
    ]);

    $order = Order::query()->latest('id')->first();

    $this->get("/orders/{$order->number}/thanks")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/order-thanks')
            ->where('order.number', $order->number)
            ->has('order.items', 1));
});

it('initial payment status is pending', function () {
    [$product] = makeProductWithModifiers();
    $modifier = $product->modifierGroups->first()->modifiers->first();

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$modifier->id]],
        ],
    ]);

    expect(Order::query()->latest('id')->first()->payment_status)->toBe(PaymentStatus::Pending);
});
