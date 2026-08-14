import { Minus, Plus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useCart } from '@/hooks/catalog/use-cart';
import { formatRub } from '@/lib/format';
import type { CartLineModifier, Modifier, ModifierGroup, Product } from '@/types/catalog';
import { ProductPlaceholder } from './product-placeholder';

type Props = {
    product: Product | null;
    onClose: () => void;
};

type Selection = Record<number, number[]>; // groupId → modifier ids

export function ProductDialog({ product, onClose }: Props) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const { add } = useCart();

    const [selection, setSelection] = useState<Selection>({});
    const [quantity, setQuantity] = useState(1);

    useEffect(() => {
        if (product) {
            const initial: Selection = {};

            for (const group of product.modifier_groups) {
                if (group.is_required && group.min_select >= 1 && group.modifiers.length > 0) {
                    initial[group.id] = [group.modifiers[0].id];
                } else {
                    initial[group.id] = [];
                }
            }

            setSelection(initial);
            setQuantity(1);
            dialogRef.current?.showModal();
        } else {
            dialogRef.current?.close();
        }
    }, [product]);

    function toggle(group: ModifierGroup, modifier: Modifier) {
        setSelection((prev) => {
            const current = prev[group.id] ?? [];
            const isSelected = current.includes(modifier.id);
            let next: number[];

            if (group.max_select <= 1) {
                next = isSelected && !group.is_required ? [] : [modifier.id];
            } else if (isSelected) {
                next = current.filter((id) => id !== modifier.id);
            } else if (current.length < group.max_select) {
                next = [...current, modifier.id];
            } else {
                next = current;
            }

            return { ...prev, [group.id]: next };
        });
    }

    const flatModifiers = useMemo<CartLineModifier[]>(() => {
        if (!product) {
return [];
}

        const out: CartLineModifier[] = [];

        for (const group of product.modifier_groups) {
            const ids = selection[group.id] ?? [];

            for (const id of ids) {
                const m = group.modifiers.find((x) => x.id === id);

                if (m) {
out.push({ modifier_id: m.id, name: m.name, price_delta: Number(m.price_delta) });
}
            }
        }

        return out;
    }, [product, selection]);

    const unmetGroups = useMemo(() => {
        if (!product) {
return [];
}

        return product.modifier_groups.filter((g) => (selection[g.id]?.length ?? 0) < g.min_select);
    }, [product, selection]);

    const isValid = unmetGroups.length === 0;

    const unitPrice = useMemo(() => {
        if (!product) {
return 0;
}

        const modSum = flatModifiers.reduce((acc, m) => acc + m.price_delta, 0);

        return Number(product.price) + modSum;
    }, [product, flatModifiers]);

    function handleAdd() {
        if (!product || !isValid) {
return;
}

        add({ product, modifiers: flatModifiers, quantity });
        onClose();
    }

    return (
        <dialog
            ref={dialogRef}
            onClose={onClose}
            onClick={(e) => {
                if (e.target === dialogRef.current) {
onClose();
}
            }}
            className="m-auto w-full max-w-2xl rounded-2xl p-0 backdrop:bg-stone-900/40 backdrop:backdrop-blur-sm"
        >
            {product && (
                <div className="flex flex-col bg-white text-stone-900 sm:flex-row">
                    <button
                        type="button"
                        onClick={onClose}
                        className="absolute right-3 top-3 z-10 grid size-9 place-items-center rounded-full bg-white/90 text-stone-700 shadow-md hover:bg-white"
                        aria-label="Закрыть"
                    >
                        <X className="size-5" />
                    </button>

                    <div className="relative aspect-square w-full shrink-0 sm:aspect-auto sm:w-72">
                        {product.image_url ? (
                            <img
                                src={product.image_url}
                                alt={product.name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <ProductPlaceholder name={product.name} className="size-full" />
                        )}
                    </div>

                    <div className="flex max-h-[80vh] flex-1 flex-col overflow-hidden">
                        <div className="flex-1 space-y-4 overflow-y-auto p-5">
                            <div>
                                <h2 className="text-xl font-semibold text-stone-900">{product.name}</h2>
                                {product.description && (
                                    <p className="mt-1 text-sm text-stone-500">{product.description}</p>
                                )}
                                <div className="mt-2 flex gap-3 text-xs text-stone-500">
                                    {product.weight_grams && <span>{product.weight_grams} г</span>}
                                    {product.calories && <span>{product.calories} ккал</span>}
                                </div>
                            </div>

                            {product.modifier_groups.map((group) => {
                                const selected = selection[group.id] ?? [];
                                const isRadio = group.max_select <= 1;
                                const unmet = selected.length < group.min_select;

                                return (
                                    <div key={group.id} className="space-y-2">
                                        <div className="flex items-baseline justify-between">
                                            <h3 className="text-sm font-semibold text-stone-900">
                                                {group.name}
                                                {group.is_required && (
                                                    <span className="ml-1 text-rose-600">*</span>
                                                )}
                                            </h3>
                                            <span className="text-xs text-stone-400">
                                                {isRadio
                                                    ? group.is_required
                                                        ? 'выберите 1'
                                                        : 'до 1'
                                                    : `до ${group.max_select}`}
                                            </span>
                                        </div>
                                        <ul className="space-y-1.5">
                                            {group.modifiers.map((modifier) => {
                                                const checked = selected.includes(modifier.id);

                                                return (
                                                    <li key={modifier.id}>
                                                        <label
                                                            className={`flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition ${
                                                                checked
                                                                    ? 'border-amber-400 bg-amber-50 text-stone-900'
                                                                    : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300'
                                                            }`}
                                                        >
                                                            <input
                                                                type={isRadio ? 'radio' : 'checkbox'}
                                                                name={`group-${group.id}`}
                                                                checked={checked}
                                                                onChange={() => toggle(group, modifier)}
                                                                className="size-4 accent-amber-500"
                                                            />
                                                            <span className="flex-1">{modifier.name}</span>
                                                            {Number(modifier.price_delta) > 0 && (
                                                                <span className="text-xs font-medium text-stone-500">
                                                                    +{formatRub(modifier.price_delta)}
                                                                </span>
                                                            )}
                                                        </label>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                        {unmet && (
                                            <p className="text-xs text-rose-600">
                                                Выберите минимум {group.min_select}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        <div className="border-t border-stone-200 bg-stone-50 p-4">
                            <div className="flex items-center justify-between gap-3">
                                <div className="inline-flex items-center rounded-full bg-white ring-1 ring-stone-200">
                                    <button
                                        type="button"
                                        onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                                        className="grid size-10 place-items-center rounded-l-full text-stone-600 hover:bg-stone-100 disabled:opacity-50"
                                        disabled={quantity <= 1}
                                        aria-label="Меньше"
                                    >
                                        <Minus className="size-4" />
                                    </button>
                                    <span className="min-w-8 text-center text-sm font-semibold text-stone-900">{quantity}</span>
                                    <button
                                        type="button"
                                        onClick={() => setQuantity((q) => q + 1)}
                                        className="grid size-10 place-items-center rounded-r-full text-stone-600 hover:bg-stone-100"
                                        aria-label="Больше"
                                    >
                                        <Plus className="size-4" />
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    onClick={handleAdd}
                                    disabled={!isValid}
                                    className="flex-1 rounded-full bg-stone-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-stone-800 disabled:opacity-50"
                                >
                                    Добавить · {formatRub(unitPrice * quantity)}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </dialog>
    );
}
