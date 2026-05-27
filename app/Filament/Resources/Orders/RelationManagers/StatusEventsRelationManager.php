<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\OrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusEvents';

    protected static ?string $title = 'История статусов';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_status')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i:s'),
                TextColumn::make('from_status')
                    ->label('Откуда')
                    ->badge()
                    ->formatStateUsing(fn (?OrderStatus $state) => $state?->label() ?? '—')
                    ->color(fn (?OrderStatus $state) => $state?->color() ?? 'gray'),
                TextColumn::make('to_status')
                    ->label('Куда')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('changedBy.name')
                    ->label('Кто')
                    ->placeholder('система'),
                TextColumn::make('note')
                    ->label('Комментарий')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
