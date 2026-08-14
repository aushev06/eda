import { Head, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useMemo, useState } from 'react';
import PaymentScreen from '@/components/pos/payment-screen';

type Modifier = { id: number; name: string; price_delta: number };
type ModifierGroup = { id: number; name: string; min_select: number; max_select: number; modifiers: Modifier[] };
type Product = { id: number; name: string; price: number; station: 'kitchen' | 'bar'; modifier_groups: ModifierGroup[] };
type Category = { id: number; name: string; products: Product[] };

type CheckItem = {
    id: number;
    name: string;
    quantity: number;
    line_total: number;
    station: 'kitchen' | 'bar' | null;
    completed: boolean;
    voided: boolean;
    void_reason: string | null;
    modifiers: { name: string; price_delta: number }[];
};

type Check = {
    id: number;
    number: string;
    total: number;
    opened_at: string | null;
    items: CheckItem[];
};

type PageProps = {
    table: { id: number; number: number; label: string | null };
    categories: Category[];
    check: Check;
    payment_methods: { value: string; label: string }[];
};

type DraftLine = { key: string; product: Product; quantity: number; modifierIds: number[] };

const STATION_LABELS: Record<string, string> = { kitchen: 'КУХНЯ', bar: 'БАР' };

function formatPrice(value: number): string {
    return `${value.toLocaleString('ru-RU')} ₽`;
}

function draftKey(productId: number, modifierIds: number[]): string {
    return `${productId}:${[...modifierIds].sort((a, b) => a - b).join(',')}`;
}

