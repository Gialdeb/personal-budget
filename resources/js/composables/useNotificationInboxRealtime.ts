import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, readonly, ref, watch } from 'vue';
import { listenOnPrivateChannel } from '@/lib/realtime/echo';
import type {
    Auth,
    NotificationInboxItem,
    NotificationInboxPreview,
    NotificationInboxRealtimePayload,
} from '@/types';

const DEFAULT_NOTIFICATION_INBOX: NotificationInboxPreview = {
    unread_count: 0,
    latest: [],
    index_url: '/notifications',
    preview_url: '/notifications/preview',
    mark_all_read_url: '/notifications/mark-all-read',
};

const notificationInboxState = ref<NotificationInboxPreview>({
    ...DEFAULT_NOTIFICATION_INBOX,
});
const notificationsPageState = ref<NotificationInboxItem[] | null>(null);
const notificationsPageUnreadCountState = ref<number | null>(null);

let realtimeSubscriptionCount = 0;
let activeRealtimeUserUuid: string | null = null;
let unsubscribeFromRealtime: (() => void) | null = null;

function cloneInboxPreview(
    preview: NotificationInboxPreview | null,
): NotificationInboxPreview {
    if (preview === null) {
        return {
            ...DEFAULT_NOTIFICATION_INBOX,
            latest: [],
        };
    }

    return {
        unread_count: preview.unread_count,
        latest: [...preview.latest],
        index_url: preview.index_url,
        preview_url: preview.preview_url,
        mark_all_read_url: preview.mark_all_read_url,
    };
}

function dedupeNotifications(
    notifications: NotificationInboxItem[],
): NotificationInboxItem[] {
    const seen = new Set<string>();

    return notifications.filter((notification) => {
        if (seen.has(notification.uuid)) {
            return false;
        }

        seen.add(notification.uuid);

        return true;
    });
}

function mergeNotificationIntoList(
    notifications: NotificationInboxItem[],
    notification: NotificationInboxItem,
    limit?: number,
): NotificationInboxItem[] {
    const merged = dedupeNotifications([notification, ...notifications]);

    if (limit === undefined) {
        return merged;
    }

    return merged.slice(0, limit);
}

function ensureRealtimeSubscription(userUuid: string | null): void {
    if (activeRealtimeUserUuid === userUuid) {
        return;
    }

    unsubscribeFromRealtime?.();
    unsubscribeFromRealtime = null;
    activeRealtimeUserUuid = null;

    if (!userUuid) {
        return;
    }

    unsubscribeFromRealtime = listenOnPrivateChannel<NotificationInboxRealtimePayload>(
        `users.${userUuid}.notifications`,
        'notification.inbox.updated',
        applyRealtimeNotificationUpdate,
    );
    activeRealtimeUserUuid = userUuid;
}

function replaceNotificationPreview(
    payload: Pick<NotificationInboxPreview, 'unread_count' | 'latest'>,
): void {
    notificationInboxState.value = {
        ...notificationInboxState.value,
        unread_count: payload.unread_count,
        latest: [...payload.latest],
    };

    if (notificationsPageUnreadCountState.value !== null) {
        notificationsPageUnreadCountState.value = payload.unread_count;
    }
}

function applyRealtimeNotificationUpdate(
    payload: NotificationInboxRealtimePayload,
): void {
    notificationInboxState.value = {
        ...notificationInboxState.value,
        unread_count: payload.unread_count,
        latest: mergeNotificationIntoList(
            notificationInboxState.value.latest,
            payload.notification,
            6,
        ),
    };

    if (notificationsPageState.value !== null) {
        notificationsPageState.value = mergeNotificationIntoList(
            notificationsPageState.value,
            payload.notification,
        );
    }

    if (notificationsPageUnreadCountState.value !== null) {
        notificationsPageUnreadCountState.value = payload.unread_count;
    }
}

function markNotificationReadLocally(notificationUuid: string): void {
    notificationInboxState.value = {
        ...notificationInboxState.value,
        latest: notificationInboxState.value.latest.map((notification) =>
            notification.uuid === notificationUuid
                ? {
                      ...notification,
                      is_read: true,
                      is_unread: false,
                      read_at: notification.read_at ?? new Date().toISOString(),
                  }
                : notification,
        ),
    };

    if (notificationsPageState.value !== null) {
        notificationsPageState.value = notificationsPageState.value.map(
            (notification) =>
                notification.uuid === notificationUuid
                    ? {
                          ...notification,
                          is_read: true,
                          is_unread: false,
                          read_at:
                              notification.read_at ?? new Date().toISOString(),
                      }
                    : notification,
        );
    }
}

function markAllNotificationsReadLocally(): void {
    const readAt = new Date().toISOString();

    notificationInboxState.value = {
        ...notificationInboxState.value,
        latest: notificationInboxState.value.latest.map((notification) => ({
            ...notification,
            is_read: true,
            is_unread: false,
            read_at: notification.read_at ?? readAt,
        })),
    };

    if (notificationsPageState.value !== null) {
        notificationsPageState.value = notificationsPageState.value.map(
            (notification) => ({
                ...notification,
                is_read: true,
                is_unread: false,
                read_at: notification.read_at ?? readAt,
            }),
        );
    }

    if (notificationsPageUnreadCountState.value !== null) {
        notificationsPageUnreadCountState.value = 0;
    }
}

function syncNotificationsPage(
    notifications: NotificationInboxItem[],
    unreadCount: number,
): void {
    notificationsPageState.value = [...notifications];
    notificationsPageUnreadCountState.value = unreadCount;
}

function resetNotificationsPage(): void {
    notificationsPageState.value = null;
    notificationsPageUnreadCountState.value = null;
}

export function useNotificationInboxRealtime() {
    const page = usePage();
    const auth = computed(() => page.props.auth as Auth);
    const sharedNotificationInbox = computed(
        () =>
            (page.props.notificationInbox ??
                null) as NotificationInboxPreview | null,
    );

    watch(
        sharedNotificationInbox,
        (value) => {
            notificationInboxState.value = cloneInboxPreview(value);
        },
        { immediate: true, deep: true },
    );

    watch(
        () => auth.value.user?.uuid ?? null,
        (userUuid) => {
            ensureRealtimeSubscription(userUuid);
        },
        { immediate: true },
    );

    onMounted(() => {
        realtimeSubscriptionCount += 1;
    });

    onBeforeUnmount(() => {
        realtimeSubscriptionCount = Math.max(0, realtimeSubscriptionCount - 1);

        if (realtimeSubscriptionCount === 0) {
            unsubscribeFromRealtime?.();
            unsubscribeFromRealtime = null;
            activeRealtimeUserUuid = null;
            resetNotificationsPage();
        }
    });

    return {
        notificationInbox: readonly(notificationInboxState),
        unreadNotificationsCount: computed(
            () => notificationInboxState.value.unread_count,
        ),
        unreadPreviewNotifications: computed(() =>
            notificationInboxState.value.latest.filter(
                (notification) => notification.is_unread,
            ),
        ),
        notificationsPage: readonly(notificationsPageState),
        notificationsPageUnreadCount: readonly(
            notificationsPageUnreadCountState,
        ),
        replaceNotificationPreview,
        markNotificationReadLocally,
        markAllNotificationsReadLocally,
        syncNotificationsPage,
        resetNotificationsPage,
    };
}
