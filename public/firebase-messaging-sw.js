/* eslint-disable no-undef */
self.importScripts(
    'https://www.gstatic.com/firebasejs/12.12.0/firebase-app-compat.js',
);
self.importScripts(
    'https://www.gstatic.com/firebasejs/12.12.0/firebase-messaging-compat.js',
);

let messaging = null;
let firebaseInitialized = false;
const RECENT_PUSH_MESSAGE_TTL_MS = 30 * 1000;
const recentlyHandledPushMessages = new Map();

function hasValidConfig(config) {
    return Object.values(config).every(
        (value) => typeof value === 'string' && value !== '',
    );
}

function pruneRecentlyHandledPushMessages(now) {
    for (const [key, expiresAt] of recentlyHandledPushMessages.entries()) {
        if (expiresAt <= now) {
            recentlyHandledPushMessages.delete(key);
        }
    }
}

function resolvePushNotificationIdentity(payload) {
    const notification = payload.notification ?? {};
    const url =
        payload.fcmOptions?.link || payload.data?.url || payload.data?.link || '/';
    const title =
        notification.title || payload.data?.title || 'Soamco Budget';
    const body = notification.body || payload.data?.body || '';
    const rawKey =
        payload.data?.broadcast_uuid ||
        payload.fcmMessageId ||
        payload.notification?.tag ||
        payload.data?.tag ||
        `${url}:${title}:${body}`;
    const deduplicationKey = String(rawKey).trim() || `${url}:${title}:${body}`;

    return {
        deduplicationKey,
        tag: `push:${deduplicationKey}`,
        url,
    };
}

function buildNotificationPayload(payload) {
    const notification = payload.notification ?? {};
    const identity = resolvePushNotificationIdentity(payload);
    const title =
        notification.title || payload.data?.title || 'Soamco Budget';

    return {
        title,
        options: {
            body: notification.body || payload.data?.body || '',
            icon: notification.icon || '/pwa-icons/icon-192.png',
            badge: notification.badge || '/pwa-icons/icon-192.png',
            data: {
                url: identity.url,
                deduplicationKey: identity.deduplicationKey,
            },
            tag: identity.tag,
        },
    };
}

async function showNotificationFromPayload(payload, source) {
    const notificationPayload = buildNotificationPayload(payload);
    const { title, options } = notificationPayload;
    const deduplicationKey =
        String(options.data?.deduplicationKey || '').trim() || options.tag;
    const now = Date.now();
    void source;

    pruneRecentlyHandledPushMessages(now);

    if (recentlyHandledPushMessages.get(deduplicationKey) > now) {
        return;
    }

    const existingNotifications = await self.registration.getNotifications({
        tag: options.tag,
    });

    if (existingNotifications.length > 0) {
        recentlyHandledPushMessages.set(
            deduplicationKey,
            now + RECENT_PUSH_MESSAGE_TTL_MS,
        );

        return;
    }

    await self.registration.showNotification(title, options);
    recentlyHandledPushMessages.set(
        deduplicationKey,
        now + RECENT_PUSH_MESSAGE_TTL_MS,
    );
}

function initializeFirebaseMessaging(config) {
    if (firebaseInitialized || !hasValidConfig(config)) {
        return;
    }

    firebase.initializeApp(config);
    messaging = firebase.messaging();
    messaging.onBackgroundMessage(async (payload) => {
        await showNotificationFromPayload(payload, 'firebase-background-message');
    });

    firebaseInitialized = true;
}

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    if (event.data?.type !== 'INIT_FIREBASE_MESSAGING') {
        return;
    }

    initializeFirebaseMessaging(event.data.config ?? {});
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const matchingClient = clients.find((client) => client.url === targetUrl);

            if (matchingClient) {
                return matchingClient.focus();
            }

            return self.clients.openWindow(targetUrl);
        }),
    );
});
