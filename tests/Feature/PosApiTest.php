<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create(['password' => 'secret-password']);
    $this->table = Table::factory()->create(['number' => 7]);

    $kitchen = Category::factory()->create();
    $bar = Category::factory()->bar()->create();
    $this->sandwich = Product::factory()->for($kitchen)->create(['name' => 'Сэндвич', 'price' => 350]);
    $this->latte = Product::factory()->for($bar)->create(['name' => 'Латте', 'price' => 240]);
});

it('issues a device token for valid staff credentials', function () {
    $response = $this->postJson(route('api.pos.login'), [
        'email' => $this->staff->email,
        'password' => 'secret-password',
        'device_name' => 'Касса 1',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('user.id', $this->staff->id)
        ->assertJsonStructure(['token']);

    $this->getJson(route('api.pos.me'), ['Authorization' => 'Bearer '.$response->json('token')])
        ->assertSuccessful()
        ->assertJsonPath('user.email', $this->staff->email);
});

it('rejects a wrong password', function () {
    $this->postJson(route('api.pos.login'), [
        'email' => $this->staff->email,
        'password' => 'nope',
        'device_name' => 'Касса 1',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('requires a token for every pos endpoint', function () {
    $this->getJson(route('api.pos.catalog'))->assertUnauthorized();
    $this->getJson(route('api.pos.tables'))->assertUnauthorized();
    $this->getJson(route('api.pos.kds', 'kitchen'))->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $token = $this->staff->createToken('Касса 1')->plainTextToken;

    $this->postJson(route('api.pos.logout'), [], ['Authorization' => "Bearer {$token}"])
        ->assertNoContent();

    expect($this->staff->tokens()->count())->toBe(0);
});

it('returns the catalog with tables', function () {
    Sanctum::actingAs($this->staff);

    $this->getJson(route('api.pos.catalog'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'categories')
        ->assertJsonPath('tables.0.number', 7);
});

it('quotes and places a paid walk-up order', function () {
    Sanctum::actingAs($this->staff);

    $items = [
        ['product_id' => $this->sandwich->id, 'quantity' => 2, 'modifier_ids' => []],
        ['product_id' => $this->latte->id, 'quantity' => 1, 'modifier_ids' => []],
    ];

    $this->postJson(route('api.pos.quote'), ['items' => $items])
        ->assertSuccessful()
        ->assertJsonPath('total', 940);

    $this->postJson(route('api.pos.orders.store'), [
        'delivery_type' => 'pickup',
        'items' => $items,
        'tenders' => [['method' => 'cash', 'amount' => 940, 'received_amount' => 1000]],
    ])
        ->assertCreated()
        ->assertJsonPath('change_due', fn ($change) => (float) $change === 60.0);

    expect(Order::sole()->payment_status)->toBe(PaymentStatus::Paid);
});

it('returns validation errors as json when the payment does not match', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson(route('api.pos.orders.store'), [
        'delivery_type' => 'pickup',
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
        'tenders' => [['method' => 'cash', 'amount' => 100]],
    ])->assertUnprocessable();

    expect(Order::count())->toBe(0);
});

it('runs a table check from open to close', function () {
    Sanctum::actingAs($this->staff);

    $checkId = $this->postJson(route('api.pos.tables.check', $this->table))
        ->assertSuccessful()
        ->assertJsonPath('table.number', 7)
        ->json('check.id');

    $this->postJson(route('api.pos.orders.items', $checkId), [
        'items' => [['product_id' => $this->latte->id, 'quantity' => 2, 'modifier_ids' => []]],
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'check.items')
        ->assertJsonPath('check.total', fn ($total) => (float) $total === 480.0);

    $this->getJson(route('api.pos.tables'))
        ->assertSuccessful()
        ->assertJsonPath('tables.0.check.id', $checkId);

    $this->postJson(route('api.pos.orders.close', $checkId), [
        'tenders' => [['method' => 'card_online', 'amount' => 480]],
    ])->assertSuccessful();

    expect(Order::find($checkId)->payment_status)->toBe(PaymentStatus::Paid);
});

it('removes an unsent item and returns the refreshed check', function () {
    Sanctum::actingAs($this->staff);

    $checkId = $this->postJson(route('api.pos.tables.check', $this->table))->json('check.id');
    $itemId = $this->postJson(route('api.pos.orders.items', $checkId), [
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
    ])->json('check.items.0.id');

    $this->deleteJson(route('api.pos.order-items.remove', $itemId))
        ->assertSuccessful()
        ->assertJsonPath('check.id', $checkId);
});

it('lists station tickets and bumps them', function () {
    Sanctum::actingAs($this->staff);

    $this->postJson(route('api.pos.orders.store'), [
        'delivery_type' => 'pickup',
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1, 'modifier_ids' => []]],
        'tenders' => [['method' => 'cash', 'amount' => 350]],
    ])->assertCreated();

    $orderId = $this->getJson(route('api.pos.kds', 'kitchen'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'tickets')
        ->json('tickets.0.id');

    $this->postJson(route('api.pos.orders.bump', [$orderId, 'kitchen']))->assertNoContent();

    $this->getJson(route('api.pos.kds', 'kitchen'))->assertJsonCount(0, 'tickets');
    expect(Order::find($orderId)->status)->not->toBe(OrderStatus::Accepted);
});
