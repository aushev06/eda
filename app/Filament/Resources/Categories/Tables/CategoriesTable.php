<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Enums\Station;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('products_count')
                    ->label('Блюд')
                    ->counts('products')
                    ->badge(),
                TextColumn::make('station')
                    ->label('Станция')
                    ->badge()
                    ->formatStateUsing(fn (Station $state): string => $state->label())
                    ->color(fn (Station $state): string => $state === Station::Bar ? 'info' : 'warning'),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
