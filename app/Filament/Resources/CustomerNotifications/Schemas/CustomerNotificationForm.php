<?php

namespace App\Filament\Resources\CustomerNotifications\Schemas;

use App\Enums\NotificationType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Получатель')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Клиент')
                            ->relationship('customer', 'phone')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->name ?? '').' '.($record->phone ?? '')))
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Содержимое')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Тип')
                            ->options(collect(NotificationType::cases())
                                ->mapWithKeys(fn (NotificationType $t) => [$t->value => $t->label()])
                                ->all())
                            ->default(NotificationType::Promo->value)
                            ->required(),
                        TextInput::make('action_url')
                            ->label('Ссылка действия')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Например, /loyalty или абсолютный URL.'),
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('Текст')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
