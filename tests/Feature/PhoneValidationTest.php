<?php

use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects invalid phone on login start', function () {
    $this->post('/account/login/start', ['phone' => '12345'])
        ->assertSessionHasErrors('phone');

    expect(Customer::query()->count())->toBe(0);
});

it('rejects non-Russian phone on login start', function () {
    $this->post('/account/login/start', ['phone' => '+1 555 1234567'])
        ->assertSessionHasErrors('phone');
});

it('accepts a russian phone on login start', function () {
    $this->post('/account/login/start', ['phone' => '+7 (999) 123-45-67'])
        ->assertRedirect('/account/login/verify');

    expect(Customer::where('phone', '+79991234567')->exists())->toBeTrue();
});

it('rejects orders with invalid phone', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 500]);

    $this->post('/orders', [
        'customer_name' => 'Иван',
        'customer_phone' => '12345',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []]],
    ])->assertSessionHasErrors('customer_phone');

    expect(Order::query()->count())->toBe(0);
});

it('normalizes the order phone to +7XXXXXXXXXX before storing', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 500]);

    $this->post('/orders', [
        'customer_name' => 'Иван',
        'customer_phone' => '8 (999) 123-45-67',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []]],
    ])->assertRedirect();

    $order = Order::query()->latest('id')->first();
    expect($order->customer_phone)->toBe('+79991234567');
    expect(Customer::where('phone', '+79991234567')->exists())->toBeTrue();
});

it('rejects invalid phone on promo validation', function () {
    $this->postJson('/promo-codes/validate', [
        'code' => 'WELCOME10',
        'subtotal' => 1000,
        'customer_phone' => 'abc123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('customer_phone');
});
