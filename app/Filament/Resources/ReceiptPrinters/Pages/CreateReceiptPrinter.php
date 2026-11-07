<?php

namespace App\Filament\Resources\ReceiptPrinters\Pages;

use App\Enums\PrinterType;
use App\Filament\Resources\ReceiptPrinters\ReceiptPrinterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceiptPrinter extends CreateRecord
{
    protected static string $resource = ReceiptPrinterResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'type' => PrinterType::Receipt];
    }
}
