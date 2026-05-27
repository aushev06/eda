<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('number')
                    ->label('№')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('delivery_type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (DeliveryType $state) => $state->label())
                    ->color(fn (DeliveryType $state) => $state->color()),
                TextColumn::make('table_number_snapshot')
                    ->label('Стол')
                    ->placeholder('—')
                    ->badge()
                    ->color('success')
                    ->toggleable(),
                TextColumn::make('customer_name')
                    ->label('Клиент')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Поз.')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('payment_method')
                    ->label('Оплата')
                    ->formatStateUsing(fn (PaymentMethod $state) => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m H:i')
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(OrderStatus::options()),
                SelectFilter::make('delivery_type')
                    ->label('Тип доставки')
                    ->options(DeliveryType::options()),
                Filter::make('today')
                    ->label('Только за сегодня')
                    ->query(fn (Builder $query) => $query->whereDate('created_at', today())),
            ])
            ->recordActions([
                ViewAction::make()->label('Открыть'),
            ]);
    }
}
