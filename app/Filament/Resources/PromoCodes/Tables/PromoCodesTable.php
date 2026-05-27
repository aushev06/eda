<?php

namespace App\Filament\Resources\PromoCodes\Tables;

use App\Enums\PromoDiscountType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromoCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('discount_type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (PromoDiscountType $state) => $state->label())
                    ->color(fn (PromoDiscountType $state) => $state->color()),
                TextColumn::make('value')
                    ->label('Значение')
                    ->formatStateUsing(fn ($state, $record) => $record->discount_type === PromoDiscountType::Percent
                        ? rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').'%'
                        : number_format((float) $state, 0, ',', ' ').' ₽'),
                TextColumn::make('min_order_amount')
                    ->label('Мин. заказ')
                    ->money('RUB'),
                TextColumn::make('usages_count')
                    ->label('Исп.')
                    ->counts('usages')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('ends_at')
                    ->label('До')
                    ->dateTime('d.m.Y')
                    ->placeholder('—'),
                IconColumn::make('first_order_only')
                    ->label('1-й')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Активность'),
                TernaryFilter::make('first_order_only')->label('Только первый заказ'),
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
