<?php

namespace App\Filament\Resources\ReceiptPrinters\Schemas;

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ReceiptPrinterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('type', PrinterType::Receipt->value),
                            )
                            ->validationMessages(['unique' => 'Принтер с таким названием уже создан.']),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->inline(false),
                        TextInput::make('comments')
                            ->label('Комментарий')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Подключение')
                    ->columns(3)
                    ->schema([
                        Select::make('connection_type')
                            ->label('Тип подключения')
                            ->options(PrinterConnectionType::options())
                            ->default(PrinterConnectionType::Network->value)
                            ->required()
                            ->native(false)
                            ->live(),
                        TextInput::make('address')
                            ->label('Адрес')
                            ->maxLength(255)
                            ->required(fn (callable $get) => self::connection($get) !== PrinterConnectionType::Usb)
                            ->helperText(fn (callable $get) => self::connection($get)?->addressHint()),
                        TextInput::make('port')
                            ->label('Порт')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(9100)
                            ->required(fn (callable $get) => self::connection($get) === PrinterConnectionType::Network)
                            ->visible(fn (callable $get) => self::connection($get) === PrinterConnectionType::Network),
                        Select::make('baud_rate')
                            ->label('Скорость порта')
                            ->options(array_combine($rates = [1200, 2400, 4800, 9600, 19200, 38400, 57600, 115200], $rates))
                            ->default(115200)
                            ->required(fn (callable $get) => self::connection($get) === PrinterConnectionType::Com)
                            ->visible(fn (callable $get) => self::connection($get) === PrinterConnectionType::Com),
                        Toggle::make('persist_connection')
                            ->label('Держать соединение открытым')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Печать')
                    ->columns(2)
                    ->schema([
                        Select::make('tape_width')
                            ->label('Ширина ленты')
                            ->options([58 => '58 мм', 80 => '80 мм'])
                            ->default(80)
                            ->required()
                            ->native(false),
                        Toggle::make('print_images')
                            ->label('Печатать изображения (логотип, QR)')
                            ->default(true)
                            ->inline(false),
                        Toggle::make('send_beep')
                            ->label('Звуковой сигнал после печати')
                            ->default(false),
                    ]),

                Section::make('Тонкая настройка')
                    ->description('Параметры передачи данных. Меняйте, только если принтер теряет часть чека.')
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextInput::make('chunk_size')
                            ->label('Размер пакета')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(1460)
                            ->suffix('байт')
                            ->required(),
                        TextInput::make('chunks_delay_ms')
                            ->label('Пауза между пакетами')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(10000)
                            ->default(25)
                            ->suffix('мс')
                            ->required(),
                        TextInput::make('tasks_delay_ms')
                            ->label('Пауза между заданиями')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(30000)
                            ->default(1000)
                            ->suffix('мс')
                            ->required(),
                    ]),
            ]);
    }

    private static function connection(callable $get): ?PrinterConnectionType
    {
        $value = $get('connection_type');

        return $value instanceof PrinterConnectionType ? $value : PrinterConnectionType::tryFrom((string) $value);
    }
}
