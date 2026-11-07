<?php

use App\Enums\DeliveryType;
use App\Enums\OnlinePaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Pages\OnlinePayments;
use App\Models\Category;
use App\Models\OnlinePayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::flush();
    Http::preventStrayRequests();
});

function enablePaymentGateway(string $gateway, array $credentials, bool $testMode = false): void
{
    $values = [
        'payments.enabled' => true,
        'payments.gateway' => $gateway,
        'payments.test_mode' => $testMode,
    ];

    foreach ($credentials as $key => $value) {
        $values["payments.{$gateway}.{$key}"] = $value;
    }

    Settings::setMany($values);
}

function enableYooKassa(): void
{
    enablePaymentGateway('yookassa', ['shop_id' => '123456', 'secret_key' => 'test_secret']);
}

function onlineOrderPayload(): array
{
    $product = Product::factory()->for(Category::factory())->create([
        'price' => 700,
        'is_active' => true,
        'in_stop_list' => false,
    ]);

    return [
        'customer_name' => 'Иван',
        'customer_phone' => '+79991234567',
        'delivery_type' => DeliveryType::Pickup->value,
        'payment_method' => PaymentMethod::CardOnline->value,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'modifier_ids' => []],
        ],
    ];
}

function unpaidOnlineOrder(float $total = 1400): Order
{
    return Order::factory()->create([
        'status' => OrderStatus::New,
        'payment_method' => PaymentMethod::CardOnline,
        'payment_status' => PaymentStatus::Pending,
        'total' => $total,
    ]);
}

function tinkoffToken(array $params, string $password): string
{
    unset($params['Token']);
    $params['Password'] = $password;
    ksort($params);

    return hash('sha256', implode('', array_map(
        fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
        array_filter($params, 'is_scalar'),
    )));
}

it('hides online card payment at checkout until a gateway is configured', function () {
    $this->get('/checkout')
        ->assertInertia(fn (Assert $page) => $page->where('establishment.online_payment', false));

    enablePaymentGateway('yookassa', ['shop_id' => '123456']);

    expect(app(PaymentGatewayManager::class)->isOnlinePaymentAvailable())->toBeFalse();

    enableYooKassa();

    $this->get('/checkout')
        ->assertInertia(fn (Assert $page) => $page->where('establishment.online_payment', true));
});

it('rejects an online card order while online payment is off', function () {
    $this->post('/orders', onlineOrderPayload())
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

it('sends the customer to the gateway after placing an online card order', function () {
    enableYooKassa();
    Http::fake([
        'api.yookassa.ru/v3/payments' => Http::response([
            'id' => 'yk-1',
            'status' => 'pending',
            'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yoomoney.ru/checkout/yk-1'],
        ]),
    ]);

    $this->post('/orders', onlineOrderPayload())
        ->assertRedirect('https://yoomoney.ru/checkout/yk-1');

    $order = Order::query()->sole();
    $payment = OnlinePayment::query()->sole();

    expect($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($payment->order_id)->toBe($order->id)
        ->and($payment->gateway)->toBe('yookassa')
        ->and($payment->external_id)->toBe('yk-1')
        ->and($payment->reference)->toBe($order->number.'-'.$payment->id)
        ->and((float) $payment->amount)->toBe(1400.0);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotence-Key', $payment->reference)
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('123456:test_secret'))
        && $request['amount'] === ['value' => '1400.00', 'currency' => 'RUB']
        && $request['capture'] === true
        && $request['confirmation']['return_url'] === route('orders.payment.return', ['number' => $order->number]));
});

