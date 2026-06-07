import { Head, Link, router } from '@inertiajs/react';
import { Bell, BellRing, Gift, Sparkles, TrendingUp } from 'lucide-react';
import { AccountLayout } from '@/components/account/account-layout';
import type { NotificationItem, NotificationType } from '@/types/notifications';

type Props = {
    notifications: NotificationItem[];
};

const ICONS: Record<NotificationType, typeof Bell> = {
    promo: Gift,
    loyalty_earn: Sparkles,
    loyalty_spend: Sparkles,
    loyalty_level_up: TrendingUp,
    order_status: BellRing,
    system: Bell,
};

const COLORS: Record<NotificationType, string> = {
    promo: 'bg-amber-50 text-amber-600',
    loyalty_earn: 'bg-emerald-50 text-emerald-600',
    loyalty_spend: 'bg-orange-50 text-orange-600',
    loyalty_level_up: 'bg-violet-50 text-violet-600',
    order_status: 'bg-sky-50 text-sky-600',
    system: 'bg-stone-100 text-stone-600',
};

export default function AccountNotifications({ notifications }: Props) {
    const hasUnread = notifications.some((n) => n.read_at === null);

    function markAllRead() {
        router.post('/account/notifications/read-all', {}, { preserveScroll: true });
    }

    function markRead(item: NotificationItem) {
        if (item.read_at === null) {
            router.post(`/account/notifications/${item.id}/read`, {}, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Уведомления" />
            <AccountLayout title="Уведомления" active="notifications">
                {notifications.length === 0 ? (
                    <div className="grid place-items-center rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
                        <Bell className="size-10 text-stone-300" />
                        <p className="mt-3 text-sm text-stone-500">
                            Пока пусто. Здесь появятся персональные промокоды и важные события.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {hasUnread && (
                            <div className="flex justify-end">
                                <button
                                    type="button"
                                    onClick={markAllRead}
                                    className="rounded-full bg-stone-100 px-4 py-1.5 text-xs font-medium text-stone-700 hover:bg-stone-200"
                                >
                                    Прочитать всё
                                </button>
                            </div>
                        )}
                        <ul className="space-y-2">
                            {notifications.map((item) => {
                                const Icon = ICONS[item.type] ?? Bell;
                                const unread = item.read_at === null;
                                return (
                                    <li key={item.id}>
                                        <div
                                            className={`flex items-start gap-3 rounded-2xl border bg-white p-4 shadow-sm transition ${
                                                unread
                                                    ? 'border-amber-200 ring-1 ring-amber-100'
                                                    : 'border-stone-200'
                                            }`}
                                        >
                                            <span
                                                className={`mt-0.5 grid size-10 shrink-0 place-items-center rounded-full ${COLORS[item.type] ?? COLORS.system}`}
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="flex items-center gap-2 text-sm font-semibold text-stone-900">
                                                    <span className="truncate">{item.title}</span>
                                                    {unread && (
                                                        <span className="inline-flex size-1.5 shrink-0 rounded-full bg-amber-500" />
                                                    )}
                                                </p>
                                                {item.body && (
                                                    <p className="mt-1 text-sm text-stone-600">{item.body}</p>
                                                )}
                                                {item.created_at && (
                                                    <p className="mt-2 text-xs text-stone-400">
                                                        {new Date(item.created_at).toLocaleString('ru-RU', {
                                                            day: '2-digit',
                                                            month: '2-digit',
                                                            year: 'numeric',
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        })}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 flex-col items-end gap-2">
                                                {item.action_url && (
                                                    <Link
                                                        href={item.action_url}
                                                        onClick={() => markRead(item)}
                                                        className="rounded-full bg-stone-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-stone-800"
                                                    >
                                                        Открыть
                                                    </Link>
                                                )}
                                                {unread && (
                                                    <button
                                                        type="button"
                                                        onClick={() => markRead(item)}
                                                        className="text-xs text-stone-500 hover:text-stone-800"
                                                    >
                                                        Прочитано
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </AccountLayout>
        </>
    );
}
