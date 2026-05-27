import { Link, router, usePage } from '@inertiajs/react';
import { Gift, Hash, ShoppingBag, User, X } from 'lucide-react';
import { useCart } from '@/hooks/catalog/use-cart';
import type { Establishment } from '@/types/catalog';

type Props = {
    establishment: Establishment;
};

type SharedProps = {
    auth: { customer: { id: number; name: string } | null };
    table: { id: number; number: number; label: string | null } | null;
};

export function SiteHeader({ establishment }: Props) {
    const { count, open } = useCart();
    const { auth, table } = usePage<SharedProps>().props;
    const customer = auth.customer;

    function leaveTable() {
        router.post('/t/leave', {}, { preserveScroll: true });
    }

    return (
        <header className="sticky top-0 z-30 border-b border-stone-200/80 bg-white/85 backdrop-blur-md">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <div className="flex items-center gap-3">
                    <div className="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-lg font-bold text-white shadow-sm">
                        {establishment.name.slice(0, 1).toUpperCase()}
                    </div>
                    <div className="flex flex-col leading-tight">
                        <span className="text-base font-semibold text-stone-900">{establishment.name}</span>
                        {establishment.phone && (
                            <a href={`tel:${establishment.phone}`} className="text-xs text-stone-500 hover:text-stone-700">
                                {establishment.phone}
                            </a>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {table && (
                        <span
                            className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20"
                            title={table.label ? `${table.label}` : undefined}
                        >
                            <Hash className="size-3.5" />
                            Столик {table.number}
                            <button
                                type="button"
                                onClick={leaveTable}
                                className="ml-0.5 rounded-full text-emerald-600 hover:text-emerald-900"
                                aria-label="Выйти со стола"
                            >
                                <X className="size-3.5" />
                            </button>
                        </span>
                    )}

                    {establishment.accepting_orders ? (
                        <span className="hidden items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20 sm:inline-flex">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                            </span>
                            Принимаем заказы
                        </span>
                    ) : (
                        <span
                            className="hidden items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-rose-600/20 sm:inline-flex"
                            title={establishment.closed_reason ?? undefined}
                        >
                            <span className="inline-flex size-2 rounded-full bg-rose-500" />
                            Закрыто
                        </span>
                    )}

                    <Link
                        href="/loyalty"
                        className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100"
                        title="Программа лояльности"
                    >
                        <Gift className="size-4" />
                        <span className="hidden sm:inline">Бонусы</span>
                    </Link>

                    {customer ? (
                        <Link
                            href="/account"
                            className="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-200"
                        >
                            <User className="size-4" />
                            <span className="hidden sm:inline">{customer.name}</span>
                        </Link>
                    ) : (
                        <Link
                            href="/account/login"
                            className="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-200"
                        >
                            <User className="size-4" />
                            <span className="hidden sm:inline">Войти</span>
                        </Link>
                    )}

                    <button
                        type="button"
                        onClick={open}
                        className="relative inline-flex items-center gap-2 rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
                    >
                        <ShoppingBag className="size-4" />
                        <span className="hidden sm:inline">Корзина</span>
                        {count > 0 && (
                            <span className="grid h-5 min-w-5 place-items-center rounded-full bg-amber-400 px-1.5 text-xs font-bold text-stone-900">
                                {count}
                            </span>
                        )}
                    </button>
                </div>
            </div>
        </header>
    );
}
