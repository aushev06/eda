<?php

namespace App\Filament\Resources\Tables\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Номер')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->unique(ignoreRecord: true),
                        TextInput::make('label')
                            ->label('Подпись')
                            ->placeholder('У окна, бар, VIP')
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
