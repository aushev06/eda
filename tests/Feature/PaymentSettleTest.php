<?php

use App\Actions\Pos\BuildKdsTickets;
use App\Actions\Pos\OpenTableCheck;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Station;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->table = Table::factory()->create(['number' => 7]);

    $kitchen = Category::factory()->create();
    $this->sandwich = Product::factory()->for($kitchen)->create(['name' => 'Сэндвич', 'price' => 800]);
});

function openTableWithItem(): Order
{
    $order = app(OpenTableCheck::class)->handle(test()->table, test()->staff);

    test()->actingAs(test()->staff)->post(route('pos.orders.items', $order), [
        'items' => [['product_id' => test()->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
    ]);

    return $order->refresh();
}

it('closes a table split between cash and card, recording the breakdown', function () {
    $order = openTableWithItem();
    expect((float) $order->total)->toBe(800.0);

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), [
            'tenders' => [
                ['method' => 'cash', 'amount' => 500],
                ['method' => 'card_online', 'amount' => 300],
            ],
        ])
        ->assertRedirect(route('pos.tables'));

    $order->refresh()->load('payments');

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::Split)
        ->and($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->payments)->toHaveCount(2)
        ->and((float) $order->changeDue())->toBe(0.0);
});

it('computes change when cash received exceeds the cash due', function () {
    $order = openTableWithItem();

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), [
            'tenders' => [
                ['method' => 'cash', 'amount' => 800, 'received_amount' => 1000],
            ],
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and((float) $order->changeDue())->toBe(200.0);
});

it('rejects a single tender charged more than the whole bill', function () {
    $order = openTableWithItem(); // total 800

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), [
            'tenders' => [['method' => 'card_online', 'amount' => 1000]],
        ])
        ->assertSessionHasErrors();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payments()->count())->toBe(0);
});

it('rejects an overpay on card within a split (card cannot exceed its share)', function () {
    $order = openTableWithItem(); // total 800

    // 900 card + (-100) is impossible; 900 card alone already exceeds the bill.
    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), [
            'tenders' => [
                ['method' => 'card_online', 'amount' => 900],
                ['method' => 'cash', 'amount' => 100, 'received_amount' => 100],
            ],
        ])
        ->assertSessionHasErrors();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('rejects a settlement whose tenders do not sum to the total', function () {
    $order = openTableWithItem();

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), [
            'tenders' => [['method' => 'cash', 'amount' => 700]],
        ])
        ->assertSessionHasErrors();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payments()->count())->toBe(0);
});

it('rejects card received less than its amount is impossible but cash short is rejected', function () {
    $order = openTableWithItem();

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), [
            'tenders' => [['method' => 'cash', 'amount' => 800, 'received_amount' => 700]],
        ])
        ->assertSessionHasErrors();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('takes pickup payment before firing, recording payment and delivering to kitchen', function () {
    $response = $this->actingAs($this->staff)->post(route('pos.orders.store'), [
        'delivery_type' => 'pickup',
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
        'tenders' => [['method' => 'card_online', 'amount' => 800]],
    ]);
    $response->assertSessionHasNoErrors();

    $order = Order::where('source', OrderSource::Pos)->latest('id')->first();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::CardOnline)
        ->and($order->status)->toBe(OrderStatus::Accepted) // fired to stations
        ->and($order->payments)->toHaveCount(1);

    // It is now visible on the kitchen station.
    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toHaveCount(1);
});

it('rolls back the whole pickup order when payment does not match the total', function () {
    $before = Order::count();

    $this->actingAs($this->staff)->post(route('pos.orders.store'), [
        'delivery_type' => 'pickup',
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
        'tenders' => [['method' => 'cash', 'amount' => 500]], // total is 800
    ])->assertSessionHasErrors();

    // No order created, nothing fired.
    expect(Order::count())->toBe($before)
        ->and(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toBeEmpty();
});

it('does not double-settle an already paid order', function () {
    $order = openTableWithItem();

    $this->actingAs($this->staff)->post(route('pos.orders.close', $order), [
        'tenders' => [['method' => 'cash', 'amount' => 800]],
    ])->assertRedirect();

    // Second attempt on the now-closed check is rejected.
    $this->actingAs($this->staff)->post(route('pos.orders.close', $order->refresh()), [
        'tenders' => [['method' => 'cash', 'amount' => 800]],
    ])->assertSessionHasErrors();

    expect($order->refresh()->payments()->count())->toBe(1);
});

it('returns a server-authoritative quote for a cart', function () {
    $this->actingAs($this->staff)
        ->postJson(route('pos.quote'), [
            'items' => [['product_id' => $this->sandwich->id, 'quantity' => 2, 'modifier_ids' => []]],
        ])
        ->assertOk()
        ->assertJson(['total' => 1600]);
});
