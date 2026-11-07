<?php

use App\Actions\Pos\BuildKdsTickets;
use App\Actions\Pos\OpenGuestOrdering;
use App\Actions\Pos\OpenTableCheck;
use App\Enums\Station;
use App\Events\PosTicketsUpdated;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->from = Table::factory()->create(['number' => 3]);
    $this->to = Table::factory()->create(['number' => 5]);
    $this->sandwich = Product::factory()->for(Category::factory())->create(['price' => 350]);

    Sanctum::actingAs($this->staff);
});

function openCheckAt(Table $table): Order
{
    return app(OpenTableCheck::class)->handle($table, test()->staff);
}

function moveCheck(Order $order, Table $table)
{
    return test()->postJson(route('api.pos.orders.move', $order), ['table_id' => $table->id]);
}

it('moves an open check to a free table with its items', function () {
    $order = openCheckAt($this->from);
    $this->postJson(route('api.pos.orders.items', $order), [
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 2]],
    ])->assertSuccessful();

    moveCheck($order, $this->to)
        ->assertSuccessful()
        ->assertJsonPath('table.id', $this->to->id)
        ->assertJsonPath('check.id', $order->id)
        ->assertJsonCount(1, 'check.items');

    expect($order->refresh())
        ->table_id->toBe($this->to->id)
        ->table_number_snapshot->toBe('5')
        ->and(Order::query()->openTableCheck($this->from->id)->exists())->toBeFalse();

    // Opening the old table now starts a fresh check, the moved one stays put.
    expect(openCheckAt($this->from)->id)->not->toBe($order->id)
        ->and(openCheckAt($this->to)->id)->toBe($order->id);
});

it('shows the new table number on kitchen tickets', function () {
    $order = openCheckAt($this->from);
    $this->postJson(route('api.pos.orders.items', $order), [
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1]],
    ])->assertSuccessful();

    Event::fake([PosTicketsUpdated::class]);
    moveCheck($order, $this->to)->assertSuccessful();

    Event::assertDispatched(PosTicketsUpdated::class);
    expect(app(BuildKdsTickets::class)->handle(Station::Kitchen)[0]['table_number'])->toBe('5');
});

it('refuses to move onto an occupied table', function () {
    $order = openCheckAt($this->from);
    $other = openCheckAt($this->to);

    moveCheck($order, $this->to)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['table_id' => 'Стол 5 занят.']);

    expect($order->refresh()->table_id)->toBe($this->from->id)
        ->and($other->refresh()->table_id)->toBe($this->to->id);
});

it('refuses to move onto the same or an inactive table', function () {
    $order = openCheckAt($this->from);
    $inactive = Table::factory()->create(['number' => 9, 'is_active' => false]);

    moveCheck($order, $this->from)->assertJsonValidationErrors('table_id');
    moveCheck($order, $inactive)->assertJsonValidationErrors('table_id');

    expect($order->refresh()->table_id)->toBe($this->from->id);
});

it('refuses to move a paid check', function () {
    $order = openCheckAt($this->from);
    $this->postJson(route('api.pos.orders.items', $order), [
        'items' => [['product_id' => $this->sandwich->id, 'quantity' => 1]],
    ])->assertSuccessful();
    $this->postJson(route('api.pos.orders.close', $order), [
        'tenders' => [['method' => 'cash', 'amount' => 350, 'received_amount' => 350]],
    ])->assertSuccessful();

    moveCheck($order, $this->to)->assertJsonValidationErrors('order');
});

it('closes phone ordering when the check moves', function () {
    $order = app(OpenGuestOrdering::class)->handle(openCheckAt($this->from));
    $guest = $order->guests()->create(['name' => 'Аня']);

    moveCheck($order, $this->to)->assertSuccessful();

    expect($order->refresh()->guest_pin)->toBeNull()
        ->and($guest->refresh()->revoked_at)->not->toBeNull();
});

it('validates the target table', function () {
    $order = openCheckAt($this->from);

    $this->postJson(route('api.pos.orders.move', $order), ['table_id' => 999999])
        ->assertJsonValidationErrors('table_id');
});
