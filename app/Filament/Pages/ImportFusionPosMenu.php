<?php

namespace App\Filament\Pages;

use App\Support\FusionPosMenuImporter;
use App\Support\ProductImportResult;
use App\Support\Settings as SettingsHelper;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ImportFusionPosMenu extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.import-fusion-pos-menu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;

    protected static ?string $navigationLabel = 'Импорт из FusionPOS';

    protected static ?string $title = 'Загрузка меню из FusionPOS';

    protected static string|\UnitEnum|null $navigationGroup = 'Меню';

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'url' => SettingsHelper::get('fusionpos.url', ''),
            'token' => SettingsHelper::get('fusionpos.token', ''),
            'activate_new' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подключение')
                    ->description('Адрес и токен сохраняются при загрузке.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('url')
                            ->label('Адрес API')
                            ->placeholder('https://example.fusionpos.ru')
                            ->url()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('token')
                            ->label('Токен')
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Импорт')
                    ->description('Загружаются категории, блюда, группы модификаторов с вариантами и их привязка к блюдам. Позиции сопоставляются по ID FusionPOS, а при первой загрузке — по названию. У найденных блюд обновляются цена и доступность, а название, фото и категория остаются как на сайте. Блюда, удалённые или выключенные в FusionPOS, скрываются с сайта. Группы модификаторов, созданные вручную на сайте, не трогаются.')
                    ->schema([
                        Toggle::make('activate_new')
                            ->label('Сразу показывать новые позиции на сайте')
                            ->helperText('Если выключено — новые блюда создаются скрытыми, чтобы сначала добавить фото и разложить по категориям.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $state = $this->form->getState();

        SettingsHelper::setMany([
            'fusionpos.url' => rtrim($state['url'], '/'),
            'fusionpos.token' => $state['token'],
        ]);

        try {
            $result = app(FusionPosMenuImporter::class)->import((bool) ($state['activate_new'] ?? false));
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Не удалось получить меню из FusionPOS')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->notifyResult($result);
    }

    private function notifyResult(ProductImportResult $result): void
    {
        $body = "Блюд создано: {$result->created}, обновлено: {$result->updated}, скрыто: {$result->deactivated}, пропущено: {$result->skipped}. Групп модификаторов: {$result->modifierGroups}, вариантов: {$result->modifiers}.";

        $notification = Notification::make()
            ->title($result->total() > 0 ? 'Меню загружено' : 'Ничего не загружено');

        if ($result->hasErrors()) {
            $shown = array_slice($result->errors, 0, 5);
            $extra = count($result->errors) - count($shown);
            $body .= ' Ошибки: '.implode(' ', $shown);

            if ($extra > 0) {
                $body .= " И ещё {$extra}.";
            }

            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->body($body)->send();
    }
}
