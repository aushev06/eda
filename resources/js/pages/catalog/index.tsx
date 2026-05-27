import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Gift } from 'lucide-react';
import { useState } from 'react';
import { CartDrawer } from '@/components/catalog/cart-drawer';
import { CategoryNav } from '@/components/catalog/category-nav';
import { ProductCard } from '@/components/catalog/product-card';
import { ProductDialog } from '@/components/catalog/product-dialog';
import { SiteHeader } from '@/components/catalog/site-header';
import { StoryRail } from '@/components/catalog/story-rail';
import { CartProvider, useCart } from '@/hooks/catalog/use-cart';
import type { Category, Establishment, Product, StoryGroup } from '@/types/catalog';

type Props = {
    categories: Category[];
    story_groups: StoryGroup[];
    establishment: Establishment;
};

export default function CatalogIndex({ categories, story_groups, establishment }: Props) {
    return (
        <CartProvider>
            <Head title={establishment.name} />
            <Catalog categories={categories} story_groups={story_groups} establishment={establishment} />
        </CartProvider>
    );
}

function Catalog({ categories, story_groups, establishment }: Props) {
    const [dialogProduct, setDialogProduct] = useState<Product | null>(null);
    const { add } = useCart();

    function quickAdd(product: Product) {
        add({ product, modifiers: [], quantity: 1 });
    }

    const totalProducts = categories.reduce((acc, c) => acc + c.products.length, 0);

    return (
        <div className="min-h-screen bg-stone-50 text-stone-900">
            <SiteHeader establishment={establishment} />
            <CategoryNav categories={categories} />

            <main className="mx-auto max-w-6xl px-4 pb-32 pt-6 sm:px-6">
                <StoryRail groups={story_groups} />

                <Link
                    href="/loyalty"
                    className="group mb-6 flex items-center justify-between gap-4 rounded-2xl bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500 p-5 text-white shadow-sm transition hover:shadow-md"
                >
                    <div className="flex items-center gap-4">
                        <div className="grid size-12 shrink-0 place-items-center rounded-xl bg-white/20 backdrop-blur-sm">
                            <Gift className="size-6" />
                        </div>
                        <div>
                            <p className="text-base font-semibold">Бонусная программа — кэшбек до 25%</p>
                            <p className="text-sm text-amber-50/95">Копите бонусы с каждого заказа и платите ими как рублями</p>
                        </div>
                    </div>
                    <ArrowRight className="hidden size-5 transition group-hover:translate-x-1 sm:block" />
                </Link>

                {totalProducts === 0 ? (
                    <div className="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
                        <p className="text-sm text-stone-500">Меню пока пустое. Загляните позже.</p>
                    </div>
                ) : (
                    <div className="space-y-12">
                        {categories.map((category) => (
                            <section
                                key={category.id}
                                id={`category-${category.id}`}
                                className="scroll-mt-32"
                            >
                                <h2 className="mb-4 text-2xl font-bold tracking-tight text-stone-900">
                                    {category.name}
                                </h2>
                                {category.products.length === 0 ? (
                                    <p className="text-sm text-stone-400">В этой категории пока ничего нет.</p>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {category.products.map((product) => (
                                            <ProductCard
                                                key={product.id}
                                                product={product}
                                                onOpen={setDialogProduct}
                                                onQuickAdd={quickAdd}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>
                        ))}
                    </div>
                )}
            </main>

            <ProductDialog product={dialogProduct} onClose={() => setDialogProduct(null)} />
            <CartDrawer establishment={establishment} />
        </div>
    );
}

