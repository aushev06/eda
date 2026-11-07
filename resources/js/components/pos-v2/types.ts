export type Station = 'kitchen' | 'bar';

export type PosModifier = {
    id: number;
    name: string;
    price_delta: number;
};

export type PosModifierGroup = {
    id: number;
    name: string;
    min_select: number;
    max_select: number;
    modifiers: PosModifier[];
};

export type PosProduct = {
    id: number;
    name: string;
    price: number;
    station: Station;
    modifier_groups: PosModifierGroup[];
};

export type PosCategory = {
    id: number;
    name: string;
    products: PosProduct[];
};

/** A not-yet-sent line: same product + same modifier set merge into one. */
export type DraftLine = {
    key: string;
    product: PosProduct;
    quantity: number;
    modifierIds: number[];
};

export type Tender = {
    method: 'cash' | 'card_online';
    amount: number;
    received_amount?: number;
};

export type SharedPosProps = {
    auth: { user: { name: string } | null };
    flash: {
        placed_order_number?: string | null;
        closed_order_number?: string | null;
        change_due?: number | null;
    };
};
