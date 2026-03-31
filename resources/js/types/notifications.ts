import type { ResourcePaginationLinks, ResourcePaginationMeta } from './admin';

export type NotificationInboxItem = {
    uuid: string;
    type: string;
    category: {
        key: string | null;
        name: string | null;
    };
    presentation: {
        layout: string;
        icon: string;
        image_url: string | null;
    };
    content: {
        title: string | null;
        message: string | null;
        cta_label: string | null;
        cta_url: string | null;
    };
    created_at: string | null;
    read_at: string | null;
    is_read: boolean;
    is_unread: boolean;
};

export type NotificationInboxPreview = {
    unread_count: number;
    latest: NotificationInboxItem[];
    index_url: string;
    preview_url: string;
    mark_all_read_url: string;
};

export type PaginatedNotificationInbox = {
    data: NotificationInboxItem[];
    links: ResourcePaginationLinks;
    meta: ResourcePaginationMeta;
};

export type NotificationsPageProps = {
    notifications: PaginatedNotificationInbox;
    summary: {
        unread_count: number;
    };
};

export type NotificationInboxRealtimePayload = {
    unread_count: number;
    notification: NotificationInboxItem;
};
