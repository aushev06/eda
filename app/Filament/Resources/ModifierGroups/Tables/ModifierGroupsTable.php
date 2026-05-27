<?php

namespace App\Filament\Resources\ModifierGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModifierGroupsTable
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
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('modifiers_count')
                    ->label('Опций')
                    ->counts('modifiers')
                    ->badge(),
                TextColumn::make('min_select')
                    ->label('Мин')
                    ->numeric(),
                TextColumn::make('max_select')
                    ->label('Макс')
                    ->numeric(),
                IconColumn::make('is_required')
                    ->label('Обязат.')
                    ->boolean(),
                TextColumn::make('products_count')
                    ->label('Блюд')
                    ->counts('products')
                    ->badge()
                    ->color('gray'),
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
