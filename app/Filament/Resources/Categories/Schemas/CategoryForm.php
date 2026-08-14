<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\Station;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                        if ($operation === 'create' && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('station')
                    ->label('Станция приготовления')
                    ->options(Station::options())
                    ->default(Station::Kitchen->value)
                    ->required()
                    ->native(false)
                    ->helperText('Куда уходят позиции этой категории на POS: кухня или бар.'),
                Toggle::make('is_active')
                    ->label('Активна')
                    ->default(true),
            ]);
    }
}
