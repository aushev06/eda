<?php

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterType;
use App\Filament\Resources\ReceiptPrinters\Pages\CreateReceiptPrinter;
use App\Filament\Resources\ReceiptPrinters\Pages\EditReceiptPrinter;
use App\Filament\Resources\ReceiptPrinters\ReceiptPrinterResource;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->create();
});

it('renders the receipt printers list under equipment', function () {
    Printer::factory()->create(['name' => 'Касса у входа']);

    $this->actingAs($this->staff)
        ->get(ReceiptPrinterResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Оборудование')
        ->assertSee('Касса у входа');
});

it('creates a network receipt printer from the admin form', function () {
    $this->actingAs($this->staff);

    Livewire::test(CreateReceiptPrinter::class)
        ->fillForm([
            'name' => 'Касса 1',
            'connection_type' => PrinterConnectionType::Network->value,
            'address' => '192.168.1.50',
            'port' => 9100,
            'tape_width' => 58,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $printer = Printer::sole();

    expect($printer->type)->toBe(PrinterType::Receipt)
        ->and($printer->connection_type)->toBe(PrinterConnectionType::Network)
        ->and($printer->address)->toBe('192.168.1.50')
        ->and($printer->port)->toBe(9100)
        ->and($printer->tape_width)->toBe(58)
        ->and($printer->chunk_size)->toBe(1460);
});

it('requires an address for network printers and a unique name', function () {
    $this->actingAs($this->staff);
    Printer::factory()->create(['name' => 'Касса 1']);

    Livewire::test(CreateReceiptPrinter::class)
        ->fillForm([
            'name' => 'Касса 1',
            'connection_type' => PrinterConnectionType::Network->value,
            'address' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique', 'address' => 'required']);
});

it('drops the port when a printer is switched to bluetooth', function () {
    $this->actingAs($this->staff);
    $printer = Printer::factory()->create(['port' => 9100]);

    Livewire::test(EditReceiptPrinter::class, ['record' => $printer->getRouteKey()])
        ->fillForm([
            'connection_type' => PrinterConnectionType::Bluetooth->value,
            'address' => '00:11:22:33:44:55',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($printer->refresh())
        ->connection_type->toBe(PrinterConnectionType::Bluetooth)
        ->address->toBe('00:11:22:33:44:55')
        ->port->toBeNull();
});

it('serves active receipt printers to the pos app', function () {
    Sanctum::actingAs($this->staff);
    $printer = Printer::factory()->create(['name' => 'Касса', 'address' => '10.0.0.5']);
    Printer::factory()->inactive()->create(['name' => 'Старая касса']);

    $this->getJson(route('api.pos.printers'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'receipt_printers')
        ->assertJsonPath('receipt_printers.0.id', $printer->id)
        ->assertJsonPath('receipt_printers.0.connection_type', 'tcp')
        ->assertJsonPath('receipt_printers.0.address', '10.0.0.5')
        ->assertJsonPath('receipt_printers.0.port', 9100)
        ->assertJsonPath('receipt_printers.0.tape_width', 80);
});

it('requires a token for the printers endpoint', function () {
    $this->getJson(route('api.pos.printers'))->assertUnauthorized();
});
