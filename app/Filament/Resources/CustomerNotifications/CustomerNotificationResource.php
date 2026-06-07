<?php

namespace App\Filament\Resources\CustomerNotifications;

use App\Filament\Resources\CustomerNotifications\Pages\CreateCustomerNotification;
use App\Filament\Resources\CustomerNotifications\Pages\EditCustomerNotification;
use App\Filament\Resources\CustomerNotifications\Pages\ListCustomerNotifications;
use App\Filament\Resources\CustomerNotifications\Schemas\CustomerNotificationForm;
use App\Filament\Resources\CustomerNotifications\Tables\CustomerNotificationsTable;
use App\Models\CustomerNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerNotificationResource extends Resource
{
    protected static ?string $model = CustomerNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'Уведомления';

    protected static ?string $modelLabel = 'Уведомление';

    protected static ?string $pluralModelLabel = 'Уведомления';

    protected static string|\UnitEnum|null $navigationGroup = 'Клиенты';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CustomerNotificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerNotificationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerNotifications::route('/'),
            'create' => CreateCustomerNotification::route('/create'),
            'edit' => EditCustomerNotification::route('/{record}/edit'),
        ];
    }
}
