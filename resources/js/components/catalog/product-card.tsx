import { Plus } from 'lucide-react';
import { formatRub } from '@/lib/format';
import type { Product } from '@/types/catalog';
import { ProductPlaceholder } from './product-placeholder';

type Props = {
    product: Product;
    onOpen: (product: Product) => void;
    onQuickAdd: (product: Product) => void;
};

export function ProductCard({ product, onOpen, onQuickAdd }: Props) {
    const hasOptions = product.modifier_groups.length > 0;
    const stopped = product.in_stop_list;

    function handleCardClick() {
        if (stopped) {
return;
}

        onOpen(product);
    }

    function handleAdd(e: React.MouseEvent) {
        e.stopPropagation();

        if (stopped) {
return;
}

        if (hasOptions) {
            onOpen(product);
        } else {
            onQuickAdd(product);
        }
    }

    return (
        <button
            type="button"
            onClick={handleCardClick}
            disabled={stopped}
            className={`group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white text-left transition hover:-translate-y-0.5 hover:border-stone-300 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 ${
                stopped ? 'opacity-60' : ''
            }`}
        >
            <div className="relative aspect-square w-full">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt={product.name}
                        className="size-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <ProductPlaceholder name={product.name} className="size-full" />
                )}
                {stopped && (
                    <span className="absolute inset-x-2 top-2 rounded-full bg-rose-100 px-3 py-1 text-center text-xs font-medium text-rose-700 ring-1 ring-rose-600/20">
                        Нет в наличии
                    </span>
                )}
            </div>

            <div className="flex flex-1 flex-col gap-2 p-4">
                <h3 className="line-clamp-2 text-sm font-semibold text-stone-900">{product.name}</h3>
                {product.description && (
                    <p className="line-clamp-2 text-xs text-stone-500">{product.description}</p>
                )}
                <div className="mt-auto flex items-center justify-between pt-2">
                    <span className="text-base font-bold text-stone-900">{formatRub(product.price)}</span>
                    <span
                        onClick={handleAdd}
                        role="button"
                        tabIndex={stopped ? -1 : 0}
                        aria-label={hasOptions ? 'Выбрать опции' : 'В корзину'}
                        className={`inline-flex size-9 cursor-pointer items-center justify-center rounded-full transition ${
                            stopped
                                ? 'bg-stone-100 text-stone-400'
                                : 'bg-amber-400 text-stone-900 hover:bg-amber-500'
                        }`}
                    >
                        <Plus className="size-4" />
                    </span>
                </div>
                {hasOptions && !stopped && (
                    <span className="text-[11px] uppercase tracking-wide text-stone-400">с опциями</span>
                )}
            </div>
        </button>
    );
}
