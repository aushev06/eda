<?php

use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('redirects to home and stores table_id in session on QR scan', function () {
    $table = Table::factory()->create();

    $this->get('/t/'.$table->qr_token)
        ->assertRedirect('/')
        ->assertSessionHas('table_id', $table->id);
});

it('returns 404 for an unknown token', function () {
    $this->get('/t/nopenotreal')->assertNotFound();
});

it('returns 404 for an inactive table', function () {
    $table = Table::factory()->inactive()->create();

    $this->get('/t/'.$table->qr_token)->assertNotFound();
});

it('shares active table info as inertia prop', function () {
    $table = Table::factory()->create(['number' => 7, 'label' => 'У окна']);

    $this->withSession(['table_id' => $table->id])
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('table.id', $table->id)
            ->where('table.number', 7)
            ->where('table.label', 'У окна'));
});

it('purges stale table_id when the table is removed', function () {
    $table = Table::factory()->create();
    $tableId = $table->id;
    $table->delete();

    $this->withSession(['table_id' => $tableId])
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('table', null))
        ->assertSessionMissing('table_id');
});

it('leave route clears session table_id', function () {
    $table = Table::factory()->create();

    $this->withSession(['table_id' => $table->id])
        ->from('/')
        ->post('/t/leave')
        ->assertRedirect('/')
        ->assertSessionMissing('table_id');
});

it('checkout passes table from session', function () {
    $table = Table::factory()->create(['number' => 12]);

    $this->withSession(['table_id' => $table->id])
        ->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('table.number', 12));
});

it('creates a dine_in order with table snapshot', function () {
    $table = Table::factory()->create(['number' => 5, 'label' => 'У стены']);
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 500]);

    $this->withSession(['table_id' => $table->id])
        ->post('/orders', [
            'customer_name' => 'Аня',
            'customer_phone' => '+79991110000',
            'delivery_type' => DeliveryType::DineIn->value,
            'payment_method' => PaymentMethod::Cash->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
            ],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->delivery_type)->toBe(DeliveryType::DineIn)
        ->and($order->table_id)->toBe($table->id)
        ->and($order->table_number_snapshot)->toBe('5')
        ->and((float) $order->delivery_fee)->toBe(0.0)
        ->and((float) $order->total)->toBe(500.0);
});

it('keeps the table_number_snapshot after the table is deleted', function () {
    $table = Table::factory()->create(['number' => 9]);
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 300]);

    $this->withSession(['table_id' => $table->id])
        ->post('/orders', [
            'customer_name' => 'X',
            'customer_phone' => '+79990000000',
            'delivery_type' => DeliveryType::DineIn->value,
            'payment_method' => PaymentMethod::Cash->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
            ],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->first();
    $table->delete();

    $order->refresh();

    expect($order->table_id)->toBeNull()
        ->and($order->table_number_snapshot)->toBe('9');
});

it('rejects dine_in order without table_id and no session', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 500]);

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::DineIn->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])
        ->assertSessionHasErrors('table_id');

    expect(Order::count())->toBe(0);
});

it('clears session table_id after a successful order', function () {
    $table = Table::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 200]);

    $this->withSession(['table_id' => $table->id])
        ->post('/orders', [
            'customer_name' => 'X',
            'customer_phone' => '+79990000000',
            'delivery_type' => DeliveryType::DineIn->value,
            'payment_method' => PaymentMethod::Cash->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
            ],
        ])
        ->assertRedirect()
        ->assertSessionMissing('table_id');
});
