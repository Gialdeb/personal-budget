/* eslint-disable no-undef */
self.importScripts(
    'https://www.gstatic.com/firebasejs/12.12.0/firebase-app-compat.js',
);
self.importScripts(
    'https://www.gstatic.com/firebasejs/12.12.0/firebase-messaging-compat.js',
);

let messaging = null;
let firebaseInitialized = false;

function hasValidConfig(config) {
    return Object.values(config).every(
        (value) => typeof value === 'string' && value !== '',
    );
}

function buildNotificationPayload(payload) {
    const notification = payload.notification ?? {};
    const title =
        notification.title || payload.data?.title || 'Soamco Budget';

    return {
        title,
        options: {
            body: notification.body || payload.data?.body || '',
            icon: notification.icon || '/pwa-icons/icon-192.png',
            badge: notification.badge || '/pwa-icons/icon-192.png',
            data: {
                url:
                    payload.fcmOptions?.link ||
                    payload.data?.url ||
                    payload.data?.link ||
                    '/',
            },
        },
    };
}

function initializeFirebaseMessaging(config) {
    if (firebaseInitialized || !hasValidConfig(config)) {
        return;
    }

    firebase.initializeApp(config);
    messaging = firebase.messaging();
    messaging.onBackgroundMessage((payload) => {
        const notificationPayload = buildNotificationPayload(payload);

        self.registration.showNotification(
            notificationPayload.title,
            notificationPayload.options,
        );
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
