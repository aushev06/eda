import { Link } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag, Trash2, X } from 'lucide-react';
import { useEffect } from 'react';
import { useCart } from '@/hooks/catalog/use-cart';
import { formatRub } from '@/lib/format';
import type { Establishment } from '@/types/catalog';
import { ProductPlaceholder } from './product-placeholder';

type Props = {
    establishment: Establishment;
};

export function CartDrawer({ establishment }: Props) {
    const { lines, count, subtotal, isOpen, close, setQuantity, remove } = useCart();

    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') close();
        }
        if (isOpen) window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [isOpen, close]);

    const belowMin = establishment.min_order_amount > 0 && subtotal < establishment.min_order_amount;
    const remaining = Math.max(0, establishment.min_order_amount - subtotal);

    return (
        <>
            <div
                onClick={close}
                aria-hidden="true"
                className={`fixed inset-0 z-40 bg-stone-900/40 backdrop-blur-sm transition-opacity ${
                    isOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
                }`}
            />

            <aside
                aria-hidden={!isOpen}
                className={`fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl transition-transform duration-200 ease-out ${
                    isOpen ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <header className="flex items-center justify-between border-b border-stone-200 px-5 py-4">
                    <h2 className="text-lg font-semibold text-stone-900">Корзина</h2>
                    <button
                        type="button"
                        onClick={close}
                        className="grid size-9 place-items-center rounded-full text-stone-500 hover:bg-stone-100"
                        aria-label="Закрыть корзину"
                    >
                        <X className="size-5" />
                    </button>
                </header>

                {lines.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                        <ShoppingBag className="size-12 text-stone-300" />
                        <p className="text-sm text-stone-500">Корзина пуста</p>
                        <p className="text-xs text-stone-400">Добавьте что-нибудь из меню — оно вкусное.</p>
                    </div>
                ) : (
                    <ul className="flex-1 divide-y divide-stone-100 overflow-y-auto px-2 py-2">
                        {lines.map((line) => {
                            const unitTotal = line.unit_price + line.modifiers_total_per_unit;
                            return (
                                <li key={line.id} className="flex gap-3 px-3 py-3">
                                    {line.image_url ? (
                                        <img
                                            src={line.image_url}
                                            alt={line.product_name}
                                            className="size-14 shrink-0 rounded-xl object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <ProductPlaceholder
                                            name={line.product_name}
                                            className="size-14 shrink-0 rounded-xl [&_svg]:size-7"
                                        />
                                    )}
                                    <div className="flex flex-1 flex-col gap-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="text-sm font-medium text-stone-900">{line.product_name}</p>
                                            <button
                                                type="button"
                                                onClick={() => remove(line.id)}
                                                className="text-stone-400 hover:text-rose-600"
                                                aria-label="Удалить"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                        {line.modifiers.length > 0 && (
                                            <p className="text-xs text-stone-500">
                                                {line.modifiers.map((m) => m.name).join(', ')}
                                            </p>
                                        )}
                                        <div className="mt-1 flex items-center justify-between">
                                            <div className="inline-flex items-center rounded-full bg-stone-100">
                                                <button
                                                    type="button"
                                                    onClick={() => setQuantity(line.id, line.quantity - 1)}
                                                    className="grid size-8 place-items-center rounded-l-full text-stone-600 hover:bg-stone-200"
                                                    aria-label="Меньше"
                                                >
                                                    <Minus className="size-3" />
                                                </button>
                                                <span className="min-w-6 text-center text-xs font-semibold text-stone-900">
                                                    {line.quantity}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => setQuantity(line.id, line.quantity + 1)}
                                                    className="grid size-8 place-items-center rounded-r-full text-stone-600 hover:bg-stone-200"
                                                    aria-label="Больше"
                                                >
                                                    <Plus className="size-3" />
                                                </button>
                                            </div>
                                            <span className="text-sm font-semibold text-stone-900">
                                                {formatRub(unitTotal * line.quantity)}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}

                {lines.length > 0 && (
                    <footer className="space-y-3 border-t border-stone-200 bg-stone-50 p-5">
                        <div className="flex items-center justify-between text-sm text-stone-600">
                            <span>{count} {pluralize(count)}</span>
                            <span className="text-base font-semibold text-stone-900">{formatRub(subtotal)}</span>
                        </div>

                        {belowMin && (
                            <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
                                Добавьте ещё на {formatRub(remaining)} для минимального заказа{' '}
                                ({formatRub(establishment.min_order_amount)}).
                            </p>
                        )}

                        {!establishment.accepting_orders && (
                            <p className="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800 ring-1 ring-rose-200">
                                Сейчас мы не принимаем заказы.
                                {establishment.closed_reason ? ` ${establishment.closed_reason}` : ''}
                            </p>
                        )}

                        <Link
                            href="/checkout"
                            className={`block w-full rounded-full px-5 py-3 text-center text-sm font-semibold transition ${
                                belowMin || !establishment.accepting_orders
                                    ? 'pointer-events-none bg-stone-200 text-stone-400'
                                    : 'bg-stone-900 text-white hover:bg-stone-800'
                            }`}
                        >
                            Оформить заказ
                        </Link>
                    </footer>
                )}
            </aside>
        </>
    );
}

function pluralize(n: number): string {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return 'позиция';
    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'позиции';
    return 'позиций';
}
