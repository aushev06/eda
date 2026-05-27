<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Order $order */
        $order = $this->getRecord();
        $transitioner = app(TransitionOrderStatus::class);

        $actions = [];

        foreach ($transitioner->allowedNext($order->status) as $next) {
            $actions[] = $this->buildTransitionAction($next, $transitioner);
        }

        return $actions;
    }

    protected function buildTransitionAction(OrderStatus $next, TransitionOrderStatus $transitioner): Action
    {
        $needsReason = $next === OrderStatus::Cancelled;

        $action = Action::make('transition_'.$next->value)
            ->label($this->actionLabel($next))
            ->color($next->color())
            ->icon($this->actionIcon($next))
            ->requiresConfirmation();

        if ($needsReason) {
            $action = $action
                ->schema([
                    Textarea::make('reason')
                        ->label('Причина отмены')
                        ->required()
                        ->rows(3),
                ]);
        }

        return $action->action(function (array $data) use ($next, $transitioner) {
            /** @var Order $order */
            $order = $this->getRecord();

            $transitioner->handle(
                order: $order,
                to: $next,
                actor: auth()->user(),
                note: $data['reason'] ?? null,
            );

            Notification::make()
                ->title('Статус обновлён')
                ->body("Заказ {$order->number} — {$next->label()}")
                ->success()
                ->send();

            $this->refreshFormData(['status']);
        });
    }

    protected function actionLabel(OrderStatus $next): string
    {
        return match ($next) {
            OrderStatus::Accepted => 'Принять',
            OrderStatus::Preparing => 'Готовится',
            OrderStatus::Ready => 'Готов',
            OrderStatus::Delivering => 'В путь',
            OrderStatus::Delivered => 'Доставлен',
            OrderStatus::Cancelled => 'Отменить',
            default => $next->label(),
        };
    }

    protected function actionIcon(OrderStatus $next): string
    {
        return match ($next) {
            OrderStatus::Accepted => 'heroicon-o-check',
            OrderStatus::Preparing => 'heroicon-o-fire',
            OrderStatus::Ready => 'heroicon-o-sparkles',
            OrderStatus::Delivering => 'heroicon-o-truck',
            OrderStatus::Delivered => 'heroicon-o-check-circle',
            OrderStatus::Cancelled => 'heroicon-o-x-mark',
            default => 'heroicon-o-arrow-right',
        };
    }
}
