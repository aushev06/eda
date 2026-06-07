import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import type { NotificationsShare } from '@/types/notifications';
import { NotificationsDrawer } from './notifications-drawer';

type SharedProps = {
    auth: { customer: { id: number; name: string } | null };
    notifications: NotificationsShare;
};

export function NotificationsBell() {
    const { auth, notifications } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);

    function onClick() {
        if (!auth.customer) {
            router.visit('/account/login');
            return;
        }
        setOpen(true);
    }

    const unread = notifications?.unread_count ?? 0;

    return (
        <>
            <button
                type="button"
                onClick={onClick}
                className="relative grid size-10 place-items-center rounded-full bg-stone-100 text-stone-700 transition hover:bg-stone-200"
                aria-label="Уведомления"
            >
                <Bell className="size-5" />
                {unread > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-amber-400 px-1 text-xs font-bold text-stone-900 ring-2 ring-white">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>

            {auth.customer && notifications && (
                <NotificationsDrawer
                    open={open}
                    onClose={() => setOpen(false)}
                    items={notifications.recent}
                    unreadCount={notifications.unread_count}
                />
            )}
        </>
    );
}
