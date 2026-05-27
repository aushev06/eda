<?php

namespace App\Filament\Resources\ModifierGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModifierGroupForm
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
                            ->maxLength(255)
                            ->helperText('Например: «Размер», «Соус», «Добавки»'),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('min_select')
                            ->label('Минимум выбора')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('max_select')
                            ->label('Максимум выбора')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('1 = радио (один вариант), >1 = чекбоксы'),
                        Toggle::make('is_required')
                            ->label('Обязательная группа')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
