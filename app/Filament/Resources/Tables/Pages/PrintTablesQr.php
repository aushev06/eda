<?php

namespace App\Filament\Resources\Tables\Pages;

use App\Filament\Resources\Tables\TableResource;
use App\Models\Table as TableModel;
use App\Support\Settings;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class PrintTablesQr extends Page
{
    protected static string $resource = TableResource::class;

    protected string $view = 'filament.resources.tables.print-qr';

    public function getTitle(): string
    {
        return 'Печать QR-кодов';
    }

    /**
     * @return Collection<int, TableModel>
     */
    public function getTables(): Collection
    {
        return TableModel::query()->active()->orderBy('number')->get();
    }

    public function getEstablishmentName(): string
    {
        return (string) Settings::get('general.name', 'Заведение');
    }
}