it('answers an Inertia checkout with an external location visit', function () {
    enableYooKassa();
    Http::fake([
        'api.yookassa.ru/*' => Http::response([
            'id' => 'yk-1',
            'confirmation' => ['confirmation_url' => 'https://yoomoney.ru/checkout/yk-1'],
        ]),
    ]);

    $this->post('/orders', onlineOrderPayload(), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://yoomoney.ru/checkout/yk-1');
});

it('keeps the order and offers a retry when the gateway refuses the payment', function () {
    enableYooKassa();
    Http::fake([
        'api.yookassa.ru/*' => Http::response(['type' => 'error', 'description' => 'Неверный shopId'], 401),
    ]);

    $response = $this->post('/orders', onlineOrderPayload());

    $order = Order::query()->sole();
    $response->assertRedirect(route('orders.thanks', ['number' => $order->number]))
        ->assertSessionHas('payment_error', 'Неверный shopId');

    expect(OnlinePayment::query()->sole()->status)->toBe(OnlinePaymentStatus::Failed);

    $this->get(route('orders.thanks', ['number' => $order->number]))
        ->assertInertia(fn (Assert $page) => $page->where('can_pay_online', true));
});

it('marks the order paid once when the customer returns from the gateway', function () {
    enableYooKassa();
    $order = unpaidOnlineOrder();
    $payment = OnlinePayment::factory()->for($order)->create([
        'gateway' => 'yookassa',
        'external_id' => 'yk-1',
        'amount' => 1400,
    ]);
    Http::fake([
        'api.yookassa.ru/v3/payments/yk-1' => Http::response(['id' => 'yk-1', 'status' => 'succeeded']),
    ]);

    $this->get(route('orders.payment.return', ['number' => $order->number]))
        ->assertRedirect(route('orders.thanks', ['number' => $order->number]));
    $this->get(route('orders.payment.return', ['number' => $order->number]));

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($payment->refresh()->status)->toBe(OnlinePaymentStatus::Succeeded)
        ->and($order->payments()->count())->toBe(1)
        ->and((float) $order->payments()->first()->amount)->toBe(1400.0);

    Http::assertSentCount(1);

    $this->get(route('orders.thanks', ['number' => $order->number]))
        ->assertInertia(fn (Assert $page) => $page->where('can_pay_online', false));
});

it('leaves the order unpaid when the customer cancelled at the gateway', function () {
    enableYooKassa();
    $order = unpaidOnlineOrder();
    $payment = OnlinePayment::factory()->for($order)->create(['gateway' => 'yookassa', 'external_id' => 'yk-1']);
    Http::fake([
        'api.yookassa.ru/*' => Http::response([
            'id' => 'yk-1',
            'status' => 'canceled',
            'cancellation_details' => ['reason' => 'expired_on_confirmation'],
        ]),
    ]);

    $this->get(route('orders.payment.return', ['number' => $order->number]));

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($payment->refresh()->status)->toBe(OnlinePaymentStatus::Cancelled)
        ->and($payment->error_message)->toBe('expired_on_confirmation');
});

it('confirms a payment from a YooKassa webhook by re-checking it with the API', function () {
    enableYooKassa();
    $order = unpaidOnlineOrder();
    OnlinePayment::factory()->for($order)->create(['gateway' => 'yookassa', 'external_id' => 'yk-1']);
    Http::fake([
        'api.yookassa.ru/v3/payments/yk-1' => Http::response(['id' => 'yk-1', 'status' => 'succeeded']),
    ]);

    $this->postJson('/payments/yookassa/webhook', [
        'type' => 'notification',
        'event' => 'payment.succeeded',
        'object' => ['id' => 'yk-1', 'status' => 'succeeded'],
    ])->assertSuccessful();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('does not trust the status sent in a webhook', function () {
    enableYooKassa();
    $order = unpaidOnlineOrder();
    OnlinePayment::factory()->for($order)->create(['gateway' => 'yookassa', 'external_id' => 'yk-1']);
    Http::fake([
        'api.yookassa.ru/*' => Http::response(['id' => 'yk-1', 'status' => 'pending']),
    ]);

    $this->postJson('/payments/yookassa/webhook', [
        'event' => 'payment.succeeded',
        'object' => ['id' => 'yk-1', 'status' => 'succeeded'],
    ])->assertSuccessful();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('ignores webhooks for unknown payments and gateways', function () {
    enableYooKassa();

    $this->postJson('/payments/yookassa/webhook', ['object' => ['id' => 'nope']])->assertSuccessful();
    $this->postJson('/payments/unknown/webhook', [])->assertNotFound();

    Http::assertNothingSent();
});

it('starts a new attempt from the thanks page and refuses paid orders', function () {
    enableYooKassa();
    $order = unpaidOnlineOrder();
    Http::fake([
        'api.yookassa.ru/*' => Http::sequence()
            ->push(['id' => 'yk-1', 'confirmation' => ['confirmation_url' => 'https://yoomoney.ru/1']])
            ->push(['id' => 'yk-2', 'confirmation' => ['confirmation_url' => 'https://yoomoney.ru/2']]),
    ]);

    $this->post(route('orders.pay', ['number' => $order->number]))->assertRedirect('https://yoomoney.ru/1');
    $this->post(route('orders.pay', ['number' => $order->number]))->assertRedirect('https://yoomoney.ru/2');

    expect($order->onlinePayments()->pluck('reference')->unique())->toHaveCount(2);

    $order->update(['payment_status' => PaymentStatus::Paid]);

    $this->post(route('orders.pay', ['number' => $order->number]))
        ->assertRedirect(route('orders.thanks', ['number' => $order->number]))
        ->assertSessionHas('payment_error');
});

it('registers and checks payments with the Alfa-Bank RBS API', function () {
    enablePaymentGateway('alfabank', ['username' => 'shop-api', 'password' => 'secret'], testMode: true);
    $order = unpaidOnlineOrder(1250.5);
    Http::fake([
        'alfa.rbsuat.com/payment/rest/register.do' => Http::response([
            'orderId' => 'rbs-1',
            'formUrl' => 'https://alfa.rbsuat.com/payment/merchants/pay?mdOrder=rbs-1',
        ]),
        'alfa.rbsuat.com/payment/rest/getOrderStatusExtended.do' => Http::response([
            'errorCode' => '0',
            'orderStatus' => 2,
        ]),
    ]);

    $this->post(route('orders.pay', ['number' => $order->number]))
        ->assertRedirect('https://alfa.rbsuat.com/payment/merchants/pay?mdOrder=rbs-1');

    $payment = $order->onlinePayments()->sole();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'register.do')
        && $request['userName'] === 'shop-api'
        && $request['password'] === 'secret'
        && $request['orderNumber'] === $payment->reference
        && (int) $request['amount'] === 125050
        && $request['dynamicCallbackUrl'] === route('payments.webhook', ['gateway' => 'alfabank']));

    $this->get('/payments/alfabank/webhook?mdOrder=rbs-1&operation=deposited&status=1')->assertSuccessful();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('shows the bank error when Sberbank rejects the registration', function () {
    enablePaymentGateway('sberbank', ['token' => 'sber-token']);
    $order = unpaidOnlineOrder();
    Http::fake([
        'securepayments.sberbank.ru/payment/rest/register.do' => Http::response([
            'errorCode' => '5',
            'errorMessage' => 'Доступ запрещён',
        ]),
    ]);

    $this->post(route('orders.pay', ['number' => $order->number]))
        ->assertSessionHas('payment_error', 'Доступ запрещён');

    Http::assertSent(fn (Request $request) => $request['token'] === 'sber-token' && ! isset($request['userName']));
});

it('signs T-Bank requests and accepts only signed notifications', function () {
    enablePaymentGateway('tinkoff', ['terminal_key' => 'TestDEMO', 'password' => 'pass']);
    $order = unpaidOnlineOrder();
    Http::fake([
        'securepay.tinkoff.ru/v2/Init' => Http::response([
            'Success' => true,
            'PaymentId' => '777',
            'PaymentURL' => 'https://securepay.tinkoff.ru/new/777',
        ]),
        'securepay.tinkoff.ru/v2/GetState' => Http::response([
            'Success' => true,
            'PaymentId' => '777',
            'Status' => 'CONFIRMED',
        ]),
    ]);

    $this->post(route('orders.pay', ['number' => $order->number]))
        ->assertRedirect('https://securepay.tinkoff.ru/new/777');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'Init')
        && $request['TerminalKey'] === 'TestDEMO'
        && $request['Amount'] === 140000
        && $request['Token'] === tinkoffToken($request->data(), 'pass'));

    $notification = ['TerminalKey' => 'TestDEMO', 'PaymentId' => 777, 'Status' => 'CONFIRMED', 'Success' => true];

    $this->postJson('/payments/tinkoff/webhook', $notification + ['Token' => 'forged'])
        ->assertSuccessful()
        ->assertSee('OK');
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);

    $this->postJson('/payments/tinkoff/webhook', $notification + ['Token' => tinkoffToken($notification, 'pass')])
        ->assertSuccessful();
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('saves gateway settings from the admin page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/online-payments')->assertSuccessful();

    Livewire::test(OnlinePayments::class)
        ->fillForm([
            'enabled' => true,
            'gateway' => 'tinkoff',
            'test_mode' => false,
            'credentials.tinkoff.terminal_key' => ' TestDEMO ',
            'credentials.tinkoff.password' => 'pass',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Settings::get('payments.gateway'))->toBe('tinkoff')
        ->and(Settings::get('payments.tinkoff.terminal_key'))->toBe('TestDEMO')
        ->and(app(PaymentGatewayManager::class)->activeKey())->toBe('tinkoff');
});
