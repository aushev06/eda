<?php

use App\Enums\LoyaltyTransactionType;
use App\Enums\NotificationType;
use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('does not share notifications when guest visits home', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null));
});

it('shares unread count and recent notifications for the logged-in customer', function () {
    $customer = Customer::factory()->create();

    CustomerNotification::create([
        'customer_id' => $customer->id,
        'type' => NotificationType::Promo,
        'title' => 'Промокод HELLO20',
        'body' => 'Скидка 20% на первый заказ',
        'action_url' => '/',
    ]);
    CustomerNotification::create([
        'customer_id' => $customer->id,
        'type' => NotificationType::System,
        'title' => 'Прочитанное',
        'read_at' => now(),
    ]);

    $this->actingAs($customer, 'customer')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread_count', 1)
            ->has('notifications.recent', 2)
            ->where('notifications.recent.0.title', 'Прочитанное')
            ->where('notifications.recent.1.title', 'Промокод HELLO20'));
});

it('creates a notification when a loyalty earn transaction is recorded', function () {
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);

    $account->transactions()->create([
        'type' => LoyaltyTransactionType::Earn,
        'amount' => 120,
        'balance_after' => 120,
        'note' => 'Кэшбек 5% за заказ A-001',
        'created_at' => now(),
    ]);

    expect($customer->notifications()->count())->toBe(1);
    $notification = $customer->notifications()->first();
    expect($notification->type)->toBe(NotificationType::LoyaltyEarn);
    expect($notification->title)->toContain('120');
    expect($notification->body)->toBe('Кэшбек 5% за заказ A-001');
});

it('creates a level-up notification when lifetime spend crosses a threshold', function () {
    LoyaltyLevel::create(['name' => 'Базовый', 'min_lifetime_spend' => 0, 'cashback_percent' => 1, 'sort_order' => 0]);
    LoyaltyLevel::create(['name' => 'Серебро', 'min_lifetime_spend' => 5000, 'cashback_percent' => 3, 'sort_order' => 1]);

    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);
    $account->update(['lifetime_spent_on_orders' => 1000]);

    expect($customer->notifications()->where('type', NotificationType::LoyaltyLevelUp)->count())->toBe(0);

    $account->update(['lifetime_spent_on_orders' => 5500]);

    $levelUp = $customer->notifications()->where('type', NotificationType::LoyaltyLevelUp)->first();
    expect($levelUp)->not->toBeNull();
    expect($levelUp->title)->toContain('Серебро');
});

it('lets a customer mark their own notification as read', function () {
    $customer = Customer::factory()->create();
    $notification = CustomerNotification::create([
        'customer_id' => $customer->id,
        'type' => NotificationType::Promo,
        'title' => 'Промо',
    ]);

    $this->actingAs($customer, 'customer')
        ->post("/account/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('blocks marking someone else\'s notification', function () {
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $notification = CustomerNotification::create([
        'customer_id' => $owner->id,
        'type' => NotificationType::Promo,
        'title' => 'Промо',
    ]);

    $this->actingAs($intruder, 'customer')
        ->post("/account/notifications/{$notification->id}/read")
        ->assertForbidden();
});

it('marks all unread as read in one call', function () {
    $customer = Customer::factory()->create();
    CustomerNotification::create([
        'customer_id' => $customer->id,
        'type' => NotificationType::Promo,
        'title' => 'A',
    ]);
    CustomerNotification::create([
        'customer_id' => $customer->id,
        'type' => NotificationType::Promo,
        'title' => 'B',
    ]);

    $this->actingAs($customer, 'customer')
        ->post('/account/notifications/read-all')
        ->assertRedirect();

    expect($customer->notifications()->whereNull('read_at')->count())->toBe(0);
});

it('does not duplicate level-up notifications when spend goes up within the same tier', function () {
    LoyaltyLevel::create(['name' => 'Базовый', 'min_lifetime_spend' => 0, 'cashback_percent' => 1, 'sort_order' => 0]);
    LoyaltyLevel::create(['name' => 'Серебро', 'min_lifetime_spend' => 5000, 'cashback_percent' => 3, 'sort_order' => 1]);

    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::forCustomer($customer);

    $account->update(['lifetime_spent_on_orders' => 5500]);
    $account->update(['lifetime_spent_on_orders' => 6000]);
    $account->update(['lifetime_spent_on_orders' => 8000]);

    expect($customer->notifications()->where('type', NotificationType::LoyaltyLevelUp)->count())->toBe(1);
});