export default function PosCheck() {
    const { table, categories, check } = usePage<PageProps>().props;

    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(categories[0]?.id ?? null);
    const [draft, setDraft] = useState<DraftLine[]>([]);
    const [modifierTarget, setModifierTarget] = useState<{ product: Product; selected: number[] } | null>(null);
    const [voidTarget, setVoidTarget] = useState<CheckItem | null>(null);
    const [voidReason, setVoidReason] = useState('');
    const [paying, setPaying] = useState(false);
    const [busy, setBusy] = useState(false);
    const [checkOpen, setCheckOpen] = useState(false);

    // KDS bumps and other cashiers' edits refresh the live check.
    useEcho('pos.tickets', 'PosTicketsUpdated', () => router.reload({ only: ['check'] }));

    const activeCategory = useMemo(
        () => categories.find((c) => c.id === activeCategoryId) ?? categories[0],
        [categories, activeCategoryId],
    );

    const draftModifiers = (line: DraftLine): Modifier[] =>
        line.product.modifier_groups.flatMap((g) => g.modifiers).filter((m) => line.modifierIds.includes(m.id));

    const draftLineTotal = (line: DraftLine): number => {
        const delta = draftModifiers(line).reduce((s, m) => s + m.price_delta, 0);

        return (line.product.price + delta) * line.quantity;
    };

    const draftTotal = draft.reduce((s, l) => s + draftLineTotal(l), 0);

    function addProduct(product: Product) {
        if (product.modifier_groups.length > 0) {
            setModifierTarget({ product, selected: [] });

            return;
        }

        addDraftLine(product, []);
    }

    function addDraftLine(product: Product, modifierIds: number[]) {
        const key = draftKey(product.id, modifierIds);
        setDraft((cur) => {
            const existing = cur.find((l) => l.key === key);

            if (existing) {
                return cur.map((l) => (l.key === key ? { ...l, quantity: l.quantity + 1 } : l));
            }

            return [...cur, { key, product, quantity: 1, modifierIds }];
        });
    }

    function changeDraftQty(key: string, delta: number) {
        setDraft((cur) => cur.map((l) => (l.key === key ? { ...l, quantity: l.quantity + delta } : l)).filter((l) => l.quantity > 0));
    }

    function modifierValid(): boolean {
        if (!modifierTarget) {
return false;
}

        return modifierTarget.product.modifier_groups.every((g) => {
            const count = g.modifiers.filter((m) => modifierTarget.selected.includes(m.id)).length;

            return count >= g.min_select && count <= g.max_select;
        });
    }

    function toggleModifier(group: ModifierGroup, modifier: Modifier) {
        if (!modifierTarget) {
return;
}

        const selected = new Set(modifierTarget.selected);

        if (selected.has(modifier.id)) {
            selected.delete(modifier.id);
        } else {
            const inGroup = group.modifiers.filter((m) => selected.has(m.id));

            if (inGroup.length >= group.max_select && group.max_select === 1) {
                inGroup.forEach((m) => selected.delete(m.id));
            } else if (inGroup.length >= group.max_select) {
                return;
            }

            selected.add(modifier.id);
        }

        setModifierTarget({ ...modifierTarget, selected: [...selected] });
    }

    function sendDraftToCheck() {
        if (draft.length === 0 || busy) {
return;
}

        setBusy(true);
        router.post(
            `/pos/orders/${check.id}/items`,
            {
                items: draft.map((l) => ({ product_id: l.product.id, quantity: l.quantity, modifier_ids: l.modifierIds })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDraft([]),
                onFinish: () => setBusy(false),
            },
        );
    }

    function removeItem(item: CheckItem) {
        if (item.completed) {
            setVoidTarget(item);
            setVoidReason('');

            return;
        }

        setBusy(true);
        router.delete(`/pos/order-items/${item.id}`, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    function confirmVoid() {
        if (!voidTarget || voidReason.trim() === '') {
return;
}

        setBusy(true);
        router.delete(`/pos/order-items/${voidTarget.id}`, {
            data: { reason: voidReason.trim() },
            preserveScroll: true,
            onSuccess: () => setVoidTarget(null),
            onFinish: () => setBusy(false),
        });
    }

    function closeTable(tenders: { method: string; amount: number; received_amount?: number }[]) {
        setBusy(true);
        router.post(`/pos/orders/${check.id}/close`, { tenders }, { onFinish: () => setBusy(false) });
    }

    const liveItems = check.items.filter((i) => !i.voided);

    const checkContent = (
        <>
            <div className="min-h-0 flex-1 overflow-y-auto p-3">
                {/* Existing check items */}
                {liveItems.length === 0 && draft.length === 0 && (
                    <p className="mt-8 text-center text-sm text-zinc-400">Счёт пуст — добавьте позиции</p>
                )}
                {liveItems.map((item) => (
                    <div key={item.id} className="flex items-start justify-between gap-2 border-b border-zinc-200 py-2">
                        <div className="min-w-0">
                            <div className="text-base font-semibold">
                                {item.quantity}× {item.name}{' '}
                                {item.station && (
                                    <span className="rounded border border-zinc-300 px-1 text-[10px] font-normal text-zinc-500 uppercase">
                                        {STATION_LABELS[item.station]}
                                    </span>
                                )}
                                <span className={`ml-1 text-[10px] font-semibold uppercase ${item.completed ? 'text-emerald-600' : 'text-amber-600'}`}>
                                    {item.completed ? 'готово' : 'в работе'}
                                </span>
                            </div>
                            {item.modifiers.length > 0 && (
                                <div className="pl-3 text-xs text-zinc-500">{item.modifiers.map((m) => m.name).join(', ')}</div>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-col items-end">
                            <span className="text-sm tabular-nums">{formatPrice(item.line_total)}</span>
                            <button
                                type="button"
                                onClick={() => removeItem(item)}
                                disabled={busy}
                                className="mt-1 px-1 py-0.5 text-xs text-red-500 disabled:opacity-40"
                            >
                                {item.completed ? 'списать' : 'убрать'}
                            </button>
                        </div>
                    </div>
                ))}

                {/* Draft round (not yet sent) */}
                {draft.length > 0 && (
                    <div className="mt-3 rounded-lg border-2 border-dashed border-zinc-400 p-2">
                        <p className="mb-1 text-xs font-semibold tracking-wide text-zinc-500 uppercase">Новый раунд</p>
                        {draft.map((line) => (
                            <div key={line.key} className="flex items-center justify-between py-1">
                                <span className="text-sm font-medium">{line.product.name}</span>
                                <div className="flex items-center gap-1">
                                    <button type="button" onClick={() => changeDraftQty(line.key, -1)} className="h-9 w-9 rounded border-2 border-zinc-400 text-lg">
                                        −
                                    </button>
                                    <span className="w-6 text-center text-sm tabular-nums">{line.quantity}</span>
                                    <button type="button" onClick={() => changeDraftQty(line.key, 1)} className="h-9 w-9 rounded border-2 border-zinc-400 text-lg">
                                        +
                                    </button>
                                </div>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={sendDraftToCheck}
                            disabled={busy}
                            className="mt-2 h-12 w-full rounded-lg bg-zinc-900 font-semibold text-white disabled:opacity-40"
                        >
                            Добавить в счёт +{formatPrice(draftTotal)}
                        </button>
                    </div>
                )}
            </div>

            <div className="flex items-baseline justify-between border-t-2 border-zinc-300 px-3 pt-2.5 text-xl font-bold">
                <span>Итого</span>
                <span className="tabular-nums">{formatPrice(check.total)}</span>
            </div>
            <button
                type="button"
                onClick={() => {
                    setCheckOpen(false);
                    setPaying(true);
                }}
                disabled={liveItems.length === 0 || busy}
                className="m-3 h-16 rounded-xl bg-emerald-600 text-lg font-bold text-white disabled:opacity-30"
            >
                Закрыть стол · {formatPrice(check.total)}
            </button>
        </>
    );

    return (
        <div className="flex h-screen flex-col bg-zinc-100 text-zinc-900 select-none">
            <Head title={`Стол ${table.label ?? table.number}`} />

            <header className="flex items-center gap-2 border-b border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-600">
                <a href="/pos/tables" className="shrink-0 rounded-lg border-2 border-zinc-400 px-3 py-1.5 font-semibold text-zinc-700">
                    ← Залы
                </a>
                <span className="truncate text-base font-bold text-zinc-900">Стол {table.label ?? table.number}</span>
                <span className="hidden text-zinc-400 sm:inline">счёт {check.number}</span>
            </header>

            <div className="flex min-h-0 flex-1">
                {/* Catalog */}
                <main className="flex min-w-0 flex-1 flex-col p-3 lg:flex-[1.8]">
                    <nav className="mb-3 flex gap-2 overflow-x-auto pb-1">
                        {categories.map((c) => (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => setActiveCategoryId(c.id)}
                                className={`rounded-full border-2 px-5 py-3 text-base font-semibold whitespace-nowrap ${
                                    c.id === activeCategory?.id ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-400 bg-white text-zinc-700'
                                }`}
                            >
                                {c.name}
                            </button>
                        ))}
                    </nav>
                    <div className="grid flex-1 auto-rows-min grid-cols-2 gap-2 overflow-y-auto pb-24 sm:grid-cols-3 lg:pb-0 xl:grid-cols-4">
                        {activeCategory?.products.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => addProduct(p)}
                                className="flex h-24 flex-col justify-between rounded-xl border-2 border-zinc-300 bg-white p-3 text-left active:scale-95"
                            >
                                <span className="line-clamp-2 text-base leading-tight font-semibold">{p.name}</span>
                                <span className="text-sm text-zinc-500">{formatPrice(p.price)}</span>
                            </button>
                        ))}
                    </div>
                </main>

                {/* Check — desktop sidebar */}
                <aside className="hidden w-[380px] shrink-0 flex-col border-l-2 border-zinc-300 bg-white lg:flex">{checkContent}</aside>
            </div>

            {/* Check — mobile sticky bar + bottom sheet */}
            <button
                type="button"
                onClick={() => setCheckOpen(true)}
                className="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between bg-emerald-600 px-5 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] text-white lg:hidden"
            >
                <span className="flex items-center gap-2 text-base font-semibold">
                    <span className="flex h-7 min-w-7 items-center justify-center rounded-full bg-white px-1.5 text-sm font-bold text-emerald-700">
                        {liveItems.length + draft.length}
                    </span>
                    Счёт стола{draft.length > 0 ? ` · +${draft.length}` : ''}
                </span>
                <span className="text-lg font-bold tabular-nums">{formatPrice(check.total)}</span>
            </button>

            {checkOpen && (
                <div className="fixed inset-0 z-40 flex flex-col justify-end bg-black/40 lg:hidden" onClick={() => setCheckOpen(false)}>
                    <div className="flex max-h-[88vh] flex-col rounded-t-2xl bg-white" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 pt-3 pb-1">
                            <span className="text-base font-bold">Стол {table.label ?? table.number}</span>
                            <button type="button" onClick={() => setCheckOpen(false)} className="px-2 py-1 text-2xl leading-none text-zinc-400">
                                ×
                            </button>
                        </div>
                        {checkContent}
                    </div>
                </div>
            )}

            {/* Modifier picker */}
            {modifierTarget && (
                <div
                    className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
                    onClick={() => setModifierTarget(null)}
                >
                    <div
                        className="flex max-h-[85vh] w-full max-w-md flex-col rounded-t-2xl bg-white p-4 sm:rounded-2xl"
                        onClick={(e) => e.stopPropagation()}
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
                            <button type="button" onClick={() => setModifierTarget(null)} className="h-13 flex-1 rounded-xl border-2 border-zinc-300 text-base font-semibold">
                                Отмена
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    addDraftLine(modifierTarget.product, modifierTarget.selected);
                                    setModifierTarget(null);
                                }}
                                disabled={!modifierValid()}
                                className="h-13 flex-1 rounded-xl bg-zinc-900 text-base font-semibold text-white disabled:opacity-30"
                            >
                                В раунд
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Void reason */}
            {voidTarget && (
                <div
                    className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
                    onClick={() => setVoidTarget(null)}
                >
                    <div className="w-full max-w-sm rounded-t-2xl bg-white p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-2xl sm:pb-4" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-1 text-lg font-bold">Списать «{voidTarget.name}»</h2>
                        <p className="mb-3 text-sm text-zinc-500">Позиция уже приготовлена — укажите причину списания.</p>
                        <input
                            autoFocus
                            value={voidReason}
                            onChange={(e) => setVoidReason(e.target.value)}
                            placeholder="Причина (гость передумал, брак…)"
                            className="mb-3 h-12 w-full rounded-lg border-2 border-zinc-300 px-3 text-base"
                        />
                        <div className="flex gap-2">
                            <button type="button" onClick={() => setVoidTarget(null)} className="h-12 flex-1 rounded-xl border-2 border-zinc-300 font-semibold">
                                Отмена
                            </button>
                            <button
                                type="button"
                                onClick={confirmVoid}
                                disabled={voidReason.trim() === '' || busy}
                                className="h-12 flex-1 rounded-xl bg-red-600 font-semibold text-white disabled:opacity-30"
                            >
                                Списать
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Payment */}
            {paying && (
                <PaymentScreen
                    total={check.total}
                    busy={busy}
                    onSettle={(tenders) => closeTable(tenders)}
                    onCancel={() => setPaying(false)}
                />
            )}
        </div>
    );
}
