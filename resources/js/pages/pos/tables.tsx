import { Head, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useState } from 'react';

type FloorTable = {
    id: number;
    number: number;
    label: string | null;
    check: {
        id: number;
        total: number;
        opened_at: string | null;
    } | null;
};

type PageProps = {
    tables: FloorTable[];
    establishment_name: string | null;
};

function formatPrice(value: number): string {
    return `${value.toLocaleString('ru-RU')} ₽`;
}

function openMinutes(openedAt: string | null, now: Date): number | null {
    if (!openedAt) {
        return null;
    }

    return Math.max(0, Math.floor((now.getTime() - new Date(openedAt).getTime()) / 60_000));
}

export default function PosTables() {
    const { tables, establishment_name } = usePage<PageProps>().props;
    const [now, setNow] = useState(() => new Date());

    // Any check change (open, add, close) refreshes the floor.
    useEcho('pos.tickets', 'PosTicketsUpdated', () => router.reload({ only: ['tables'] }));

    useEffect(() => {
        const ticker = setInterval(() => setNow(new Date()), 30_000);

        return () => clearInterval(ticker);
    }, []);

    function openTable(table: FloorTable) {
        router.get(`/pos/tables/${table.id}`);
    }

    return (
        <div className="flex h-screen flex-col bg-zinc-100 text-zinc-900 select-none">
            <Head title="Залы" />

            <header className="flex items-center justify-between gap-2 border-b border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-600">
                <span className="truncate font-bold tracking-wide text-zinc-900 uppercase">{establishment_name ?? 'Залы'}</span>
                <nav className="flex shrink-0 items-center gap-2">
                    <a href="/pos" className="rounded-lg border-2 border-zinc-400 px-3 py-1.5 font-semibold text-zinc-700 sm:px-4">
                        Касса
                    </a>
                    <span className="rounded-lg border-2 border-zinc-900 bg-zinc-900 px-3 py-1.5 font-semibold text-white sm:px-4">Залы</span>
                    <a href="/pos/kitchen" className="hidden px-2 py-1 underline-offset-2 hover:underline sm:inline">
                        Кухня
                    </a>
                    <a href="/pos/bar" className="hidden px-2 py-1 underline-offset-2 hover:underline sm:inline">
                        Бар
                    </a>
                </nav>
            </header>

            <main className="grid flex-1 auto-rows-min grid-cols-2 gap-3 overflow-y-auto p-3 sm:grid-cols-3 sm:p-4 md:grid-cols-4 lg:grid-cols-6">
                {tables.map((table) => {
                    const occupied = table.check !== null;
                    const minutes = occupied ? openMinutes(table.check!.opened_at, now) : null;

                    return (
                        <button
                            key={table.id}
                            type="button"
                            onClick={() => openTable(table)}
                            className={`flex h-32 flex-col items-center justify-center rounded-2xl border-3 p-3 transition-transform active:scale-95 ${
                                occupied ? 'border-emerald-500 bg-emerald-50' : 'border-zinc-300 bg-white'
                            }`}
                        >
                            <span className="text-2xl font-bold">{table.label ?? table.number}</span>
                            {occupied ? (
                                <>
                                    <span className="mt-1 text-lg font-semibold tabular-nums">{formatPrice(table.check!.total)}</span>
                                    {minutes !== null && <span className="text-xs text-zinc-500 tabular-nums">{minutes} мин</span>}
                                </>
                            ) : (
                                <span className="mt-1 text-sm text-zinc-400">Свободен</span>
                            )}
                        </button>
                    );
                })}
                {tables.length === 0 && (
                    <p className="col-span-full mt-16 text-center text-lg text-zinc-500">Нет активных столов</p>
                )}
            </main>
        </div>
    );
}
