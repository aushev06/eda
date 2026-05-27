<?php

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Settings::flush();
});

it('returns the default when a key has no value', function () {
    expect(Settings::get('general.name'))->toBe('Моё заведение')
        ->and(Settings::isAcceptingOrders())->toBeTrue();
});

it('persists a value and caches it', function () {
    Settings::set('general.name', 'Test Cafe');

    expect(Settings::get('general.name'))->toBe('Test Cafe')
        ->and(Setting::where('key', 'general.name')->value('value'))->toBe('Test Cafe');
});

it('flushes the cache when a setting is changed', function () {
    Settings::set('mode.accepting_orders', false);
    expect(Settings::isAcceptingOrders())->toBeFalse();

    Settings::set('mode.accepting_orders', true);
    expect(Settings::isAcceptingOrders())->toBeTrue();
});

it('setMany updates multiple keys at once', function () {
    Settings::setMany([
        'general.name' => 'Bistro',
        'general.phone' => '+7 999 123 45 67',
    ]);

    expect(Settings::get('general.name'))->toBe('Bistro')
        ->and(Settings::get('general.phone'))->toBe('+7 999 123 45 67');
});

it('renders the settings page', function () {
    $this->get('/admin/settings')->assertSuccessful();
});

it('saves the form data into settings and working hours', function () {
    Livewire::test(SettingsPage::class)
        ->set('data.general.name', 'Fidele Cafe')
        ->set('data.general.phone', '+7 495 123 45 67')
        ->set('data.mode.accepting_orders', false)
        ->set('data.mode.closed_reason', 'Технический перерыв')
        ->set('data.orders.min_order_amount', 1000)
        ->set('data.working_hours.1.opens_at', '09:00')
        ->set('data.working_hours.1.closes_at', '23:00')
        ->set('data.working_hours.1.is_closed', false)
        ->call('save')
        ->assertHasNoFormErrors();

    Cache::forget(Settings::CACHE_KEY);

    expect(Settings::get('general.name'))->toBe('Fidele Cafe')
        ->and(Settings::isAcceptingOrders())->toBeFalse()
        ->and(Settings::closedReason())->toBe('Технический перерыв')
        ->and((float) Settings::get('orders.min_order_amount'))->toBe(1000.0);

    $monday = WorkingHour::where('day_of_week', 1)->first();
    expect($monday)->not->toBeNull()
        ->and($monday->opens_at->format('H:i'))->toBe('09:00')
        ->and($monday->closes_at->format('H:i'))->toBe('23:00');
});

it('renders the topbar mode indicator as accepting by default', function () {
    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Принимаем заказы');
});

it('renders the topbar mode indicator as closed when disabled', function () {
    Settings::set('mode.accepting_orders', false);
    Settings::set('mode.closed_reason', 'Технический перерыв');

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Заказы закрыты');
});
