const config = {!! \Illuminate\Support\Js::from($config) !!};

const VERSION = '{{ $config['version'] }}';
const OFFLINE_URL = '{{ $config['offline_url'] }}';
const STATIC_CACHE = config.cache_names.static;
const IMAGE_CACHE = config.cache_names.images;
const CACHE_PREFIX = config.cache_prefix;
const PRECACHE_URLS = config.precache_urls;
const STATIC_ASSET_PATH_PREFIXES = config.static_asset_path_prefixes;
const STABLE_IMAGE_PATH_PREFIXES = config.stable_image_path_prefixes;
const ACTIVE_CACHE_NAMES = Object.values(config.cache_names);
const FIREBASE_MESSAGING_CONFIG = config.firebase_messaging;
const DEBUG_LOGGING_ENABLED = config.debug_logging === true;
const DEFAULT_PUSH_NOTIFICATION_ICON = '/pwa/icons/icon-192.png';
const DEFAULT_PUSH_NOTIFICATION_BADGE = '/pwa/icons/icon-maskable-192.png';
const RECENT_PUSH_MESSAGE_TTL_MS = 30 * 1000;
let firebaseMessaging = null;
let firebaseMessagingInitialized = false;
const recentlyHandledPushMessages = new Map();

function pushSwInfo(message, context) {
    if (!DEBUG_LOGGING_ENABLED) {
        return;
    }

    if (typeof context === 'undefined') {
        console.info(message);

        return;
    }

    console.info(message, context);
}

function pushSwWarn(message, context) {
    if (!DEBUG_LOGGING_ENABLED) {
        return;
    }

    if (typeof context === 'undefined') {
        console.warn(message);

        return;
    }

    console.warn(message, context);
}

self.importScripts(
    'https://www.gstatic.com/firebasejs/12.12.0/firebase-app-compat.js',
);
self.importScripts(
    'https://www.gstatic.com/firebasejs/12.12.0/firebase-messaging-compat.js',
);

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(STATIC_CACHE);

            await cache.addAll(PRECACHE_URLS);
            self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            if ('navigationPreload' in self.registration) {
                try {
                    await self.registration.navigationPreload.enable();
                } catch (error) {
                    pushSwInfo(
                        '[push-sw] navigation preload skipped',
                        error instanceof Error ? error.message : error,
                    );
                }
            }

            const cacheNames = await caches.keys();

            await Promise.all(
                cacheNames
                    .filter(
                        (cacheName) =>
                            cacheName.startsWith(CACHE_PREFIX) &&
                            !ACTIVE_CACHE_NAMES.includes(cacheName),
                    )
                    .map((cacheName) => caches.delete(cacheName)),
            );

            await self.clients.claim();
        })(),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(event));

        return;
    }

    if (isStaticAssetRequest(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));

        return;
    }

    if (isStableImageRequest(request, url.pathname)) {
        event.respondWith(handleStableImageRequest(event));
    }
});

function isStaticAssetRequest(pathname) {
    return STATIC_ASSET_PATH_PREFIXES.some((prefix) =>
        pathname.startsWith(prefix),
    );
}

function isStableImageRequest(request, pathname) {
    if (request.destination !== 'image') {
        return false;
    }

    return STABLE_IMAGE_PATH_PREFIXES.some((prefix) =>
        pathname.startsWith(prefix),
    );
}

async function handleNavigationRequest(event) {
    try {
        const preloadResponse = await event.preloadResponse;

        if (preloadResponse) {
            return preloadResponse;
        }

        return await fetch(new Request(event.request, { cache: 'no-cache' }));
    } catch {
        const cache = await caches.open(STATIC_CACHE);
        const offlineResponse = await cache.match(OFFLINE_URL);

        if (offlineResponse) {
            return offlineResponse;
        }

        return Response.error();
    }
}

