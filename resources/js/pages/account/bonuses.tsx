import { Head, Link } from '@inertiajs/react';
import { ArrowDownCircle, ArrowUpCircle, BookOpen, RefreshCcw, Settings2, Sparkles } from 'lucide-react';
import { AccountLayout } from '@/components/account/account-layout';
import { formatRub } from '@/lib/format';

type Level = {
    id?: number;
    name: string;
    cashback_percent: number;
    min_lifetime_spend?: number;
};

type Transaction = {
    id: number;
    type: 'earn' | 'spend' | 'refund' | 'adjust';
    amount: string;
    balance_after: string;
    note: string | null;
    created_at: string;
    order?: { id: number; number: string } | null;
};

type Props = {
    account: {
        balance: number;
        lifetime_earned: number;
        lifetime_spent_on_orders: number;
    };
    current_level: Level | null;
    next_level: (Level & { min_lifetime_spend: number }) | null;
    progress: { percent: number; remaining: number } | null;
    all_levels: Array<{ id: number; name: string; min_lifetime_spend: string; cashback_percent: string }>;
    transactions: Transaction[];
};

const TYPE_META: Record<Transaction['type'], { label: string; icon: typeof ArrowUpCircle; color: string }> = {
    earn: { label: 'Начисление', icon: ArrowUpCircle, color: 'text-emerald-600' },
    spend: { label: 'Списание', icon: ArrowDownCircle, color: 'text-amber-600' },
    refund: { label: 'Возврат', icon: RefreshCcw, color: 'text-sky-600' },
    adjust: { label: 'Корректировка', icon: Settings2, color: 'text-stone-500' },
};

export default function AccountBonuses({
    account,
    current_level,
    next_level,
    progress,
    all_levels,
    transactions,
}: Props) {
    return (
        <>
            <Head title="Бонусы" />
            <AccountLayout title="Бонусы" active="bonuses">
                {/* Balance card */}
                <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 p-6 text-white shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-sm font-medium text-amber-50/90">Ваш бонусный счёт</p>
                            <p className="mt-1 text-4xl font-bold tracking-tight">{formatBonuses(account.balance)}</p>
                            <p className="mt-1 text-xs text-amber-50/80">1 бонус = 1 ₽</p>
                        </div>
                        <div className="grid size-12 place-items-center rounded-xl bg-white/15 backdrop-blur-sm">
                            <Sparkles className="size-6" />
                        </div>
                    </div>

                    {current_level && (
                        <div className="mt-4 flex items-baseline justify-between border-t border-white/20 pt-4">
                            <span className="text-sm text-amber-50/90">Уровень</span>
                            <span className="text-base font-semibold">
                                {current_level.name} · {current_level.cashback_percent}%
                            </span>
                        </div>
                    )}
                </div>

                {/* Progress to next level */}
                {next_level && progress && (
                    <div className="mt-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                        <div className="mb-2 flex items-baseline justify-between text-sm">
                            <span className="text-stone-600">
                                До уровня «{next_level.name}» — {next_level.cashback_percent}%
                            </span>
                            <span className="font-semibold">осталось {formatRub(progress.remaining)}</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-stone-100">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500 transition-all"
                                style={{ width: `${progress.percent}%` }}
                            />
                        </div>
                        <p className="mt-2 text-xs text-stone-500">
                            Накоплено заказов на {formatRub(account.lifetime_spent_on_orders)} из {formatRub(next_level.min_lifetime_spend)}
                        </p>
                    </div>
                )}

                {!next_level && current_level && (
                    <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <p className="text-sm font-semibold text-emerald-900">
                            Вы достигли максимального уровня — «{current_level.name}» ({current_level.cashback_percent}%).
                        </p>
                        <p className="mt-1 text-xs text-emerald-800">Кэшбек теперь начисляется по высшей ставке.</p>
                    </div>
                )}

                {/* Stats */}
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <Stat label="Всего начислено" value={formatRub(account.lifetime_earned)} />
                    <Stat label="Оборот заказов" value={formatRub(account.lifetime_spent_on_orders)} />
                </div>

                {/* All levels reference */}
                <div className="mt-6 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <div className="mb-3 flex items-baseline justify-between gap-3">
                        <h2 className="text-base font-semibold">Уровни программы</h2>
                        <Link
                            href="/loyalty"
                            className="inline-flex items-center gap-1 text-xs font-medium text-stone-500 hover:text-stone-900"
                        >
                            <BookOpen className="size-3.5" />
                            Подробнее о программе
                        </Link>
                    </div>
                    <ul className="space-y-2 text-sm">
                        {all_levels.map((level) => {
                            const isCurrent = current_level?.name === level.name;

                            return (
                                <li
                                    key={level.id}
                                    className={`flex items-center justify-between rounded-xl border px-3 py-2.5 ${
                                        isCurrent ? 'border-amber-400 bg-amber-50' : 'border-stone-100'
                                    }`}
                                >
                                    <span className="font-medium">
                                        {level.name}
                                        {isCurrent && (
                                            <span className="ml-2 rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-semibold text-white">
                                                ваш
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-stone-600">
                                        от {formatRub(level.min_lifetime_spend)} · {Number(level.cashback_percent)}%
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                </div>

                {/* Transactions */}
                <div className="mt-6 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-base font-semibold">История операций</h2>
                    {transactions.length === 0 ? (
                        <p className="text-sm text-stone-500">Здесь будут начисления и списания бонусов.</p>
                    ) : (
                        <ul className="divide-y divide-stone-100">
                            {transactions.map((tx) => {
                                const meta = TYPE_META[tx.type];
                                const Icon = meta.icon;
                                const amount = Number(tx.amount);

                                return (
                                    <li key={tx.id} className="flex items-start justify-between gap-3 py-3">
                                        <div className="flex gap-3">
                                            <Icon className={`mt-0.5 size-5 shrink-0 ${meta.color}`} />
                                            <div>
                                                <p className="text-sm font-medium">{meta.label}</p>
                                                <p className="text-xs text-stone-500">
                                                    {tx.note ?? (tx.order ? `Заказ ${tx.order.number}` : '—')}
                                                </p>
                                                <p className="mt-0.5 text-xs text-stone-400">
                                                    {new Date(tx.created_at).toLocaleString('ru-RU', {
                                                        day: '2-digit',
                                                        month: '2-digit',
                                                        year: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })}
                                                </p>
                                            </div>
                                        </div>
                                        <span className={`shrink-0 font-semibold ${amount >= 0 ? 'text-emerald-700' : 'text-stone-900'}`}>
                                            {amount >= 0 ? '+' : ''}
                                            {formatBonuses(amount)}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </AccountLayout>
        </>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <p className="text-xs text-stone-500">{label}</p>
            <p className="mt-1 text-xl font-semibold">{value}</p>
        </div>
    );
}

function formatBonuses(value: number): string {
    const formatted = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Math.abs(value));

    return `${formatted} б.`;
}
