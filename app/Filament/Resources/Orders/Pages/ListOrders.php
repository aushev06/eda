<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Все'),
            'active' => Tab::make('В работе')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::New->value,
                    OrderStatus::Accepted->value,
                    OrderStatus::Preparing->value,
                ]))
                ->badge(fn () => self::getResource()::getModel()::query()
                    ->whereIn('status', [
                        OrderStatus::New->value,
                        OrderStatus::Accepted->value,
                        OrderStatus::Preparing->value,
                    ])->count()),
            'new' => Tab::make('Новые')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::New))
                ->badge(fn () => self::getResource()::getModel()::query()->where('status', OrderStatus::New)->count())
                ->badgeColor('warning'),
            'ready' => Tab::make('Готовы / в пути')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::Ready->value,
                    OrderStatus::Delivering->value,
                ])),
            'done' => Tab::make('Завершены')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::Delivered->value,
                    OrderStatus::Cancelled->value,
                ])),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }
}
