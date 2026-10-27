<?php

use App\Actions\Pos\OpenTableCheck;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->table = Table::factory()->create(['number' => 7]);

    $this->sandwich = Product::factory()->for(Category::factory())->create(['name' => 'Сэндвич', 'price' => 350]);
    Product::factory()->for(Category::factory()->bar())->create(['name' => 'Латте', 'price' => 240]);
});

it('requires staff auth for the v2 screens', function () {
    $this->get(route('pos.v2.index'))->assertRedirect();
    $this->get(route('pos.v2.tables'))->assertRedirect();
    $this->get(route('pos.v2.kds', ['station' => 'kitchen']))->assertRedirect();
});

it('renders the v2 cashier with the shared catalog', function () {
    $this->actingAs($this->staff)
        ->get(route('pos.v2.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pos-v2/index')
            ->has('categories', 2)
            ->has('tables', 1)
            ->has('categories.0.products', 1)
            ->has('categories.0.products.0.modifier_groups'));
});

it('renders the v2 floor, table check and station screens', function () {
    $this->actingAs($this->staff);

    $this->get(route('pos.v2.tables'))
        ->assertInertia(fn (Assert $page) => $page->component('pos-v2/tables')->where('tables.0.number', 7));

    $this->get(route('pos.v2.tables.check', $this->table))
        ->assertInertia(fn (Assert $page) => $page->component('pos-v2/check')->where('table.number', 7)->has('check.id'));

    $this->get(route('pos.v2.kds', ['station' => 'bar']))
        ->assertInertia(fn (Assert $page) => $page->component('pos-v2/kds')->where('station', 'bar'));
});

it('keeps the original screens on their own pages', function () {
    $this->actingAs($this->staff)
        ->get(route('pos.index'))
        ->assertInertia(fn (Assert $page) => $page->component('pos/index'));
});

it('returns to the v2 floor after closing a table from v2', function () {
    $order = app(OpenTableCheck::class)->handle($this->table, $this->staff);

    $this->actingAs($this->staff)
        ->post(route('pos.orders.items', $order), [
            'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
        ]);

    $this->post(route('pos.v2.orders.close', $order->refresh()), [
        'tenders' => [['method' => 'cash', 'amount' => 350, 'received_amount' => 500]],
    ])
        ->assertRedirect(route('pos.v2.tables'))
        ->assertSessionHas('closed_order_number', $order->number)
        ->assertSessionHas('change_due', fn ($changeDue) => (float) $changeDue === 150.0);

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Delivered);
});
