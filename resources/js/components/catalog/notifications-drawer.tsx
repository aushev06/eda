import { Link, router } from '@inertiajs/react';
import { Bell, BellRing, Gift, Sparkles, TrendingUp, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import type { NotificationItem, NotificationType } from '@/types/notifications';

type Props = {
    open: boolean;
    onClose: () => void;
    items: NotificationItem[];
    unreadCount: number;
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

export function NotificationsDrawer({ open, onClose, items, unreadCount }: Props) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    useEffect(() => {
        if (!open) {
return;
}

        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') {
onClose();
}
        }
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    // Lock body scroll while the drawer is open so the background doesn't
    // shift behind the overlay.
    useEffect(() => {
        if (!open) {
return;
}

        const original = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = original;
        };
    }, [open]);

    function markAllRead() {
        router.post(
            '/account/notifications/read-all',
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    function openItem(item: NotificationItem) {
        if (item.read_at === null) {
            router.post(
                `/account/notifications/${item.id}/read`,
                {},
                { preserveScroll: true, preserveState: true, only: ['notifications'] },
            );
        }

        if (item.action_url) {
            router.visit(item.action_url);
            onClose();
        }
    }

    if (!mounted) {
return null;
}

    return createPortal(
        <div className="pointer-events-none fixed inset-0 z-[100]" aria-hidden={!open}>
            <div
                onClick={onClose}
                className={`absolute inset-0 bg-stone-950/60 transition-opacity duration-300 ease-out ${
                    open ? 'pointer-events-auto opacity-100' : 'opacity-0'
                }`}
            />

            <aside
                role="dialog"
                aria-modal="true"
                aria-label="Уведомления"
                className={`absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-[0_25px_50px_-12px_rgba(0,0,0,0.45)] ring-1 ring-black/5 transition-transform duration-300 ease-out will-change-transform ${
                    open ? 'pointer-events-auto translate-x-0' : 'translate-x-full'
                }`}
            >
                <header className="flex items-center justify-between border-b border-stone-200 bg-white px-5 py-4">
                    <div>
                        <p className="text-base font-semibold text-stone-900">Уведомления</p>
                        {unreadCount > 0 && (
                            <p className="text-xs text-stone-500">{unreadCount} непрочитанных</p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllRead}
                                className="rounded-full px-3 py-1.5 text-xs font-medium text-stone-600 hover:bg-stone-100"
                            >
                                Прочитать всё
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onClose}
                            className="grid size-9 place-items-center rounded-full text-stone-500 hover:bg-stone-100"
                            aria-label="Закрыть"
                        >
                            <X className="size-5" />
                        </button>
                    </div>
                </header>

                <div className="flex-1 overflow-y-auto overscroll-contain">
                    {items.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-stone-500">
                            Пока пусто. Здесь появятся персональные промокоды, начисления бонусов и важные события.
                        </div>
                    ) : (
                        <ul className="divide-y divide-stone-100">
                            {items.map((item) => {
                                const Icon = ICONS[item.type] ?? Bell;
                                const unread = item.read_at === null;

                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() => openItem(item)}
                                            className={`flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-stone-50 ${
                                                unread ? 'bg-amber-50/40' : ''
                                            }`}
                                        >
                                            <span
                                                className={`mt-0.5 grid size-9 shrink-0 place-items-center rounded-full ${COLORS[item.type] ?? COLORS.system}`}
                                            >
                                                <Icon className="size-4" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="flex items-center gap-2 text-sm font-semibold text-stone-900">
                                                    <span className="truncate">{item.title}</span>
                                                    {unread && (
                                                        <span className="inline-flex size-1.5 shrink-0 rounded-full bg-amber-500" />
                                                    )}
                                                </p>
                                                {item.body && (
                                                    <p className="mt-0.5 text-sm text-stone-600">{item.body}</p>
                                                )}
                                                {item.created_at && (
                                                    <p className="mt-1 text-xs text-stone-400">
                                                        {formatRelative(item.created_at)}
                                                    </p>
                                                )}
                                            </div>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                <footer className="border-t border-stone-200 bg-white px-5 py-3 text-center">
                    <Link
                        href="/account/notifications"
                        onClick={onClose}
                        className="text-sm font-medium text-stone-700 hover:text-stone-900"
                    >
                        Все уведомления
                    </Link>
                </footer>
            </aside>
        </div>,
        document.body,
    );
}

function formatRelative(iso: string): string {
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const diffMin = Math.round(diffMs / 60000);

    if (diffMin < 1) {
return 'только что';
}

    if (diffMin < 60) {
return `${diffMin} мин назад`;
}

    const diffHrs = Math.round(diffMin / 60);

    if (diffHrs < 24) {
return `${diffHrs} ч назад`;
}

    const diffDays = Math.round(diffHrs / 24);

    if (diffDays < 7) {
return `${diffDays} дн назад`;
}

    return date.toLocaleDateString('ru-RU');
}
