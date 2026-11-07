import type {
    DraftLine,
    PosModifier,
    PosModifierGroup,
    PosProduct,
} from './types';

export const STATION_LABELS: Record<string, string> = {
    kitchen: 'Кухня',
    bar: 'Бар',
};

export function formatPrice(value: number): string {
    return `${value.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ₽`;
}

export const round2 = (n: number) => Math.round(n * 100) / 100;

/** Stable key so the same product with the same modifier set merges into one line. */
export function lineKey(productId: number, modifierIds: number[]): string {
    return `${productId}:${[...modifierIds].sort((a, b) => a - b).join(',')}`;
}

export function lineModifiers(line: DraftLine): PosModifier[] {
    return line.product.modifier_groups
        .flatMap((group) => group.modifiers)
        .filter((modifier) => line.modifierIds.includes(modifier.id));
}

export function lineTotal(line: DraftLine): number {
    const perUnitDelta = lineModifiers(line).reduce(
        (sum, modifier) => sum + modifier.price_delta,
        0,
    );

    return (line.product.price + perUnitDelta) * line.quantity;
}

export function addToLines(
    lines: DraftLine[],
    product: PosProduct,
    modifierIds: number[],
    quantity = 1,
): DraftLine[] {
    const key = lineKey(product.id, modifierIds);

    if (lines.some((line) => line.key === key)) {
        return lines.map((line) =>
            line.key === key
                ? { ...line, quantity: line.quantity + quantity }
                : line,
        );
    }

    return [...lines, { key, product, quantity, modifierIds }];
}

export function changeLineQuantity(
    lines: DraftLine[],
    key: string,
    delta: number,
): DraftLine[] {
    return lines
        .map((line) =>
            line.key === key
                ? { ...line, quantity: line.quantity + delta }
                : line,
        )
        .filter((line) => line.quantity > 0);
}

export function draftPayload(lines: DraftLine[]) {
    return lines.map((line) => ({
        product_id: line.product.id,
        quantity: line.quantity,
        modifier_ids: line.modifierIds,
    }));
}

export function groupSelectionCount(
    group: PosModifierGroup,
    selected: number[],
): number {
    return group.modifiers.filter((modifier) => selected.includes(modifier.id))
        .length;
}

export function isSelectionValid(
    product: PosProduct,
    selected: number[],
): boolean {
    return product.modifier_groups.every((group) => {
        const count = groupSelectionCount(group, selected);

        return count >= group.min_select && count <= group.max_select;
    });
}

/**
 * Toggle a modifier within its group's limits. Single-choice groups swap
 * the selection instead of blocking the tap.
 */
export function toggleModifierSelection(
    group: PosModifierGroup,
    modifier: PosModifier,
    selected: number[],
): number[] {
    const next = new Set(selected);

    if (next.has(modifier.id)) {
        next.delete(modifier.id);

        return [...next];
    }

    const selectedInGroup = group.modifiers.filter((m) => next.has(m.id));

    if (selectedInGroup.length >= group.max_select) {
        if (group.max_select !== 1) {
            return selected;
        }

        selectedInGroup.forEach((m) => next.delete(m.id));
    }

    next.add(modifier.id);

    return [...next];
}

export function minutesSince(iso: string | null, now: Date): number | null {
    if (!iso) {
        return null;
    }

    return Math.max(
        0,
        Math.floor((now.getTime() - new Date(iso).getTime()) / 60_000),
    );
}

export function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}
