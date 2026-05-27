<?php

namespace App\Filament\Resources\DeliveryZones\Schemas;

use App\Filament\Forms\Components\PolygonMap;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        ColorPicker::make('color')
                            ->label('Цвет на карте')
                            ->default('#3b82f6'),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0)
                            ->helperText('Меньше — выше приоритет. Для перекрывающихся зон выбирается первая.'),
                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true)
                            ->columnSpan(2),
                    ]),

                Section::make('Тариф')
                    ->columns(2)
                    ->schema([
                        TextInput::make('delivery_fee')
                            ->label('Стоимость доставки')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->suffix('₽')
                            ->required(),
                        TextInput::make('min_order_amount')
                            ->label('Мин. сумма заказа')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->suffix('₽')
                            ->required(),
                        TextInput::make('estimated_minutes_min')
                            ->label('Время доставки (от)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('мин')
                            ->required(),
                        TextInput::make('estimated_minutes_max')
                            ->label('Время доставки (до)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('мин')
                            ->required(),
                    ]),

                Section::make('Полигон зоны')
                    ->schema([
                        PolygonMap::make('polygon')
                            ->label('')
                            ->required()
                            ->rules(['array', 'min:3'])
                            ->helperText('Нарисуйте полигон на карте — он определяет границы зоны.'),
                    ]),
            ]);
    }
}
