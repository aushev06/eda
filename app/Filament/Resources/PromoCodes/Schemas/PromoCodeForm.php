<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use App\Enums\PromoDiscountType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Код')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (string $state) => mb_strtoupper(trim($state)))
                            ->helperText('Например, WELCOME10. Сохранится заглавными буквами.'),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                        Textarea::make('description')
                            ->label('Описание')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Скидка')
                    ->columns(2)
                    ->schema([
                        Select::make('discount_type')
                            ->label('Тип')
                            ->options(PromoDiscountType::options())
                            ->default(PromoDiscountType::Percent->value)
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->label('Значение')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->suffix(fn (callable $get) => $get('discount_type') === PromoDiscountType::Percent->value ? '%' : '₽'),
                        TextInput::make('max_discount_amount')
                            ->label('Максимальная скидка')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->suffix('₽')
                            ->visible(fn (callable $get) => $get('discount_type') === PromoDiscountType::Percent->value)
                            ->helperText('Кэп для процентной скидки. Пусто — без ограничения.'),
                        TextInput::make('min_order_amount')
                            ->label('Мин. сумма заказа')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->suffix('₽')
                            ->default(0),
                    ]),

                Section::make('Условия')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Старт')
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Окончание')
                            ->seconds(false),
                        TextInput::make('max_uses_global')
                            ->label('Лимит использований всего')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Без лимита'),
                        TextInput::make('max_uses_per_customer')
                            ->label('Лимит на клиента')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Без лимита'),
                        Toggle::make('first_order_only')
                            ->label('Только на первый заказ')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
