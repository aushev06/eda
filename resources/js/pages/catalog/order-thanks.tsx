import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect } from 'react';
import { formatRub } from '@/lib/format';

type OrderItemModifier = {
    id: number;
    modifier_name: string;
    price_delta: string;
};

type OrderItem = {
    id: number;
    product_name: string;
    quantity: number;
    unit_price: string;
    line_total: string;
    modifiers: OrderItemModifier[];
};

type Order = {
    id: number;
    number: string;
    status: string;
    delivery_type: string;
    customer_name: string;
    customer_phone: string;
    customer_comment: string | null;
    delivery_street: string | null;
    delivery_apartment: string | null;
    subtotal: string;
    modifiers_total: string;
    delivery_fee: string;
    discount_total: string;
    promo_code: string | null;
    total: string;
    items: OrderItem[];
    delivery_zone: { name: string } | null;
};

type Props = {
    order: Order;
};

export default function OrderThanks({ order }: Props) {
    const isDelivery = order.delivery_type === 'delivery';

    // Belt-and-suspenders: if the cart somehow survived the checkout submit
    // (e.g. user landed here via direct link or browser back-then-forward),
    // wipe it now. The checkout page already calls clear() on success.
    useEffect(() => {
        if (typeof window !== 'undefined') {
            window.localStorage.removeItem('fidele.cart.v1');
        }
    }, []);

    return (
        <>
            <Head title={`Заказ ${order.number}`} />
            <main className="min-h-screen bg-stone-50 px-4 py-10 text-stone-900 sm:px-6">
                <div className="mx-auto max-w-2xl">
                    <div className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                        <div className="flex flex-col items-center text-center">
                            <div className="grid size-14 place-items-center rounded-2xl bg-emerald-100 text-emerald-700">
                                <CheckCircle2 className="size-8" />
                            </div>
                            <h1 className="mt-4 text-2xl font-semibold">Спасибо за заказ!</h1>
                            <p className="mt-1 text-sm text-stone-500">
                                Номер вашего заказа
                            </p>
                            <p className="mt-2 text-3xl font-bold tracking-wider text-stone-900">{order.number}</p>
                            <p className="mt-3 max-w-md text-sm text-stone-600">
                                Мы свяжемся с вами по телефону{' '}
                                <span className="font-medium text-stone-900">{order.customer_phone}</span> для подтверждения.
                            </p>
                        </div>

                        <hr className="my-6 border-stone-100" />

                        <div className="space-y-4 text-sm">
                            <Detail label="Получение">
                                {isDelivery
                                    ? `Доставка${order.delivery_zone ? `, зона «${order.delivery_zone.name}»` : ''}`
                                    : 'Самовывоз'}
                            </Detail>
                            {isDelivery && order.delivery_street && (
                                <Detail label="Адрес">
                                    {order.delivery_street}
                                    {order.delivery_apartment ? `, кв. ${order.delivery_apartment}` : ''}
                                </Detail>
                            )}
                            {order.customer_comment && (
                                <Detail label="Комментарий">{order.customer_comment}</Detail>
                            )}
                        </div>

                        <hr className="my-6 border-stone-100" />

                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-500">Состав</h2>
                        <ul className="divide-y divide-stone-100 text-sm">
                            {order.items.map((item) => (
                                <li key={item.id} className="flex justify-between gap-3 py-2.5">
                                    <div>
                                        <p className="font-medium">
                                            {item.product_name} <span className="text-stone-400">× {item.quantity}</span>
                                        </p>
                                        {item.modifiers.length > 0 && (
                                            <p className="text-xs text-stone-500">
                                                {item.modifiers.map((m) => m.modifier_name).join(', ')}
                                            </p>
                                        )}
                                    </div>
                                    <span className="shrink-0 font-medium">{formatRub(item.line_total)}</span>
                                </li>
                            ))}
                        </ul>

                        <dl className="mt-5 space-y-1.5 border-t border-stone-100 pt-4 text-sm">
                            <Row label="Товары" value={formatRub(order.subtotal)} />
                            {Number(order.modifiers_total) > 0 && (
                                <Row label="Опции" value={formatRub(order.modifiers_total)} />
                            )}
                            {Number(order.delivery_fee) > 0 && (
                                <Row label="Доставка" value={formatRub(order.delivery_fee)} />
                            )}
                            {Number(order.discount_total) > 0 && (
                                <Row
                                    label={`Скидка${order.promo_code ? ` · ${order.promo_code}` : ''}`}
                                    value={`−${formatRub(order.discount_total)}`}
                                />
                            )}
                            <Row label="Итого" value={formatRub(order.total)} bold />
                        </dl>

                        <div className="mt-6 flex justify-center">
                            <Link
                                href="/"
                                className="inline-flex items-center gap-2 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800"
                            >
                                Вернуться в меню
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}

function Detail({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
            <dt className="w-32 shrink-0 text-xs uppercase tracking-wide text-stone-400">{label}</dt>
            <dd className="text-sm text-stone-900">{children}</dd>
        </div>
    );
}

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
    return (
        <div className={`flex justify-between ${bold ? 'text-base font-semibold text-stone-900' : 'text-stone-600'}`}>
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}
