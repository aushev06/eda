<?php

namespace App\Filament\Resources\DeliveryZones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ColorColumn::make('color')
                    ->label('Цвет'),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('polygon')
                    ->label('Точек')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->alignCenter(),
                TextColumn::make('delivery_fee')
                    ->label('Доставка')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('min_order_amount')
                    ->label('Мин. заказ')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('estimated_minutes_min')
                    ->label('Время')
                    ->formatStateUsing(fn ($state, $record) => "{$state}–{$record->estimated_minutes_max} мин"),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
