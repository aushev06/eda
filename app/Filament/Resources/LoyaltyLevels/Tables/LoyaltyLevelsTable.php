<?php

namespace App\Filament\Resources\LoyaltyLevels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoyaltyLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('min_lifetime_spend')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('min_lifetime_spend')
                    ->label('Порог')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('cashback_percent')
                    ->label('Кэшбек')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').' %')
                    ->badge()
                    ->color('success'),
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
