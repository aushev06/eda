<?php

namespace App\Filament\Resources\ReceiptPrinters\Tables;

use App\Enums\PrinterConnectionType;
use App\Models\Printer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReceiptPrintersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Printer $record) => $record->comments),
                TextColumn::make('connection_type')
                    ->label('Подключение')
                    ->badge()
                    ->formatStateUsing(fn (PrinterConnectionType $state) => $state->label()),
                TextColumn::make('address')
                    ->label('Адрес')
                    ->formatStateUsing(fn (?string $state, Printer $record) => $record->port ? "{$state}:{$record->port}" : $state)
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('tape_width')
                    ->label('Лента')
                    ->suffix(' мм')
                    ->alignCenter(),
                ToggleColumn::make('is_active')
                    ->label('Активен'),
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
