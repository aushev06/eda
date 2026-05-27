<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use Database\Seeders\LoyaltyLevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LoyaltyLevelsSeeder::class);
});

it('renders the public loyalty page for a guest with all levels', function () {
    $this->get('/loyalty')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/loyalty')
            ->has('levels', 4)
            ->where('levels.0.name', 'Стартовый')
            ->where('levels.3.name', 'VIP')
            ->where('customer_balance', null)
            ->where('is_enabled', true));
});

it('includes customer balance for an authenticated customer', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['balance' => 350]);

    $this->actingAs($customer, 'customer')
        ->get('/loyalty')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer_balance', 350));
});
