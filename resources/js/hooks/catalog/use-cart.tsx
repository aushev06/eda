import type { ReactNode} from 'react';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { CartLine, CartLineModifier, Product } from '@/types/catalog';

const STORAGE_KEY = 'fidele.cart.v1';

type AddArgs = {
    product: Product;
    modifiers: CartLineModifier[];
    quantity: number;
};

type CartContextValue = {
    lines: CartLine[];
    count: number;
    subtotal: number;
    add: (args: AddArgs) => void;
    setQuantity: (id: string, quantity: number) => void;
    remove: (id: string) => void;
    clear: () => void;
    isOpen: boolean;
    open: () => void;
    close: () => void;
};

const CartContext = createContext<CartContextValue | null>(null);

function makeLineId(productId: number, modifiers: CartLineModifier[]): string {
    const ids = modifiers
        .map((m) => m.modifier_id)
        .sort((a, b) => a - b)
        .join('-');

    return ids.length > 0 ? `${productId}:${ids}` : `${productId}:`;
}

function loadFromStorage(): CartLine[] {
    if (typeof window === 'undefined') {
return [];
}

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
return [];
}

        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? (parsed as CartLine[]) : [];
    } catch {
        return [];
    }
}

export function CartProvider({ children }: { children: ReactNode }) {
    // Lazy initialiser: read from storage exactly once on first render so we
    // don't race the load-then-save effect pair (which previously wrote `[]`
    // to localStorage before the load effect could populate it).
    const [lines, setLines] = useState<CartLine[]>(() => loadFromStorage());
    const [isOpen, setOpen] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') {
return;
}

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
    }, [lines]);

    const add = useCallback(({ product, modifiers, quantity }: AddArgs) => {
        const modifiersTotal = modifiers.reduce((sum, m) => sum + Number(m.price_delta), 0);
        const id = makeLineId(product.id, modifiers);

        setLines((prev) => {
            const existing = prev.find((l) => l.id === id);

            if (existing) {
                return prev.map((l) => (l.id === id ? { ...l, quantity: l.quantity + quantity } : l));
            }

            return [
                ...prev,
                {
                    id,
                    product_id: product.id,
                    product_name: product.name,
                    unit_price: Number(product.price),
                    quantity,
                    modifiers,
                    modifiers_total_per_unit: modifiersTotal,
                    image_url: product.image_url,
                },
            ];
        });

        setOpen(true);
    }, []);

    const setQuantity = useCallback((id: string, quantity: number) => {
        setLines((prev) => {
            if (quantity <= 0) {
return prev.filter((l) => l.id !== id);
}

            return prev.map((l) => (l.id === id ? { ...l, quantity } : l));
        });
    }, []);

    const remove = useCallback((id: string) => {
        setLines((prev) => prev.filter((l) => l.id !== id));
    }, []);

    const clear = useCallback(() => {
        // Write synchronously: by the time the [lines] effect would have fired,
        // the checkout page is already being unmounted by Inertia and the
        // pending storage write never lands.
        if (typeof window !== 'undefined') {
            window.localStorage.removeItem(STORAGE_KEY);
        }

        setLines([]);
        setOpen(false);
    }, []);

    const { count, subtotal } = useMemo(() => {
        let c = 0;
        let s = 0;

        for (const l of lines) {
            c += l.quantity;
            s += (l.unit_price + l.modifiers_total_per_unit) * l.quantity;
        }

        return { count: c, subtotal: s };
    }, [lines]);

    const value = useMemo<CartContextValue>(
        () => ({
            lines,
            count,
            subtotal,
            add,
            setQuantity,
            remove,
            clear,
            isOpen,
            open: () => setOpen(true),
            close: () => setOpen(false),
        }),
        [lines, count, subtotal, add, setQuantity, remove, clear, isOpen],
    );

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
    const ctx = useContext(CartContext);

    if (!ctx) {
        throw new Error('useCart must be used inside <CartProvider>');
    }

    return ctx;
}
