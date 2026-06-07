<?php

namespace App\Filament\Resources\CustomerNotifications\Tables;

use App\Enums\NotificationType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('customer.phone')
                    ->label('Клиент')
                    ->description(fn ($record) => $record->customer?->name)
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof NotificationType ? $state->label() : (string) $state),
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->limit(40)
                    ->searchable(),
                IconColumn::make('read_at')
                    ->label('Прочитано')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->read_at !== null),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options(collect(NotificationType::cases())
                        ->mapWithKeys(fn (NotificationType $t) => [$t->value => $t->label()])
                        ->all()),
                TernaryFilter::make('read_at')
                    ->label('Прочитано')
                    ->placeholder('Все')
                    ->trueLabel('Прочитанные')
                    ->falseLabel('Непрочитанные')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('read_at'),
                        false: fn (Builder $q) => $q->whereNull('read_at'),
                    ),
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
