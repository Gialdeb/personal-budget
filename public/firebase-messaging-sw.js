/* eslint-disable no-restricted-globals */
const LEGACY_FIREBASE_MESSAGING_UNREGISTERED_EVENT =
    'legacy-firebase-messaging-sw-unregistered';

async function unregisterLegacyWorker() {
    try {
        await self.registration.unregister();
    } catch {
        // Ignore unregister failures; the app bootstrap also performs cleanup.
    }

    const clients = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });

    for (const client of clients) {
        client.postMessage({
            type: LEGACY_FIREBASE_MESSAGING_UNREGISTERED_EVENT,
        });
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(unregisterLegacyWorker());
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'INIT_FIREBASE_MESSAGING') {
        event.waitUntil(unregisterLegacyWorker());
    }
});
