<?php

namespace App\Filament\Pages;

use App\Models\WorkingHour;
use App\Support\Settings as SettingsHelper;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $title = 'Настройки заведения';

    protected static ?int $navigationSort = 100;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $hours = WorkingHour::query()->get()->keyBy('day_of_week');

        $workingHours = [];

        for ($day = 1; $day <= 7; $day++) {
            /** @var WorkingHour|null $row */
            $row = $hours->get($day);

            $workingHours[$day] = [
                'opens_at' => $row?->opens_at?->format('H:i') ?? '10:00',
                'closes_at' => $row?->closes_at?->format('H:i') ?? '22:00',
                'is_closed' => (bool) ($row?->is_closed ?? false),
            ];
        }

        $defaults = SettingsHelper::defaults();

        $this->form->fill([
            'general' => [
                'name' => SettingsHelper::get('general.name', $defaults['general.name']),
                'phone' => SettingsHelper::get('general.phone', $defaults['general.phone']),
                'email' => SettingsHelper::get('general.email', $defaults['general.email']),
                'address' => SettingsHelper::get('general.address', $defaults['general.address']),
            ],
            'mode' => [
                'accepting_orders' => (bool) SettingsHelper::get('mode.accepting_orders', true),
                'closed_reason' => SettingsHelper::get('mode.closed_reason'),
            ],
            'orders' => [
                'min_order_amount' => (float) SettingsHelper::get('orders.min_order_amount', 0),
                'min_delivery_amount' => (float) SettingsHelper::get('orders.min_delivery_amount', 0),
                'base_prep_minutes' => (int) SettingsHelper::get('orders.base_prep_minutes', 30),
                'delivery_radius_km' => (int) SettingsHelper::get('orders.delivery_radius_km', 10),
            ],
            'social' => [
                'telegram' => SettingsHelper::get('social.telegram', ''),
                'vk' => SettingsHelper::get('social.vk', ''),
                'instagram' => SettingsHelper::get('social.instagram', ''),
            ],
            'working_hours' => $workingHours,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Контакты')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                TextInput::make('general.name')->label('Название')->required()->maxLength(255),
                                TextInput::make('general.phone')->label('Телефон')->tel()->maxLength(64),
                                TextInput::make('general.email')->label('Email')->email()->maxLength(255),
                                Textarea::make('general.address')->label('Адрес')->rows(2)->columnSpanFull(),
                            ]),

                        Tab::make('Режим работы')
                            ->icon('heroicon-o-power')
                            ->schema([
                                Toggle::make('mode.accepting_orders')
                                    ->label('Принимаем заказы')
                                    ->helperText('Главный рубильник: если выключен — клиенты увидят сообщение «закрыто», а оператор всё равно может работать с уже принятыми заказами.')
                                    ->live(),
                                Textarea::make('mode.closed_reason')
                                    ->label('Причина закрытия')
                                    ->placeholder('Например: технический перерыв до 14:00')
                                    ->rows(2)
                                    ->visible(fn (callable $get) => ! $get('mode.accepting_orders')),
                            ]),

                        Tab::make('Параметры заказов')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('orders.min_order_amount')
                                        ->label('Мин. сумма заказа')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(1)
                                        ->suffix('₽'),
                                    TextInput::make('orders.min_delivery_amount')
                                        ->label('Мин. сумма для доставки')
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(1)
                                        ->suffix('₽'),
                                    TextInput::make('orders.base_prep_minutes')
                                        ->label('Базовое время готовки')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('мин'),
                                    TextInput::make('orders.delivery_radius_km')
                                        ->label('Радиус доставки')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('км'),
                                ]),
                            ]),

                        Tab::make('График работы')
                            ->icon('heroicon-o-clock')
                            ->schema(
                                collect(WorkingHour::DAY_LABELS)
                                    ->map(fn (string $label, int $day) => Section::make($label)
                                        ->compact()
                                        ->columns(3)
                                        ->schema([
                                            Toggle::make("working_hours.$day.is_closed")
                                                ->label('Выходной')
                                                ->live(),
                                            TimePicker::make("working_hours.$day.opens_at")
                                                ->label('Открытие')
                                                ->seconds(false)
                                                ->disabled(fn (callable $get) => (bool) $get("working_hours.$day.is_closed")),
                                            TimePicker::make("working_hours.$day.closes_at")
                                                ->label('Закрытие')
                                                ->seconds(false)
                                                ->disabled(fn (callable $get) => (bool) $get("working_hours.$day.is_closed")),
                                        ]))
                                    ->values()
                                    ->all()
                            ),

                        Tab::make('Соцсети')
                            ->icon('heroicon-o-link')
                            ->schema([
                                TextInput::make('social.telegram')->label('Telegram')->prefix('@')->maxLength(255),
                                TextInput::make('social.vk')->label('VK')->url()->maxLength(255),
                                TextInput::make('social.instagram')->label('Instagram')->prefix('@')->maxLength(255),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $flat = [];
        foreach (['general', 'mode', 'orders', 'social'] as $group) {
            foreach (($data[$group] ?? []) as $key => $value) {
                $flat["$group.$key"] = $value;
            }
        }

        SettingsHelper::setMany($flat);

        foreach (($data['working_hours'] ?? []) as $day => $row) {
            WorkingHour::updateOrCreate(
                ['day_of_week' => (int) $day],
                [
                    'opens_at' => $row['opens_at'] ?? null,
                    'closes_at' => $row['closes_at'] ?? null,
                    'is_closed' => (bool) ($row['is_closed'] ?? false),
                ],
            );
        }

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
