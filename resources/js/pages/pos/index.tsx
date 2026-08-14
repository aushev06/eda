import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import PaymentScreen from '@/components/pos/payment-screen';

type PosModifier = {
    id: number;
    name: string;
    price_delta: number;
};

type PosModifierGroup = {
    id: number;
    name: string;
    min_select: number;
    max_select: number;
    modifiers: PosModifier[];
};

type PosProduct = {
    id: number;
    name: string;
    price: number;
    station: 'kitchen' | 'bar';
    modifier_groups: PosModifierGroup[];
};

type PosCategory = {
    id: number;
    name: string;
    products: PosProduct[];
};

type PosTable = {
    id: number;
    number: number;
    label: string | null;
};

type CartLine = {
    key: string;
    product: PosProduct;
    quantity: number;
    modifierIds: number[];
};

type PageProps = {
    categories: PosCategory[];
    tables: PosTable[];
    establishment_name: string | null;
    auth: { user: { name: string } | null };
    flash: { placed_order_number?: string | null };
};

const STATION_LABELS: Record<string, string> = {
    kitchen: 'КУХНЯ',
    bar: 'БАР',
};

function formatPrice(value: number): string {
    return `${value.toLocaleString('ru-RU')} ₽`;
}

function lineModifiers(line: CartLine): PosModifier[] {
    return line.product.modifier_groups
        .flatMap((group) => group.modifiers)
        .filter((modifier) => line.modifierIds.includes(modifier.id));
}

function lineTotal(line: CartLine): number {
    const perUnitDelta = lineModifiers(line).reduce((sum, modifier) => sum + modifier.price_delta, 0);

    return (line.product.price + perUnitDelta) * line.quantity;
}

/** Stable key so the same product with the same modifier set merges into one line. */
function cartKey(productId: number, modifierIds: number[]): string {
    return `${productId}:${[...modifierIds].sort((a, b) => a - b).join(',')}`;
}

/** Self-dismissing confirmation toast; remounted via key on every new order number. */
function ConfirmationToast({ number }: { number: string }) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => setVisible(false), 4000);

        return () => clearTimeout(timer);
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 rounded-xl bg-emerald-600 px-6 py-3 text-base font-semibold text-white shadow-lg">
            Заказ {number} отправлен на станции
        </div>
    );
}

