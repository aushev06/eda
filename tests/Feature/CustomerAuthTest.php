<?php

use App\Actions\Auth\StartCustomerLogin;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerOtp;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('login page renders for a guest', function () {
    $this->get('/account/login')->assertSuccessful();
});

it('starting login creates a customer and otp and flashes dev_otp in test env', function () {
    $this->post('/account/login/start', ['phone' => '+79991234567'])
        ->assertRedirect('/account/login/verify')
        ->assertSessionHas('dev_otp');

    expect(Customer::where('phone', '+79991234567')->exists())->toBeTrue()
        ->and(CustomerOtp::where('phone', '+79991234567')->count())->toBe(1);
});

it('normalises russian phone format', function () {
    $this->post('/account/login/start', ['phone' => '8 (999) 123-45-67'])
        ->assertRedirect();

    expect(CustomerOtp::where('phone', '+79991234567')->exists())->toBeTrue();
});

it('throttles otp requests under the cooldown', function () {
    $this->post('/account/login/start', ['phone' => '+79991234567']);
    $this->post('/account/login/start', ['phone' => '+79991234567'])
        ->assertSessionHasErrors('phone');
});

it('verify logs the customer in with the correct code', function () {
    $start = app(StartCustomerLogin::class)->handle('+79991234567');

    $this->post('/account/login/verify', [
        'phone' => '+79991234567',
        'code' => $start['code'],
    ])->assertRedirect('/account');

    $this->assertAuthenticatedAs(Customer::where('phone', '+79991234567')->first(), 'customer');
});

it('rejects a wrong code', function () {
    app(StartCustomerLogin::class)->handle('+79991234567');

    $this->post('/account/login/verify', [
        'phone' => '+79991234567',
        'code' => '0000',
    ])->assertSessionHasErrors('code');

    $this->assertGuest('customer');
});

it('locks the otp after too many failed attempts', function () {
    $start = app(StartCustomerLogin::class)->handle('+79990000000');

    for ($i = 0; $i < 6; $i++) {
        $this->post('/account/login/verify', [
            'phone' => '+79990000000',
            'code' => '0000',
        ]);
    }

    $this->post('/account/login/verify', [
        'phone' => '+79990000000',
        'code' => $start['code'],
    ])->assertSessionHasErrors('code');
});

it('rejects an expired code', function () {
    $customer = Customer::factory()->create(['phone' => '+79991110000']);
    CustomerOtp::create([
        'phone' => '+79991110000',
        'code_hash' => Hash::make('1234'),
        'expires_at' => now()->subMinute(),
        'created_at' => now()->subMinutes(10),
    ]);

    $this->post('/account/login/verify', [
        'phone' => '+79991110000',
        'code' => '1234',
    ])->assertSessionHasErrors('code');

    $this->assertGuest('customer');
});

it('guards account pages from guests', function () {
    $this->get('/account')->assertRedirect('/account/login');
    $this->get('/account/orders')->assertRedirect('/account/login');
    $this->get('/account/addresses')->assertRedirect('/account/login');
});

it('logged-in customer can view profile, orders and addresses', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer, 'customer');

    $this->get('/account')->assertSuccessful();
    $this->get('/account/orders')->assertSuccessful();
    $this->get('/account/addresses')->assertSuccessful();
});

it('orders list only shows own orders', function () {
    $me = Customer::factory()->create();
    $other = Customer::factory()->create();
    Order::factory()->create(['customer_id' => $me->id, 'number' => 'A-MINE']);
    Order::factory()->create(['customer_id' => $other->id, 'number' => 'A-OTHER']);

    $this->actingAs($me, 'customer')
        ->get('/account/orders')
        ->assertSuccessful()
        ->assertSee('A-MINE')
        ->assertDontSee('A-OTHER');
});

it('updates customer profile', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->patch('/account', ['name' => 'Иван', 'email' => 'ivan@test.local'])
        ->assertRedirect('/account');

    expect($customer->refresh()->name)->toBe('Иван')
        ->and($customer->email)->toBe('ivan@test.local');
});

it('creates and lists a customer address', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post('/account/addresses', [
            'street' => 'Тверская, 1',
            'apartment' => '42',
            'is_default' => true,
        ])
        ->assertRedirect('/account/addresses');

    expect($customer->addresses()->count())->toBe(1)
        ->and($customer->addresses()->first()->is_default)->toBeTrue();
});

it('refuses to delete another customers address', function () {
    $me = Customer::factory()->create();
    $other = Customer::factory()->create();
    $stranger = CustomerAddress::factory()->for($other)->create();

    $this->actingAs($me, 'customer')
        ->delete("/account/addresses/{$stranger->id}")
        ->assertForbidden();

    expect(CustomerAddress::find($stranger->id))->not->toBeNull();
});

it('customer logout redirects to home', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->post('/account/logout')
        ->assertRedirect('/');

    $this->assertGuest('customer');
});

it('checkout passes saved addresses for an authenticated customer', function () {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->for($customer)->create(['street' => 'Тверская, 1']);

    $this->actingAs($customer, 'customer')
        ->get('/checkout')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/checkout')
            ->has('saved_addresses', 1)
            ->where('saved_addresses.0.street', 'Тверская, 1'));
});
