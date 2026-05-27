<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Состав заказа';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')
                    ->label('Блюдо')
                    ->description(fn ($record) => $record->modifiers
                        ->map(fn ($m) => $m->modifier_name.((float) $m->price_delta > 0 ? ' +'.number_format((float) $m->price_delta, 0, ',', ' ').' ₽' : ''))
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label('Кол-во')
                    ->alignCenter(),
                TextColumn::make('unit_price')
                    ->label('Цена')
                    ->money('RUB'),
                TextColumn::make('modifiers_total')
                    ->label('Δ модиф.')
                    ->money('RUB'),
                TextColumn::make('line_total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->weight('medium'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