export default function PosIndex() {
    const { categories, tables, establishment_name, auth, flash } = usePage<PageProps>().props;

    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(categories[0]?.id ?? null);
    const [cart, setCart] = useState<CartLine[]>([]);
    const [dineIn, setDineIn] = useState(false);
    const [tableId, setTableId] = useState<number | null>(null);
    const [modifierTarget, setModifierTarget] = useState<{ product: PosProduct; selected: number[]; editKey?: string } | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [paymentTotal, setPaymentTotal] = useState<number | null>(null);
    const [cartOpen, setCartOpen] = useState(false);
    const [clock, setClock] = useState(() => new Date());

    const cartCount = cart.reduce((sum, line) => sum + line.quantity, 0);

    useEffect(() => {
        const timer = setInterval(() => setClock(new Date()), 30_000);

        return () => clearInterval(timer);
    }, []);

    const activeCategory = useMemo(
        () => categories.find((category) => category.id === activeCategoryId) ?? categories[0],
        [categories, activeCategoryId],
    );

    const total = cart.reduce((sum, line) => sum + lineTotal(line), 0);

    function addProduct(product: PosProduct) {
        const requiresChoice = product.modifier_groups.some((group) => group.min_select > 0 || group.modifiers.length > 0);

        if (requiresChoice) {
            setModifierTarget({ product, selected: [] });

            return;
        }

        addLine(product, []);
    }

    function addLine(product: PosProduct, modifierIds: number[]) {
        const key = cartKey(product.id, modifierIds);
        setCart((current) => {
            const existing = current.find((line) => line.key === key);

            if (existing) {
                return current.map((line) => (line.key === key ? { ...line, quantity: line.quantity + 1 } : line));
            }

            return [...current, { key, product, quantity: 1, modifierIds }];
        });
    }

    function changeQuantity(key: string, delta: number) {
        setCart((current) =>
            current
                .map((line) => (line.key === key ? { ...line, quantity: line.quantity + delta } : line))
                .filter((line) => line.quantity > 0),
        );
    }

    function applyModifierSelection() {
        if (!modifierTarget) {
            return;
        }

        const { product, selected, editKey } = modifierTarget;

        for (const group of product.modifier_groups) {
            const count = group.modifiers.filter((modifier) => selected.includes(modifier.id)).length;

            if (count < group.min_select || count > group.max_select) {
                return;
            }
        }

        if (editKey) {
            setCart((current) => {
                const edited = current.find((line) => line.key === editKey);

                if (!edited) {
                    return current;
                }

                const withoutOld = current.filter((line) => line.key !== editKey);
                const newKey = cartKey(product.id, selected);
                const merged = withoutOld.find((line) => line.key === newKey);

                if (merged) {
                    return withoutOld.map((line) =>
                        line.key === newKey ? { ...line, quantity: line.quantity + edited.quantity } : line,
                    );
                }

                return [...withoutOld, { ...edited, key: newKey, modifierIds: selected }];
            });
        } else {
            addLine(product, selected);
        }

        setModifierTarget(null);
    }

    function modifierSelectionValid(): boolean {
        if (!modifierTarget) {
            return false;
        }

        return modifierTarget.product.modifier_groups.every((group) => {
            const count = group.modifiers.filter((modifier) => modifierTarget.selected.includes(modifier.id)).length;

            return count >= group.min_select && count <= group.max_select;
        });
    }

    function toggleModifier(group: PosModifierGroup, modifier: PosModifier) {
        if (!modifierTarget) {
            return;
        }

        const selected = new Set(modifierTarget.selected);

        if (selected.has(modifier.id)) {
            selected.delete(modifier.id);
        } else {
            const selectedInGroup = group.modifiers.filter((m) => selected.has(m.id));

            // Single-choice groups swap the selection instead of blocking the tap.
            if (selectedInGroup.length >= group.max_select && group.max_select === 1) {
                selectedInGroup.forEach((m) => selected.delete(m.id));
            } else if (selectedInGroup.length >= group.max_select) {
                return;
            }

            selected.add(modifier.id);
        }

        setModifierTarget({ ...modifierTarget, selected: [...selected] });
    }

    const cartItems = () =>
        cart.map((line) => ({
            product_id: line.product.id,
            quantity: line.quantity,
            modifier_ids: line.modifierIds,
        }));

    async function openPayment() {
        if (cart.length === 0 || submitting || (dineIn && !tableId)) {
            return;
        }

        // Authoritative server total so the payment pad and settlement agree.
        const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''));
        const res = await fetch('/pos/quote', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token },
            body: JSON.stringify({ items: cartItems() }),
        });

        if (!res.ok) {
            return;
        }

        const data = await res.json();
        setPaymentTotal(data.total);
        setCartOpen(false);
    }

    function submitOrder(tenders: { method: string; amount: number; received_amount?: number }[]) {
        setSubmitting(true);
        router.post(
            '/pos/orders',
            {
                delivery_type: dineIn ? 'dine_in' : 'pickup',
                table_id: dineIn ? tableId : null,
                items: cartItems(),
                tenders,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCart([]);
                    setDineIn(false);
                    setTableId(null);
                    setPaymentTotal(null);
                    setCartOpen(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    const cartContent = (
        <>
            <div className="flex gap-2 border-b border-dashed border-zinc-300 p-3">
                <button
                    type="button"
                    onClick={() => {
                        setDineIn(false);
                        setTableId(null);
                    }}
                    className={`flex-1 rounded-full border-2 px-4 py-2.5 text-sm font-semibold ${
                        !dineIn ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-400 text-zinc-700'
                    }`}
                >
                    С собой
                </button>
                <button
                    type="button"
                    onClick={() => setDineIn(true)}
                    className={`flex-1 rounded-full border-2 px-4 py-2.5 text-sm font-semibold ${
                        dineIn ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-400 text-zinc-700'
                    }`}
                >
                    В зале
                </button>
            </div>

            {dineIn && (
                <div className="flex flex-wrap gap-1.5 border-b border-dashed border-zinc-300 p-3">
                    {tables.map((table) => (
                        <button
                            key={table.id}
                            type="button"
                            onClick={() => setTableId(table.id)}
                            className={`min-w-12 rounded-lg border-2 px-3 py-2.5 text-sm font-bold ${
                                tableId === table.id ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-400 text-zinc-700'
                            }`}
                        >
                            {table.label ?? table.number}
                        </button>
                    ))}
                    {tables.length === 0 && <span className="text-sm text-zinc-500">Нет активных столов</span>}
                </div>
            )}

            <div className="min-h-0 flex-1 overflow-y-auto p-3">
                {cart.length === 0 && <p className="mt-8 text-center text-sm text-zinc-400">Тапните по блюду, чтобы добавить</p>}
                {cart.map((line) => (
                    <div key={line.key} className="border-b border-zinc-200 py-2.5">
                        <button
                            type="button"
                            className="flex w-full items-baseline justify-between gap-2 text-left"
                            onClick={() =>
                                line.product.modifier_groups.length > 0 &&
                                setModifierTarget({ product: line.product, selected: line.modifierIds, editKey: line.key })
                            }
                        >
                            <span className="text-base font-semibold">
                                {line.product.name}{' '}
                                <span className="rounded border border-zinc-300 px-1 text-[10px] font-normal text-zinc-500 uppercase">
                                    {STATION_LABELS[line.product.station]}
                                </span>
                            </span>
                            <span className="text-base whitespace-nowrap tabular-nums">{formatPrice(lineTotal(line))}</span>
                        </button>
                        {lineModifiers(line).length > 0 && (
                            <div className="mt-0.5 pl-3 text-xs text-zinc-500">
                                {lineModifiers(line)
                                    .map((modifier) =>
                                        modifier.price_delta > 0 ? `+ ${modifier.name} (${formatPrice(modifier.price_delta)})` : `+ ${modifier.name}`,
                                    )
                                    .join(', ')}
                            </div>
                        )}
                        <div className="mt-1.5 inline-flex overflow-hidden rounded-lg border-2 border-zinc-400">
                            <button type="button" onClick={() => changeQuantity(line.key, -1)} className="h-11 w-12 text-xl font-bold active:bg-zinc-200">
                                −
                            </button>
                            <span className="flex h-11 w-11 items-center justify-center border-x-2 border-zinc-400 text-base font-semibold tabular-nums">
                                {line.quantity}
                            </span>
                            <button type="button" onClick={() => changeQuantity(line.key, 1)} className="h-11 w-12 text-xl font-bold active:bg-zinc-200">
                                +
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex items-baseline justify-between border-t-2 border-zinc-300 px-3 pt-2.5 text-xl font-bold">
                <span>Итого</span>
                <span className="tabular-nums">{formatPrice(total)}</span>
            </div>
            <button
                type="button"
                onClick={openPayment}
                disabled={cart.length === 0 || submitting || (dineIn && !tableId)}
                className="m-3 h-16 rounded-xl bg-zinc-900 text-lg font-bold text-white transition-opacity disabled:opacity-30"
            >
                {dineIn && !tableId ? 'Выберите стол' : `К оплате · ${formatPrice(total)}`}
            </button>
        </>
    );

    return (
        <div className="flex h-screen flex-col bg-zinc-100 text-zinc-900 select-none">
            <Head title="Касса" />

            {/* Header */}
            <header className="flex items-center justify-between gap-2 border-b border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-600">
                <span className="truncate font-bold tracking-wide text-zinc-900 uppercase">{establishment_name ?? 'Касса'}</span>
                <div className="flex shrink-0 items-center gap-2 sm:gap-4">
                    <a href="/pos/tables" className="rounded-lg border-2 border-zinc-400 px-3 py-1.5 font-semibold text-zinc-700">
                        Залы
                    </a>
                    <a href="/pos/kitchen" className="hidden rounded px-2 py-1 underline-offset-2 hover:underline sm:inline">
                        Кухня
                    </a>
                    <a href="/pos/bar" className="hidden rounded px-2 py-1 underline-offset-2 hover:underline sm:inline">
                        Бар
                    </a>
                    <span className="hidden md:inline">{auth.user?.name}</span>
                    <span className="hidden tabular-nums sm:inline">
                        {clock.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}
                    </span>
                </div>
            </header>

            <div className="flex min-h-0 flex-1">
                {/* Product catalog */}
                <main className="flex min-w-0 flex-1 flex-col p-3 lg:flex-[1.8]">
                    <nav className="mb-3 flex gap-2 overflow-x-auto pb-1">
                        {categories.map((category) => (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() => setActiveCategoryId(category.id)}
                                className={`rounded-full border-2 px-5 py-3 text-base font-semibold whitespace-nowrap transition-colors ${
                                    category.id === activeCategory?.id
                                        ? 'border-zinc-900 bg-zinc-900 text-white'
                                        : 'border-zinc-400 bg-white text-zinc-700'
                                }`}
                            >
                                {category.name}
                            </button>
                        ))}
                    </nav>

                    <div className="grid flex-1 auto-rows-min grid-cols-2 gap-2 overflow-y-auto pb-24 sm:grid-cols-3 lg:pb-0 xl:grid-cols-4">
                        {activeCategory?.products.map((product) => (
                            <button
                                key={product.id}
                                type="button"
                                onClick={() => addProduct(product)}
                                className="flex h-24 flex-col justify-between rounded-xl border-2 border-zinc-300 bg-white p-3 text-left transition-transform active:scale-95"
                            >
                                <span className="line-clamp-2 text-base leading-tight font-semibold">{product.name}</span>
                                <span className="text-sm text-zinc-500">{formatPrice(product.price)}</span>
                            </button>
                        ))}
                    </div>
                </main>

                {/* Cart — desktop sidebar */}
                <aside className="hidden w-[360px] shrink-0 flex-col border-l-2 border-zinc-300 bg-white lg:flex">{cartContent}</aside>
            </div>

            {/* Cart — mobile sticky bar + bottom sheet */}
            {cart.length > 0 && (
                <button
                    type="button"
                    onClick={() => setCartOpen(true)}
                    className="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between bg-zinc-900 px-5 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] text-white lg:hidden"
                >
                    <span className="flex items-center gap-2 text-base font-semibold">
                        <span className="flex h-7 min-w-7 items-center justify-center rounded-full bg-white px-1.5 text-sm font-bold text-zinc-900">
                            {cartCount}
                        </span>
                        Корзина
                    </span>
                    <span className="text-lg font-bold tabular-nums">{formatPrice(total)}</span>
                </button>
            )}

            {cartOpen && (
                <div className="fixed inset-0 z-40 flex flex-col justify-end bg-black/40 lg:hidden" onClick={() => setCartOpen(false)}>
                    <div className="flex max-h-[88vh] flex-col rounded-t-2xl bg-white" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 pt-3 pb-1">
                            <span className="text-base font-bold">Заказ</span>
                            <button type="button" onClick={() => setCartOpen(false)} className="px-2 py-1 text-2xl leading-none text-zinc-400">
                                ×
                            </button>
                        </div>
                        {cartContent}
                    </div>
                </div>
            )}

            {/* Payment pad */}
            {paymentTotal !== null && (
                <PaymentScreen
                    total={paymentTotal}
                    busy={submitting}
                    onSettle={(tenders) => submitOrder(tenders)}
                    onCancel={() => setPaymentTotal(null)}
                />
            )}

            {/* Order confirmation toast */}
            {flash.placed_order_number && <ConfirmationToast key={flash.placed_order_number} number={flash.placed_order_number} />}

            {/* Modifier picker */}
            {modifierTarget && (
                <div
                    className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
                    onClick={() => setModifierTarget(null)}
                >
                    <div
                        className="flex max-h-[85vh] w-full max-w-md flex-col rounded-t-2xl bg-white p-4 sm:rounded-2xl"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <h2 className="mb-3 text-lg font-bold">{modifierTarget.product.name}</h2>
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {modifierTarget.product.modifier_groups.map((group) => (
                                <div key={group.id} className="mb-4">
                                    <p className="mb-1.5 text-sm font-semibold text-zinc-600">
                                        {group.name}
                                        {group.min_select > 0 && <span className="text-red-600"> *</span>}
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {group.modifiers.map((modifier) => {
                                            const active = modifierTarget.selected.includes(modifier.id);

                                            return (
                                                <button
                                                    key={modifier.id}
                                                    type="button"
                                                    onClick={() => toggleModifier(group, modifier)}
                                                    className={`rounded-full border-2 px-3.5 py-2.5 text-sm font-medium ${
                                                        active ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-300 text-zinc-700'
                                                    }`}
                                                >
                                                    {modifier.name}
                                                    {modifier.price_delta > 0 && ` +${formatPrice(modifier.price_delta)}`}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div className="mt-2 flex gap-2">
                            <button
                                type="button"
                                onClick={() => setModifierTarget(null)}
                                className="h-13 flex-1 rounded-xl border-2 border-zinc-300 text-base font-semibold"
                            >
                                Отмена
                            </button>
                            <button
                                type="button"
                                onClick={applyModifierSelection}
                                disabled={!modifierSelectionValid()}
                                className="h-13 flex-1 rounded-xl bg-zinc-900 text-base font-semibold text-white disabled:opacity-30"
                            >
                                {modifierTarget.editKey ? 'Сохранить' : 'В заказ'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
