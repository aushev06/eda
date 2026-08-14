<?php

use App\Actions\Pos\BuildKdsTickets;
use App\Actions\Pos\BumpStationTicket;
use App\Actions\Pos\OpenTableCheck;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Station;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->table = Table::factory()->create(['number' => 5]);

    $kitchen = Category::factory()->create();
    $bar = Category::factory()->bar()->create();
    $this->sandwich = Product::factory()->for($kitchen)->create(['name' => 'Сэндвич', 'price' => 350]);
    $this->latte = Product::factory()->for($bar)->create(['name' => 'Латте', 'price' => 240]);
});

function openCheck(): Order
{
    return app(OpenTableCheck::class)->handle(test()->table, test()->staff);
}

function addRound(Order $order, array $items): TestResponse
{
    return test()->actingAs(test()->staff)
        ->post(route('pos.orders.items', $order), ['items' => $items]);
}

it('requires staff auth for table routes', function () {
    $this->get(route('pos.tables'))->assertRedirect();
});

it('opens an empty check for a table without creating a customer', function () {
    $order = openCheck();

    expect($order->status)->toBe(OrderStatus::Accepted)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payment_method)->toBeNull()
        ->and($order->customer_id)->toBeNull()
        ->and($order->opened_at)->not->toBeNull()
        ->and($order->isOpenTableCheck())->toBeTrue();
});

it('returns the same open check when the table is opened again', function () {
    $first = openCheck();
    $second = openCheck();

    expect($second->id)->toBe($first->id)
        ->and(Order::where('table_id', $this->table->id)->count())->toBe(1);
});

it('adds rounds that fire to the correct stations and grows the total', function () {
    $order = openCheck();

    addRound($order, [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]])
        ->assertSessionHasNoErrors();
    expect((float) $order->refresh()->total)->toBe(350.0);

    // Second round an hour later
    addRound($order, [['product_id' => $this->latte->id, 'quantity' => 2, 'modifier_ids' => []]])
        ->assertSessionHasNoErrors();
    expect((float) $order->refresh()->total)->toBe(830.0);

    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen))->toHaveCount(1)
        ->and(app(BuildKdsTickets::class)->handle(Station::Bar))->toHaveCount(1);
});

it('does NOT close the check when all items are bumped — the critical guard', function () {
    $order = openCheck();
    addRound($order, [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]]);

    app(BumpStationTicket::class)->handle($order->refresh(), Station::Kitchen, $this->staff);

    $order->refresh();
    expect($order->status)->toBeIn([OrderStatus::Accepted, OrderStatus::Preparing])
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->isOpenTableCheck())->toBeTrue();

    // And a later round can still be added
    addRound($order, [['product_id' => $this->latte->id, 'quantity' => 1, 'modifier_ids' => []]])
        ->assertSessionHasNoErrors();
    expect($order->refresh()->items()->count())->toBe(2);
});

it('hard-deletes an un-made item for free', function () {
    $order = openCheck();
    addRound($order, [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]]);
    $item = $order->refresh()->items->first();

    $this->actingAs($this->staff)
        ->delete(route('pos.order-items.remove', $item))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->items()->count())->toBe(0)
        ->and((float) $order->total)->toBe(0.0);
});

it('voids a made item with a reason and drops it from the total but keeps it in data', function () {
    $order = openCheck();
    addRound($order, [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]]);
    app(BumpStationTicket::class)->handle($order->refresh(), Station::Kitchen, $this->staff);
    $item = $order->refresh()->items->first();

    // Without a reason a made item cannot be voided
    $this->actingAs($this->staff)
        ->delete(route('pos.order-items.remove', $item))
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->staff)
        ->delete(route('pos.order-items.remove', $item), ['reason' => 'гость передумал'])
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->voided_at)->not->toBeNull()
        ->and($item->void_reason)->toBe('гость передумал')
        ->and($order->refresh()->items()->count())->toBe(1)
        ->and((float) $order->total)->toBe(0.0);
});

it('closes the check with a payment method, settling and freeing the table', function () {
    $order = openCheck();
    addRound($order, [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]]);

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order->refresh()), [
            'tenders' => [['method' => 'cash', 'amount' => 350]],
        ])
        ->assertRedirect(route('pos.tables'));

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->payment_method->value)->toBe('cash')
        ->and($order->isOpenTableCheck())->toBeFalse();
});

it('rejects closing an empty check', function () {
    $order = openCheck();

    $this->actingAs($this->staff)
        ->post(route('pos.orders.close', $order), ['payment_method' => 'cash'])
        ->assertSessionHasErrors();

    expect($order->refresh()->isOpenTableCheck())->toBeTrue();
});

it('keeps quick pickup POS orders auto-delivering on bump (regression)', function () {
    $this->actingAs($this->staff)->post(route('pos.orders.store'), [
        'delivery_type' => 'pickup',
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
        'tenders' => [['method' => 'cash', 'amount' => 350]],
    ])->assertSessionHasNoErrors();

    $order = Order::where('table_id', null)->latest('id')->first();
    app(BumpStationTicket::class)->handle($order, Station::Kitchen, $this->staff);

    expect($order->refresh()->status)->toBe(OrderStatus::Delivered);
});
