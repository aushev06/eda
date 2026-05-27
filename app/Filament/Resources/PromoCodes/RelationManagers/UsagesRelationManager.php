<?php

namespace App\Filament\Resources\PromoCodes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Использования';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('customer.name')
                    ->label('Клиент')
                    ->placeholder('—'),
                TextColumn::make('customer.phone')
                    ->label('Телефон')
                    ->placeholder('—'),
                TextColumn::make('order.number')
                    ->label('Заказ')
                    ->placeholder('—')
                    ->badge(),
                TextColumn::make('discount_amount')
                    ->label('Скидка')
                    ->money('RUB'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
