<x-filament-panels::page>
    @php
        $tables = $this->getTables();
        $establishment = $this->getEstablishmentName();
    @endphp

    <div class="mb-4 flex items-center justify-between gap-3 print:hidden">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Нажмите «Печать» — каждый QR на отдельной карточке. Распечатайте, разрежьте и наклейте на столики.
        </p>
        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
        >
            Печать
        </button>
    </div>

    @if ($tables->isEmpty())
        <p class="text-sm text-gray-500">Активных столиков нет. Добавьте их сначала.</p>
    @else
        <div class="grid grid-cols-1 gap-4 print:gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tables as $table)
                <div class="break-inside-avoid rounded-2xl border border-gray-200 bg-white p-6 text-center print:border-gray-400 print:break-inside-avoid">
                    <p class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ $establishment }}</p>
                    <p class="mt-1 text-4xl font-bold text-gray-900">Столик {{ $table->number }}</p>
                    @if ($table->label)
                        <p class="mt-0.5 text-sm text-gray-500">{{ $table->label }}</p>
                    @endif
                    <img
                        src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=8&data={{ urlencode(url('/t/'.$table->qr_token)) }}"
                        alt="QR код для столика {{ $table->number }}"
                        class="mx-auto mt-4 size-56"
                    />
                    <p class="mt-3 text-xs text-gray-500">Отсканируйте, чтобы заказать</p>
                    <p class="mt-1 break-all text-[10px] font-mono text-gray-400">
                        {{ url('/t/'.$table->qr_token) }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    <style>
        @media print {
            @page { margin: 1cm; }
            body { background: white !important; }
            aside, header, nav, .fi-sidebar, .fi-topbar { display: none !important; }
            .fi-main { padding: 0 !important; }
        }
    </style>
</x-filament-panels::page>
