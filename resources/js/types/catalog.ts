export type Modifier = {
    id: number;
    modifier_group_id: number;
    name: string;
    price_delta: string;
    is_active: boolean;
    sort_order: number;
};

export type ModifierGroup = {
    id: number;
    name: string;
    min_select: number;
    max_select: number;
    is_required: boolean;
    sort_order: number;
    modifiers: Modifier[];
    pivot?: { sort_order: number };
};

export type Product = {
    id: number;
    category_id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string;
    image_path: string | null;
    image_url: string | null;
    weight_grams: number | null;
    calories: number | null;
    is_active: boolean;
    in_stop_list: boolean;
    sort_order: number;
    modifier_groups: ModifierGroup[];
};

export type Category = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    products: Product[];
};

export type Story = {
    id: number;
    story_group_id: number;
    media_type: 'image' | 'video';
    media_path: string;
    media_url: string | null;
    duration_ms: number;
    cta_label: string | null;
    cta_url: string | null;
    sort_order: number;
    is_active: boolean;
};

export type StoryGroup = {
    id: number;
    title: string;
    preview_path: string | null;
    preview_url: string | null;
    sort_order: number;
    is_active: boolean;
    stories: Story[];
};

export type Establishment = {
    name: string;
    phone: string;
    accepting_orders: boolean;
    closed_reason: string | null;
    min_order_amount: number;
};

export type CartLineModifier = {
    modifier_id: number;
    name: string;
    price_delta: number;
};

export type CartLine = {
    id: string; // composite key: product_id + sorted modifier ids
    product_id: number;
    product_name: string;
    unit_price: number;
    quantity: number;
    modifiers: CartLineModifier[];
    /** Sum of all modifier price_deltas for one unit. */
    modifiers_total_per_unit: number;
    /** Public image URL, null when no photo uploaded — placeholder used. */
    image_url: string | null;
};
