<?php

use App\Actions\Loyalty\GrantOrderBonuses;
use App\Actions\Loyalty\RefundOrderBonuses;
use App\Actions\Loyalty\SpendBonuses;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DeliveryType;
use App\Enums\LoyaltyTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLevel;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\LoyaltyLevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LoyaltyLevelsSeeder::class);
});

it('picks the right level by lifetime spend', function () {
    expect(LoyaltyLevel::forLifetimeSpend(0)->name)->toBe('Стартовый')
        ->and(LoyaltyLevel::forLifetimeSpend(29999)->name)->toBe('Стартовый')
        ->and(LoyaltyLevel::forLifetimeSpend(30000)->name)->toBe('Основной')
        ->and(LoyaltyLevel::forLifetimeSpend(49999)->name)->toBe('Основной')
        ->and(LoyaltyLevel::forLifetimeSpend(50000)->name)->toBe('Премиальный')
        ->and(LoyaltyLevel::forLifetimeSpend(70000)->name)->toBe('VIP')
        ->and(LoyaltyLevel::forLifetimeSpend(150000)->name)->toBe('VIP');
});

it('grants cashback at the customer current tier on delivery', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Delivering,
        'total' => 1000,
    ]);

    app(GrantOrderBonuses::class)->handle($order);
    $order->refresh();

    expect((float) $order->bonus_earned_amount)->toBe(100.0); // 10% Стартовый

    $account = $customer->loyaltyAccount;
    expect($account)->not->toBeNull()
        ->and((float) $account->balance)->toBe(100.0)
        ->and((float) $account->lifetime_earned)->toBe(100.0)
        ->and((float) $account->lifetime_spent_on_orders)->toBe(1000.0)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Earn)->count())->toBe(1);
});

it('upgrades the tier as lifetime spend grows', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['lifetime_spent_on_orders' => 29500]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Delivering,
        'total' => 1000, // pushes total to 30,500 → Основной 15%
    ]);

    app(GrantOrderBonuses::class)->handle($order);

    expect((float) $order->fresh()->bonus_earned_amount)->toBe(150.0);
});

it('does not grant cashback twice for the same order', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 1000,
    ]);

    app(GrantOrderBonuses::class)->handle($order);
    app(GrantOrderBonuses::class)->handle($order->refresh());

    expect((float) $customer->loyaltyAccount->fresh()->balance)->toBe(100.0);
});

it('spend.quote clamps to balance and cap', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 5000]);

    // Subtotal 1000 → cap = 30% = 300
    expect(app(SpendBonuses::class)->quote($customer, 300, 1000))->toBe(300.0);

    expect(fn () => app(SpendBonuses::class)->quote($customer, 500, 1000))
        ->toThrow(ValidationException::class);
});

it('spend.quote refuses to spend more than balance', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 50]);

    expect(fn () => app(SpendBonuses::class)->quote($customer, 100, 10000))
        ->toThrow(ValidationException::class);
});

it('refunds spent and earned bonuses on cancellation', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 500, 'lifetime_earned' => 0, 'lifetime_spent_on_orders' => 0]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 1000,
        'bonus_used_amount' => 100,
        'bonus_earned_amount' => 150,
    ]);

    // Pretend delivery happened: account state matches order.
    $account->update([
        'balance' => 500 - 100 + 150, // 550
        'lifetime_earned' => 150,
        'lifetime_spent_on_orders' => 1000,
    ]);

    app(RefundOrderBonuses::class)->handle($order);

    $account->refresh();
    $order->refresh();

    expect((float) $account->balance)->toBe(500.0)
        ->and((float) $account->lifetime_earned)->toBe(0.0)
        ->and((float) $account->lifetime_spent_on_orders)->toBe(0.0)
        ->and((float) $order->bonus_used_amount)->toBe(0.0)
        ->and((float) $order->bonus_earned_amount)->toBe(0.0);
});

it('transitioning to Delivered grants bonuses end-to-end', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Delivering,
        'total' => 2000,
    ]);

    app(TransitionOrderStatus::class)->handle($order, OrderStatus::Delivered);

    expect((float) $customer->loyaltyAccount->fresh()->balance)->toBe(200.0); // 10%
});

it('transitioning to Cancelled refunds spent bonuses', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 0]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Accepted,
        'bonus_used_amount' => 100,
    ]);

    app(TransitionOrderStatus::class)->handle($order, OrderStatus::Cancelled);

    expect((float) $account->fresh()->balance)->toBe(100.0)
        ->and((float) $order->fresh()->bonus_used_amount)->toBe(0.0);
});

it('creates an order with bonus spend for an authenticated customer', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 1000]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 1000]);

    $this->actingAs($customer, 'customer')
        ->post('/orders', [
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_type' => DeliveryType::Pickup->value,
            'payment_method' => PaymentMethod::Cash->value,
            'bonus_to_use' => 200,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
            ],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect((float) $order->bonus_used_amount)->toBe(200.0)
        ->and((float) $order->total)->toBe(800.0)
        ->and((float) $account->fresh()->balance)->toBe(800.0);
});

it('rejects bonus_to_use over the configured cap', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 10000]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 1000]);

    $this->actingAs($customer, 'customer')
        ->post('/orders', [
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_type' => DeliveryType::Pickup->value,
            'payment_method' => PaymentMethod::Cash->value,
            'bonus_to_use' => 500, // > 30% of 1000
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
            ],
        ])
        ->assertSessionHasErrors('bonus_to_use');

    expect(Order::count())->toBe(0);
});

it('rejects bonus_to_use for guests', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['price' => 1000]);

    $this->post('/orders', [
        'customer_name' => 'X',
        'customer_phone' => '+79990000000',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::Cash->value,
        'bonus_to_use' => 100,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []],
        ],
    ])
        ->assertSessionHasErrors('bonus_to_use');

    expect(Order::count())->toBe(0);
});

it('renders the bonuses page with current level and progress', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 250, 'lifetime_spent_on_orders' => 15000]);

    $this->actingAs($customer, 'customer')
        ->get('/account/bonuses')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/bonuses')
            ->where('account.balance', 250)
            ->where('current_level.name', 'Стартовый')
            ->where('next_level.name', 'Основной')
            ->has('progress'));
});

it('checkout passes loyalty info to authenticated customers', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 500]);

    $this->actingAs($customer, 'customer')
        ->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/checkout')
            ->where('loyalty.balance', 500)
            ->where('loyalty.max_spend_percent', 30));
});
