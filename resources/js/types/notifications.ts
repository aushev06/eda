export type NotificationType =
    | 'promo'
    | 'loyalty_earn'
    | 'loyalty_spend'
    | 'loyalty_level_up'
    | 'order_status'
    | 'system';

export type NotificationItem = {
    id: number;
    type: NotificationType;
    title: string;
    body: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationsShare = {
    recent: NotificationItem[];
    unread_count: number;
} | null;
