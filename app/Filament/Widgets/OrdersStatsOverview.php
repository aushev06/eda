<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdersStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $newCount = Order::query()->where('status', OrderStatus::New)->count();

        $inProgressCount = Order::query()
            ->whereIn('status', [
                OrderStatus::Accepted->value,
                OrderStatus::Preparing->value,
            ])
            ->count();

        $readyCount = Order::query()
            ->whereIn('status', [
                OrderStatus::Ready->value,
                OrderStatus::Delivering->value,
            ])
            ->count();

        $revenueToday = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereDate('delivered_at', today())
            ->sum('total');

        return [
            Stat::make('Новые заказы', $newCount)
                ->description('Ждут принятия')
                ->color($newCount > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-bell-alert'),

            Stat::make('В работе', $inProgressCount)
                ->description('Принят / готовится')
                ->color('info')
                ->icon('heroicon-o-fire'),

            Stat::make('Готовы / в пути', $readyCount)
                ->description('Можно выдавать / везут')
                ->color('success')
                ->icon('heroicon-o-truck'),

            Stat::make('Выручка за сегодня', number_format((float) $revenueToday, 0, ',', ' ').' ₽')
                ->description('Только доставленные')
                ->color('success')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
