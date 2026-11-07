<?php

namespace App\Filament\Resources\ReceiptPrinters;

use App\Enums\PrinterType;
use App\Filament\Resources\ReceiptPrinters\Pages\CreateReceiptPrinter;
use App\Filament\Resources\ReceiptPrinters\Pages\EditReceiptPrinter;
use App\Filament\Resources\ReceiptPrinters\Pages\ListReceiptPrinters;
use App\Filament\Resources\ReceiptPrinters\Schemas\ReceiptPrinterForm;
use App\Filament\Resources\ReceiptPrinters\Tables\ReceiptPrintersTable;
use App\Models\Printer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «Оборудование → Чековые принтеры»: printers of type Receipt only.
 */
class ReceiptPrinterResource extends Resource
{
    protected static ?string $model = Printer::class;

    protected static ?string $slug = 'equipment/receipt-printers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $navigationLabel = 'Чековые принтеры';

    protected static ?string $modelLabel = 'Чековый принтер';

    protected static ?string $pluralModelLabel = 'Чековые принтеры';

    protected static string|\UnitEnum|null $navigationGroup = 'Оборудование';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->ofType(PrinterType::Receipt);
    }

    public static function form(Schema $schema): Schema
    {
        return ReceiptPrinterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptPrintersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceiptPrinters::route('/'),
            'create' => CreateReceiptPrinter::route('/create'),
            'edit' => EditReceiptPrinter::route('/{record}/edit'),
        ];
    }
}
