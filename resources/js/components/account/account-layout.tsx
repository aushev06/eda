import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, Gift, LogOut, MapPin, ShoppingBag, User } from 'lucide-react';
import { ReactNode } from 'react';

type Props = {
    title: string;
    active: 'profile' | 'orders' | 'addresses' | 'bonuses' | 'notifications';
    children: ReactNode;
};

type SharedAuth = {
    auth: { customer: { name: string; phone: string } | null };
    flash?: { status?: string | null };
    notifications?: { unread_count: number } | null;
};

export function AccountLayout({ title, active, children }: Props) {
    const { props } = usePage<SharedAuth>();
    const customer = props.auth.customer;
    const status = props.flash?.status ?? null;
    const unread = props.notifications?.unread_count ?? 0;

    if (!customer) {
        return null;
    }

    function logout() {
        router.post('/account/logout');
    }

    return (
        <div className="min-h-screen bg-stone-50 text-stone-900">
            <header className="border-b border-stone-200 bg-white">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link href="/" className="inline-flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900">
                        <ArrowLeft className="size-4" />
                        К меню
                    </Link>
                    <span className="text-base font-semibold">Личный кабинет</span>
                </div>
            </header>

            <main className="mx-auto grid max-w-5xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[240px_1fr]">
                <aside>
                    <div className="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
                        <div className="mb-3 border-b border-stone-100 pb-3">
                            <p className="text-sm font-semibold">{customer.name}</p>
                            <p className="text-xs text-stone-500">{customer.phone}</p>
                        </div>
                        <nav className="space-y-1">
                            <NavLink href="/account" active={active === 'profile'} icon={<User className="size-4" />}>
                                Профиль
                            </NavLink>
                            <NavLink href="/account/orders" active={active === 'orders'} icon={<ShoppingBag className="size-4" />}>
                                История заказов
                            </NavLink>
                            <NavLink href="/account/bonuses" active={active === 'bonuses'} icon={<Gift className="size-4" />}>
                                Бонусы
                            </NavLink>
                            <NavLink
                                href="/account/notifications"
                                active={active === 'notifications'}
                                icon={<Bell className="size-4" />}
                                badge={unread > 0 ? unread : undefined}
                            >
                                Уведомления
                            </NavLink>
                            <NavLink href="/account/addresses" active={active === 'addresses'} icon={<MapPin className="size-4" />}>
                                Адреса
                            </NavLink>
                            <button
                                type="button"
                                onClick={logout}
                                className="mt-2 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-stone-600 hover:bg-stone-100"
                            >
                                <LogOut className="size-4" />
                                Выйти
                            </button>
                        </nav>
                    </div>
                </aside>

                <section className="space-y-4">
                    {status && (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">
                            {status}
                        </div>
                    )}
                    <div>
                        <h1 className="mb-4 text-2xl font-semibold tracking-tight">{title}</h1>
                        {children}
                    </div>
                </section>
            </main>
        </div>
    );
}

function NavLink({
    href,
    active,
    icon,
    children,
    badge,
}: {
    href: string;
    active: boolean;
    icon: ReactNode;
    children: ReactNode;
    badge?: number;
}) {
    return (
        <Link
            href={href}
            className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition ${
                active ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-100'
            }`}
        >
            {icon}
            <span className="flex-1">{children}</span>
            {badge !== undefined && (
                <span
                    className={`grid h-5 min-w-5 place-items-center rounded-full px-1.5 text-xs font-bold ${
                        active ? 'bg-amber-400 text-stone-900' : 'bg-amber-400 text-stone-900'
                    }`}
                >
                    {badge > 99 ? '99+' : badge}
                </span>
            )}
        </Link>
    );
}