async function handleStableImageRequest(event) {
    const cache = await caches.open(IMAGE_CACHE);
    const cachedResponse = await cache.match(event.request);

    const networkResponsePromise = fetch(event.request)
        .then(async (response) => {
            if (isCacheableResponse(response)) {
                await cache.put(event.request, response.clone());
            }

            return response;
        })
        .catch(() => null);

    if (cachedResponse) {
        event.waitUntil(networkResponsePromise);

        return cachedResponse;
    }

    return (await networkResponsePromise) || Response.error();
}

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    const response = await fetch(request);

    if (isCacheableResponse(response)) {
        await cache.put(request, response.clone());
    }

    return response;
}

function isCacheableResponse(response) {
    return response.ok && response.type === 'basic';
}

function hasValidFirebaseConfig(config) {
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

    return {
        title:
            notification.title || payload.data?.title || 'Soamco Budget',
        options: {
            body: notification.body || payload.data?.body || '',
            icon: notification.icon || DEFAULT_PUSH_NOTIFICATION_ICON,
            badge: notification.badge || DEFAULT_PUSH_NOTIFICATION_BADGE,
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
    const reservationExpiresAt = now + RECENT_PUSH_MESSAGE_TTL_MS;

    pruneRecentlyHandledPushMessages(now);

    try {
        if (recentlyHandledPushMessages.get(deduplicationKey) > now) {
            pushSwInfo('[push-sw] duplicate notification skipped', {
                source,
                deduplicationKey,
                tag: options.tag,
                reason: 'recent-memory',
            });

            return;
        }

        recentlyHandledPushMessages.set(deduplicationKey, reservationExpiresAt);
        pushSwInfo('[push-sw] notification handling reserved', {
            source,
            deduplicationKey,
            tag: options.tag,
        });

        const existingNotifications = await self.registration.getNotifications({
            tag: options.tag,
        });

        if (existingNotifications.length > 0) {
            pushSwInfo('[push-sw] duplicate notification skipped', {
                source,
                deduplicationKey,
                tag: options.tag,
                reason: 'existing-notification',
            });

            return;
        }

        await self.registration.showNotification(title, options);

        pushSwInfo('[push-sw] notification shown', {
            source,
            title,
            url: options.data?.url || '/',
            tag: options.tag,
            deduplicationKey,
        });
    } catch (error) {
        recentlyHandledPushMessages.delete(deduplicationKey);
        pushSwWarn('[push-sw] notification failed', {
            source,
            error,
            deduplicationKey,
        });
    }
}

function initializeFirebaseMessaging(config) {
    if (firebaseMessagingInitialized || !hasValidFirebaseConfig(config)) {
        pushSwInfo('[push-sw] firebase messaging init skipped', {
            firebaseMessagingInitialized,
            hasValidFirebaseConfig: hasValidFirebaseConfig(config),
        });

        return;
    }

    firebase.initializeApp(config);
    firebaseMessaging = firebase.messaging();
    pushSwInfo('[push-sw] firebase messaging initialized');
    firebaseMessaging.onBackgroundMessage(async (payload) => {
        pushSwInfo('[push-sw] background payload received', payload);
        await showNotificationFromPayload(payload, 'firebase-background-message');
    });

    firebaseMessagingInitialized = true;
}

function handlePushSubscriptionChange(event) {
    pushSwInfo('[push-sw] pushsubscriptionchange received', {
        hadOldSubscription: Boolean(event.oldSubscription),
    });

    event.waitUntil(self.registration.update());
}

self.addEventListener('push', (event) => {
    let payload = null;

    try {
        payload = event.data?.json() ?? null;
    } catch (error) {
        pushSwWarn('[push-sw] push payload parse failed', error);
    }

    if (!payload) {
        pushSwInfo('[push-sw] push event received without JSON payload');

        return;
    }

    pushSwInfo('[push-sw] raw push event received', payload);
    event.waitUntil(showNotificationFromPayload(payload, 'service-worker-push'));
});

self.addEventListener('pushsubscriptionchange', handlePushSubscriptionChange);

self.addEventListener('notificationclick', (event) => {
    pushSwInfo('[push-sw] notification click received', event.notification.data ?? {});
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

initializeFirebaseMessaging(FIREBASE_MESSAGING_CONFIG ?? {});
