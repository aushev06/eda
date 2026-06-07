<?php

namespace App\Filament\Pages;

use App\Support\ProductCsvImporter;
use App\Support\ProductImportResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read Schema $form
 */
class ImportProducts extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.import-products';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Импорт из CSV';

    protected static ?string $title = 'Импорт продуктов из CSV';

    protected static string|\UnitEnum|null $navigationGroup = 'Меню';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Файл CSV')
                    ->description('Три колонки: категория, название, цена. Первая строка-заголовок и пустые строки пропускаются. Разделитель — запятая или точка с запятой, кодировка UTF-8 или Windows-1251.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('CSV-файл')
                            ->required()
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/csv',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('imports')
                            ->helperText('Существующие категории дополняются, новые создаются автоматически. Повторная загрузка обновляет цены, а не плодит дубли.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Скачать шаблон')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadTemplate()),
        ];
    }

    public function import(): void
    {
        $path = $this->form->getState()['file'] ?? null;

        if (! is_string($path) || $path === '') {
            Notification::make()
                ->title('Выберите CSV-файл')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(ProductCsvImporter::class)->importFile(Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->form->fill();

        $this->notifyResult($result);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $rows = [
            ['категория', 'название', 'цена'],
            ['Пицца', 'Маргарита', '499'],
            ['Пицца', 'Пепперони', '599'],
            ['Напитки', 'Кола 0.5', '120'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '\\');
            }

            fclose($handle);
        }, 'products-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function notifyResult(ProductImportResult $result): void
    {
        $body = "Создано: {$result->created}, обновлено: {$result->updated}, пропущено: {$result->skipped}.";

        $notification = Notification::make()
            ->title($result->total() > 0 ? 'Импорт завершён' : 'Ничего не импортировано')
            ->body($body);

        if ($result->hasErrors()) {
            $shown = array_slice($result->errors, 0, 5);
            $extra = count($result->errors) - count($shown);
            $body .= ' Ошибки: '.implode(' ', $shown);

            if ($extra > 0) {
                $body .= " И ещё {$extra}.";
            }

            $notification->body($body)->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }
}
