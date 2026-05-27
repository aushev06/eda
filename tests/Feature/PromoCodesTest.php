<?php

use App\Actions\Promo\ApplyPromoCode;
use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;
use App\Enums\PromoDiscountType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('applies a percent promo code', function () {
    $promo = PromoCode::factory()->create([
        'code' => 'PCT10',
        'discount_type' => PromoDiscountType::Percent,
        'value' => 10,
    ]);

    $result = app(ApplyPromoCode::class)->handle('PCT10', 1000);

    expect($result['promo']->is($promo))->toBeTrue()
        ->and($result['discount'])->toBe(100.0);
});

it('caps percent discount by max_discount_amount', function () {
    PromoCode::factory()->create([
        'code' => 'CAPPED',
        'discount_type' => PromoDiscountType::Percent,
        'value' => 50,
        'max_discount_amount' => 200,
    ]);

    expect(app(ApplyPromoCode::class)->handle('CAPPED', 1000)['discount'])->toBe(200.0);
});

it('applies a fixed promo code', function () {
    PromoCode::factory()->create([
        'code' => 'FIX300',
        'discount_type' => PromoDiscountType::Fixed,
        'value' => 300,
    ]);

    expect(app(ApplyPromoCode::class)->handle('FIX300', 1000)['discount'])->toBe(300.0);
});

it('clamps fixed discount to subtotal so total cannot go negative', function () {
    PromoCode::factory()->create([
        'code' => 'BIG',
        'discount_type' => PromoDiscountType::Fixed,
        'value' => 5000,
    ]);

    expect(app(ApplyPromoCode::class)->handle('BIG', 800)['discount'])->toBe(800.0);
});

it('rejects an unknown code', function () {
    expect(fn () => app(ApplyPromoCode::class)->handle('UNKNOWN', 1000))
        ->toThrow(ValidationException::class);
});

it('rejects an inactive code', function () {
    PromoCode::factory()->inactive()->create(['code' => 'OFF']);

    expect(fn () => app(ApplyPromoCode::class)->handle('OFF', 1000))
        ->toThrow(ValidationException::class);
});

it('rejects when subtotal is below min_order_amount', function () {
    PromoCode::factory()->create([
        'code' => 'NEEDS1K',
        'discount_type' => PromoDiscountType::Fixed,
        'value' => 100,
        'min_order_amount' => 1000,
    ]);

    expect(fn () => app(ApplyPromoCode::class)->handle('NEEDS1K', 500))
        ->toThrow(ValidationException::class);
});

it('rejects when starts_at is in the future', function () {
    PromoCode::factory()->create([
        'code' => 'FUTURE',
        'starts_at' => now()->addDay(),
    ]);

    expect(fn () => app(ApplyPromoCode::class)->handle('FUTURE', 1000))
        ->toThrow(ValidationException::class);
});

it('rejects when ends_at is in the past', function () {
    PromoCode::factory()->create([
        'code' => 'EXPIRED',
        'ends_at' => now()->subDay(),
    ]);

    expect(fn () => app(ApplyPromoCode::class)->handle('EXPIRED', 1000))
        ->toThrow(ValidationException::class);
});

it('enforces the global uses limit', function () {
    $promo = PromoCode::factory()->create([
        'code' => 'ONCE',
        'max_uses_global' => 1,
    ]);

    $promo->usages()->create(['discount_amount' => 100, 'created_at' => now()]);

    expect(fn () => app(ApplyPromoCode::class)->handle('ONCE', 1000))
        ->toThrow(ValidationException::class);
});

it('enforces per-customer uses limit', function () {
    $promo = PromoCode::factory()->create([
        'code' => 'PERME',
        'max_uses_per_customer' => 1,
    ]);

    $customer = Customer::factory()->create(['phone' => '+79991234567']);
    $promo->usages()->create([
        'customer_id' => $customer->id,
        'discount_amount' => 50,
        'created_at' => now(),
    ]);

    expect(fn () => app(ApplyPromoCode::class)->handle('PERME', 1000, '+79991234567'))
        ->toThrow(ValidationException::class);
});

it('enforces first_order_only against customer with prior orders', function () {
    PromoCode::factory()->firstOrderOnly()->create(['code' => 'WELCOME']);
    $customer = Customer::factory()->create(['phone' => '+79990000000']);
    Order::factory()->create(['customer_id' => $customer->id]);

    expect(fn () => app(ApplyPromoCode::class)->handle('WELCOME', 1000, '+79990000000'))
        ->toThrow(ValidationException::class);
});

it('allows first_order_only for a new customer', function () {
    PromoCode::factory()->firstOrderOnly()->create(['code' => 'WELCOME']);

    $result = app(ApplyPromoCode::class)->handle('WELCOME', 1000, '+79991111111');
    expect($result['discount'])->toBeGreaterThan(0);
});

it('promo validate endpoint returns discount on success', function () {
    PromoCode::factory()->create([
        'code' => 'API10',
        'discount_type' => PromoDiscountType::Percent,
        'value' => 10,
    ]);

    $this->postJson('/promo-codes/validate', [
        'code' => 'API10',
        'subtotal' => 500,
    ])
        ->assertOk()
        ->assertJson([
            'valid' => true,
            'code' => 'API10',
            'discount' => 50,
        ]);
});

it('promo validate endpoint returns 422 with message on failure', function () {
    $this->postJson('/promo-codes/validate', [
        'code' => 'NOPE',
        'subtotal' => 500,
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['valid', 'message']);
});

it('creates an order with promo code applied and records usage', function () {
    PromoCode::factory()->create([
        'code' => 'ORDERFIX',
        'discount_type' => PromoDiscountType::Fixed,
        'value' => 200,
    ]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 1000]);

    $this->post('/orders', [
        'customer_name' => 'Иван',
        'customer_phone' => '+79991234567',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'promo_code' => 'ORDERFIX',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->promo_code)->toBe('ORDERFIX')
        ->and($order->promo_code_id)->not->toBeNull()
        ->and((float) $order->discount_total)->toBe(200.0)
        ->and((float) $order->total)->toBe(1000.0 - 200.0)
        ->and($order->promoCode->usages()->count())->toBe(1);
});

it('rejects checkout with an invalid promo code', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 500]);

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'promo_code' => 'NOSUCH',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])
        ->assertSessionHasErrors('promo_code');

    expect(Order::count())->toBe(0);
});
