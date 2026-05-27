@php
    use App\Support\Settings;
    $accepting = Settings::isAcceptingOrders();
    $reason = Settings::closedReason();
@endphp

<div class="flex items-center gap-2 px-3">
    @if ($accepting)
        <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/30">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
            </span>
            Принимаем заказы
        </span>
    @else
        <span
            class="inline-flex items-center gap-1.5 rounded-full bg-danger-50 px-3 py-1 text-xs font-medium text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-500/30"
            @if ($reason) title="{{ $reason }}" @endif
        >
            <span class="inline-flex h-2 w-2 rounded-full bg-danger-500"></span>
            Заказы закрыты@if ($reason): {{ \Illuminate\Support\Str::limit($reason, 50) }}@endif
        </span>
    @endif
</div>
