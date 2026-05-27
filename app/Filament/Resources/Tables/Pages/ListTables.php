<?php

namespace App\Filament\Resources\Tables\Pages;

use App\Filament\Resources\Tables\TableResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTables extends ListRecords
{
    protected static string $resource = TableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Печать QR')
                ->icon('heroicon-o-printer')
                ->url(static::getResource()::getUrl('print'))
                ->openUrlInNewTab(),
            CreateAction::make(),
        ];
    }
}
