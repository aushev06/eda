<?php

namespace App\Filament\Resources\LoyaltyLevels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LoyaltyLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                        TextInput::make('min_lifetime_spend')
                            ->label('Минимальный оборот')
                            ->numeric()
                            ->minValue(0)
                            ->step(100)
                            ->suffix('₽')
                            ->required()
                            ->helperText('Сумма заказов клиента (lifetime), при которой действует уровень.'),
                        TextInput::make('cashback_percent')
                            ->label('Кэшбек')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->suffix('%')
                            ->required(),
                    ]),
            ]);
    }
}
