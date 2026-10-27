<?php

namespace App\Filament\Pages;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Settings as SettingsHelper;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class OnlinePayments extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.online-payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Онлайн-оплата';

    protected static ?string $title = 'Онлайн-оплата';

    protected static ?int $navigationSort = 101;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(PaymentGatewayManager $gateways): void
    {
        $credentials = [];
        foreach ($gateways->gateways() as $key => $class) {
            foreach (array_keys($class::credentialFields()) as $field) {
                $credentials[$key][$field] = SettingsHelper::get("payments.{$key}.{$field}", '');
            }
        }

        $this->form->fill([
            'enabled' => (bool) SettingsHelper::get('payments.enabled', false),
            'gateway' => SettingsHelper::get('payments.gateway'),
            'test_mode' => (bool) SettingsHelper::get('payments.test_mode', false),
            'credentials' => $credentials,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $gateways = app(PaymentGatewayManager::class);

        return $schema
            ->components([
                Section::make('Приём оплаты')
                    ->description('Когда оплата включена и выбранный сервис настроен, на сайте появляется способ «Карта онлайн». После оформления клиент переходит на страницу оплаты банка, а заказ отмечается оплаченным, когда банк подтвердит платёж.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Принимать оплату картой онлайн')
                            ->columnSpanFull(),
                        Select::make('gateway')
                            ->label('Платёжный сервис')
                            ->options($gateways->options())
                            ->required(fn (callable $get) => (bool) $get('enabled'))
                            ->live(),
                        Toggle::make('test_mode')
                            ->label('Тестовый режим')
                            ->helperText('Для Альфа-Банка и Сбербанка запросы идут на тестовый сервер банка. Для ЮKassa и Т-Банка укажите данные тестового магазина или терминала.')
                            ->inline(false),
                    ]),
                ...collect($gateways->gateways())
                    ->map(fn (string $class, string $key) => $this->gatewaySection($key, $class))
                    ->values()
                    ->all(),
            ])
            ->statePath('data');
    }

    /**
     * @param  class-string<PaymentGateway>  $class
     */
    protected function gatewaySection(string $key, string $class): Section
    {
        $fields = collect($class::credentialFields())
            ->map(function (array $field, string $name) use ($key) {
                $input = TextInput::make("credentials.{$key}.{$name}")
                    ->label($field['label'])
                    ->placeholder($field['placeholder'] ?? null)
                    ->helperText($field['help'] ?? null)
                    ->maxLength(255);

                return ($field['secret'] ?? false) ? $input->password()->revealable() : $input;
            })
            ->values()
            ->all();

        return Section::make($class::label())
            ->columns(2)
            ->visible(fn (callable $get) => $get('gateway') === $key)
            ->schema([
                ...$fields,
                Text::make('Адрес для уведомлений (webhook): '.route('payments.webhook', ['gateway' => $key]))
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $gateways = app(PaymentGatewayManager::class);

        $values = [
            'payments.enabled' => (bool) ($state['enabled'] ?? false),
            'payments.gateway' => $state['gateway'] ?? null,
            'payments.test_mode' => (bool) ($state['test_mode'] ?? false),
        ];

        foreach ($gateways->gateways() as $key => $class) {
            foreach (array_keys($class::credentialFields()) as $field) {
                $value = $state['credentials'][$key][$field] ?? null;
                $values["payments.{$key}.{$field}"] = is_string($value) ? trim($value) : $value;
            }
        }

        SettingsHelper::setMany($values);

        if ($values['payments.enabled'] && ! $gateways->isOnlinePaymentAvailable()) {
            Notification::make()
                ->title('Настройки сохранены, но оплата на сайте не появится')
                ->body('Заполните обязательные данные выбранного сервиса.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()->title('Настройки сохранены')->success()->send();
    }
}
