<?php

namespace App\Filament\Resources\Tables\Tables;

use App\Models\Table as TableModel;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')
                    ->label('№')
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('label')
                    ->label('Подпись')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('qr_token')
                    ->label('QR-ссылка')
                    ->formatStateUsing(fn (TableModel $record) => url('/t/'.$record->qr_token))
                    ->copyable()
                    ->copyMessage('Ссылка скопирована')
                    ->limit(40),
                TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->counts('orders')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Активность'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('open_qr')
                    ->label('QR')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (TableModel $record) => 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.urlencode(url('/t/'.$record->qr_token)))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
