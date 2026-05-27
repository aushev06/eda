<?php

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryZoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Простой квадрат для тестов: lat 0..1, lng 0..1.
 *
 * @return array<int, array{0: float, 1: float}>
 */
function squarePolygon(): array
{
    return [
        [0.0, 0.0],
        [0.0, 1.0],
        [1.0, 1.0],
        [1.0, 0.0],
    ];
}

it('containsPoint returns true for a point strictly inside the polygon', function () {
    $zone = DeliveryZone::factory()->withPolygon(squarePolygon())->create();

    expect($zone->containsPoint(0.5, 0.5))->toBeTrue();
});

it('containsPoint returns false for a point outside the polygon', function () {
    $zone = DeliveryZone::factory()->withPolygon(squarePolygon())->create();

    expect($zone->containsPoint(2.0, 2.0))->toBeFalse()
        ->and($zone->containsPoint(-1.0, 0.5))->toBeFalse()
        ->and($zone->containsPoint(0.5, -0.5))->toBeFalse();
});

it('containsPoint returns false for a polygon with fewer than 3 vertices', function () {
    $zone = DeliveryZone::factory()->withPolygon([[0.0, 0.0], [1.0, 1.0]])->create();

    expect($zone->containsPoint(0.5, 0.5))->toBeFalse();
});

it('resolver returns the first matching active zone by sort_order', function () {
    DeliveryZone::factory()->withPolygon(squarePolygon())->create(['sort_order' => 5, 'name' => 'Большая']);
    DeliveryZone::factory()->withPolygon(squarePolygon())->create(['sort_order' => 1, 'name' => 'Приоритетная']);

    $zone = app(DeliveryZoneResolver::class)->resolve(0.5, 0.5);

    expect($zone)->not->toBeNull()
        ->and($zone->name)->toBe('Приоритетная');
});

it('resolver ignores inactive zones', function () {
    DeliveryZone::factory()->withPolygon(squarePolygon())->inactive()->create(['sort_order' => 1]);
    DeliveryZone::factory()->withPolygon(squarePolygon())->create(['sort_order' => 5, 'name' => 'Активная']);

    $zone = app(DeliveryZoneResolver::class)->resolve(0.5, 0.5);

    expect($zone?->name)->toBe('Активная');
});

it('resolver returns null when no zone covers the point', function () {
    DeliveryZone::factory()->withPolygon(squarePolygon())->create();

    expect(app(DeliveryZoneResolver::class)->resolve(99.0, 99.0))->toBeNull();
});

it('tariff returns a structured array', function () {
    $zone = DeliveryZone::factory()->create([
        'delivery_fee' => 250,
        'min_order_amount' => 1000,
        'estimated_minutes_min' => 40,
        'estimated_minutes_max' => 70,
    ]);

    expect($zone->tariff())->toBe([
        'fee' => 250.0,
        'min_order' => 1000.0,
        'min_minutes' => 40,
        'max_minutes' => 70,
    ]);
});

it('renders the delivery zones list page', function () {
    DeliveryZone::factory()->count(2)->create();

    $this->get(DeliveryZoneResource::getUrl('index'))->assertSuccessful();
});

it('creates a delivery zone via the form', function () {
    Livewire::test(CreateDeliveryZone::class)
        ->fillForm([
            'name' => 'Север',
            'color' => '#10b981',
            'polygon' => squarePolygon(),
            'delivery_fee' => 200,
            'min_order_amount' => 1000,
            'estimated_minutes_min' => 30,
            'estimated_minutes_max' => 60,
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DeliveryZone::where('name', 'Север')->exists())->toBeTrue();
});

it('rejects a polygon with fewer than 3 points on create', function () {
    Livewire::test(CreateDeliveryZone::class)
        ->fillForm([
            'name' => 'Битый',
            'color' => '#000000',
            'polygon' => [[0.0, 0.0], [1.0, 1.0]],
            'delivery_fee' => 100,
            'min_order_amount' => 500,
            'estimated_minutes_min' => 30,
            'estimated_minutes_max' => 60,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['polygon']);
});

it('order belongs to a delivery zone', function () {
    $zone = DeliveryZone::factory()->create(['name' => 'Центр']);
    $order = Order::factory()->create(['delivery_zone_id' => $zone->id]);

    expect($order->deliveryZone->is($zone))->toBeTrue();
});

it('renders the order view page with delivery zone name', function () {
    $zone = DeliveryZone::factory()->create(['name' => 'Центр']);
    $order = Order::factory()->create([
        'delivery_zone_id' => $zone->id,
        'delivery_type' => 'delivery',
    ]);

    $this->get(OrderResource::getUrl('view', ['record' => $order]))
        ->assertSuccessful()
        ->assertSee('Центр');
});

it('keeps the order when the delivery zone is deleted (SET NULL)', function () {
    $zone = DeliveryZone::factory()->create();
    $order = Order::factory()->create(['delivery_zone_id' => $zone->id]);

    $zone->delete();

    expect($order->fresh())
        ->not->toBeNull()
        ->and($order->fresh()->delivery_zone_id)->toBeNull();
});

it('editing a delivery zone preserves the polygon array', function () {
    $zone = DeliveryZone::factory()->withPolygon(squarePolygon())->create();

    Livewire::test(EditDeliveryZone::class, ['record' => $zone->getRouteKey()])
        ->fillForm(['name' => 'Обновлённая зона'])
        ->call('save')
        ->assertHasNoFormErrors();

    $zone->refresh();

    expect($zone->name)->toBe('Обновлённая зона')
        ->and($zone->polygon)->toEqualCanonicalizing(squarePolygon());
});
