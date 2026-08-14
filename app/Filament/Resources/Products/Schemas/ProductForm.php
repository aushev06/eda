<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\Station;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Категория')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
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
                        TextInput::make('price')
                            ->label('Цена')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('₽'),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Фото')
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText('JPG/PNG/WebP до 5 МБ. Квадратные снимки выглядят лучше всего.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Доп. информация')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('weight_grams')
                            ->label('Вес, г')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('calories')
                            ->label('Калории, ккал')
                            ->numeric()
                            ->minValue(0),
                    ]),

                Section::make('Модификаторы')
                    ->schema([
                        Select::make('modifierGroups')
                            ->label('Группы модификаторов')
                            ->multiple()
                            ->relationship('modifierGroups', 'name')
                            ->preload()
                            ->searchable()
                            ->helperText('Прикрепите группы (размер, добавки, соус и т.п.) к этому блюду.'),
                    ]),

                Section::make('Статус')
                    ->columns(3)
                    ->schema([
                        Select::make('station')
                            ->label('Станция (переопределение)')
                            ->options(Station::options())
                            ->placeholder('Как у категории')
                            ->native(false)
                            ->helperText('Оставьте пустым, чтобы блюдо уходило на станцию своей категории.'),
                        Toggle::make('is_active')
                            ->label('Активно')
                            ->default(true),
                        Toggle::make('in_stop_list')
                            ->label('В стоп-листе')
                            ->default(false),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
