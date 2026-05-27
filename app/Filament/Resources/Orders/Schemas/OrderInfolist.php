<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Заказ')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('number')
                            ->label('Номер')
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                            ->color(fn (OrderStatus $state) => $state->color()),
                        TextEntry::make('delivery_type')
                            ->label('Тип')
                            ->badge()
                            ->formatStateUsing(fn (DeliveryType $state) => $state->label())
                            ->color(fn (DeliveryType $state) => $state->color()),
                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i'),
                    ]),

                Section::make('Клиент')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer_name')->label('Имя'),
                        TextEntry::make('customer_phone')->label('Телефон')->copyable(),
                        TextEntry::make('customer_comment')
                            ->label('Комментарий')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Адрес доставки')
                    ->columns(3)
                    ->visible(fn (Order $record) => $record->delivery_type === DeliveryType::Delivery)
                    ->schema([
                        TextEntry::make('delivery_street')->label('Улица')->columnSpan(2),
                        TextEntry::make('delivery_apartment')->label('Кв.'),
                        TextEntry::make('delivery_entrance')->label('Подъезд'),
                        TextEntry::make('delivery_floor')->label('Этаж'),
                        TextEntry::make('delivery_intercom')->label('Домофон'),
                        TextEntry::make('deliveryZone.name')
                            ->label('Зона доставки')
                            ->placeholder('— не определена')
                            ->badge()
                            ->color(fn (Order $record) => $record->deliveryZone?->color ? null : 'gray'),
                        TextEntry::make('delivery_instructions')
                            ->label('Инструкции курьеру')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Заказ в зале')
                    ->columns(2)
                    ->visible(fn (Order $record) => $record->delivery_type === DeliveryType::DineIn)
                    ->schema([
                        TextEntry::make('table_number_snapshot')
                            ->label('Столик')
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(fn (?string $state) => $state ? 'Столик '.$state : '—'),
                        TextEntry::make('table.label')
                            ->label('Подпись')
                            ->placeholder('—'),
                    ]),

                Section::make('Оплата')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Способ')
                            ->formatStateUsing(fn (PaymentMethod $state) => $state->label()),
                        TextEntry::make('payment_status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                            ->color(fn (PaymentStatus $state) => $state->color()),
                        TextEntry::make('subtotal')->label('Товары')->money('RUB'),
                        TextEntry::make('modifiers_total')->label('Модификаторы')->money('RUB'),
                        TextEntry::make('delivery_fee')->label('Доставка')->money('RUB'),
                        TextEntry::make('discount_total')
                            ->label('Скидка')
                            ->money('RUB')
                            ->formatStateUsing(fn ($state, Order $record) => $record->promo_code
                                ? '−'.number_format((float) $state, 0, ',', ' ').' ₽ ('.$record->promo_code.')'
                                : number_format((float) $state, 0, ',', ' ').' ₽'),
                        TextEntry::make('bonus_used_amount')
                            ->label('Списано бонусов')
                            ->money('RUB')
                            ->visible(fn (Order $record) => (float) $record->bonus_used_amount > 0),
                        TextEntry::make('bonus_earned_amount')
                            ->label('Начислено бонусов')
                            ->money('RUB')
                            ->color('success')
                            ->visible(fn (Order $record) => (float) $record->bonus_earned_amount > 0),
                        TextEntry::make('total')->label('Итого')->money('RUB')->weight('bold')->size('lg')->columnSpan(2),
                    ]),

                Section::make('Хронология')
                    ->columns(4)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('accepted_at')->label('Принят')->dateTime('d.m H:i')->placeholder('—'),
                        TextEntry::make('ready_at')->label('Готов')->dateTime('d.m H:i')->placeholder('—'),
                        TextEntry::make('delivered_at')->label('Доставлен')->dateTime('d.m H:i')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label('Отменён')->dateTime('d.m H:i')->placeholder('—'),
                        TextEntry::make('cancellation_reason')
                            ->label('Причина отмены')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
