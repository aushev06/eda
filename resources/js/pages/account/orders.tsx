import { Head, Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { AccountLayout } from '@/components/account/account-layout';
import { formatRub } from '@/lib/format';

type OrderItem = {
    id: number;
    product_name: string;
    quantity: number;
};

type Order = {
    id: number;
    number: string;
    status: string;
    delivery_type: string;
    total: string;
    created_at: string;
    items: OrderItem[];
    delivery_zone?: { name: string } | null;
};

type Props = {
    orders: Order[];
};

const STATUS_LABEL: Record<string, string> = {
    new: 'Новый',
    accepted: 'Принят',
    preparing: 'Готовится',
    ready: 'Готов',
    delivering: 'В пути',
    delivered: 'Доставлен',
    cancelled: 'Отменён',
};

const STATUS_COLOR: Record<string, string> = {
    new: 'bg-amber-100 text-amber-800',
    accepted: 'bg-sky-100 text-sky-800',
    preparing: 'bg-sky-100 text-sky-800',
    ready: 'bg-emerald-100 text-emerald-800',
    delivering: 'bg-sky-100 text-sky-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-rose-100 text-rose-800',
};

export default function AccountOrders({ orders }: Props) {
    return (
        <>
            <Head title="История заказов" />
            <AccountLayout title="История заказов" active="orders">
                {orders.length === 0 ? (
                    <div className="grid place-items-center rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
                        <ShoppingBag className="size-10 text-stone-300" />
                        <p className="mt-3 text-sm text-stone-500">Здесь появятся ваши заказы.</p>
                        <Link
                            href="/"
                            className="mt-4 inline-flex items-center rounded-full bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800"
                        >
                            К меню
                        </Link>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {orders.map((order) => (
                            <li
                                key={order.id}
                                className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <span className="font-mono text-base font-semibold tracking-wider">
                                            {order.number}
                                        </span>
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                STATUS_COLOR[order.status] ?? 'bg-stone-100 text-stone-700'
                                            }`}
                                        >
                                            {STATUS_LABEL[order.status] ?? order.status}
                                        </span>
                                    </div>
                                    <time className="text-xs text-stone-500">
                                        {new Date(order.created_at).toLocaleString('ru-RU', {
                                            day: '2-digit',
                                            month: '2-digit',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </time>
                                </div>

                                <p className="mt-2 text-sm text-stone-600">
                                    {order.delivery_type === 'delivery'
                                        ? `Доставка${order.delivery_zone ? ` · ${order.delivery_zone.name}` : ''}`
                                        : 'Самовывоз'}{' '}
                                    · {order.items.length} {pluralize(order.items.length)}
                                </p>

                                <div className="mt-3 flex items-center justify-between">
                                    <p className="text-xs text-stone-500">
                                        {order.items.map((i) => `${i.product_name} × ${i.quantity}`).join(', ')}
                                    </p>
                                    <span className="text-base font-semibold">{formatRub(order.total)}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </AccountLayout>
        </>
    );
}

function pluralize(n: number): string {
    const m10 = n % 10;
    const m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return 'позиция';
    if ([2, 3, 4].includes(m10) && ![12, 13, 14].includes(m100)) return 'позиции';
    return 'позиций';
}
