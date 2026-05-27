import {
    Beef,
    Beer,
    Cake,
    Coffee,
    Cookie,
    CookingPot,
    Croissant,
    CupSoda,
    Drumstick,
    Egg,
    Fish,
    GlassWater,
    IceCream,
    LucideIcon,
    Pizza,
    Salad,
    Sandwich,
    Soup,
    Utensils,
    Wine,
} from 'lucide-react';

type Props = {
    name: string;
    className?: string;
};

type Palette = {
    from: string;
    to: string;
    fg: string;
    icon: LucideIcon;
    match: RegExp;
};

const PALETTES: Palette[] = [
    { from: 'from-rose-100', to: 'to-orange-200', fg: 'text-rose-700', icon: Pizza, match: /пицц|pizza/i },
    { from: 'from-amber-100', to: 'to-yellow-200', fg: 'text-amber-700', icon: Sandwich, match: /бургер|burger|сэндвич|sandwich/i },
    { from: 'from-orange-100', to: 'to-amber-200', fg: 'text-orange-700', icon: Drumstick, match: /курин|курица|крыл|нагг|chicken|wings/i },
    { from: 'from-red-100', to: 'to-rose-200', fg: 'text-red-700', icon: Beef, match: /стейк|говяд|мяс|шашлык|beef|steak|meat/i },
    { from: 'from-sky-100', to: 'to-blue-200', fg: 'text-sky-700', icon: Fish, match: /рыб|лосос|форел|тунец|fish|salmon/i },
    { from: 'from-emerald-100', to: 'to-green-200', fg: 'text-emerald-700', icon: Salad, match: /салат|salad|овощ|veggie/i },
    { from: 'from-orange-100', to: 'to-red-200', fg: 'text-orange-700', icon: Soup, match: /суп|борщ|солянк|soup|broth/i },
    { from: 'from-orange-200', to: 'to-amber-300', fg: 'text-orange-800', icon: CookingPot, match: /паст|спагетт|лапш|ризотто|pasta|noodle|risotto/i },
    { from: 'from-amber-100', to: 'to-orange-200', fg: 'text-amber-700', icon: Croissant, match: /круассан|выпечк|булк|пирожк|хлеб|сэндв|тост|bake|pastry/i },
    { from: 'from-orange-100', to: 'to-amber-200', fg: 'text-orange-700', icon: Egg, match: /омлет|яичниц|сырник|блин|egg|pancake/i },
    { from: 'from-rose-100', to: 'to-pink-200', fg: 'text-rose-700', icon: Cake, match: /торт|чизкейк|пирожн|cake|cheesecake/i },
    { from: 'from-violet-100', to: 'to-purple-200', fg: 'text-violet-700', icon: IceCream, match: /мороже|сорбе|ice|sorbet/i },
    { from: 'from-pink-100', to: 'to-rose-200', fg: 'text-pink-700', icon: Cookie, match: /печен|маффин|кекс|десерт|cookie|muffin|dessert/i },
    { from: 'from-stone-200', to: 'to-amber-200', fg: 'text-stone-700', icon: Coffee, match: /кофе|капучин|латте|эспресс|coffee|cappuccino|latte/i },
    { from: 'from-purple-100', to: 'to-violet-200', fg: 'text-purple-700', icon: Wine, match: /вино|wine/i },
    { from: 'from-yellow-100', to: 'to-amber-200', fg: 'text-yellow-700', icon: Beer, match: /пиво|сидр|beer|ale|lager/i },
    { from: 'from-sky-100', to: 'to-cyan-200', fg: 'text-sky-700', icon: CupSoda, match: /кол|пепси|спрайт|лимонад|газир|soda|cola/i },
    { from: 'from-blue-100', to: 'to-sky-200', fg: 'text-blue-700', icon: GlassWater, match: /сок|вода|морс|компот|juice|water/i },
];

const FALLBACK: Palette = {
    from: 'from-stone-100',
    to: 'to-stone-200',
    fg: 'text-stone-500',
    icon: Utensils,
    match: /.*/,
};

function pickPalette(name: string): Palette {
    return PALETTES.find((p) => p.match.test(name)) ?? FALLBACK;
}

export function ProductPlaceholder({ name, className }: Props) {
    const palette = pickPalette(name);
    const Icon = palette.icon;

    return (
        <div
            className={`flex items-center justify-center bg-gradient-to-br ${palette.from} ${palette.to} ${className ?? ''}`}
            aria-hidden="true"
        >
            <Icon className={`size-16 ${palette.fg}`} strokeWidth={1.5} />
        </div>
    );
}
