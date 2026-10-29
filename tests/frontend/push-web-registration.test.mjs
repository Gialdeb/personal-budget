import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const pushLibrarySource = readFileSync(
    new URL('../../resources/js/lib/push-notifications.ts', import.meta.url),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const serviceWorkerSource = readFileSync(
    new URL(
        '../../resources/views/pwa/service-worker.blade.php',
        import.meta.url,
    ),
    'utf8',
);
const legacyFirebaseMessagingWorkerSource = readFileSync(
    new URL('../../public/firebase-messaging-sw.js', import.meta.url),
    'utf8',
);

test('push notifications library reads firebase config from Vite env and registers the FCM service worker', () => {
    assert.match(pushLibrarySource, /VITE_FIREBASE_API_KEY/);
    assert.match(pushLibrarySource, /VITE_FIREBASE_VAPID_PUBLIC_KEY/);
    assert.match(pushLibrarySource, /navigator\.serviceWorker\.register/);
    assert.match(pushLibrarySource, /\/service-worker\.js/);
    assert.match(pushLibrarySource, /getToken\(messaging,/);
    assert.match(pushLibrarySource, /serviceWorkerRegistration,\s*\n\s*}\);/);
});

test('push notifications library requests notification permission only when needed and clears persisted tokens on disable', () => {
    assert.match(pushLibrarySource, /Notification\.requestPermission\(\)/);
    assert.match(pushLibrarySource, /CURRENT_PUSH_TOKEN_STORAGE_KEY/);
    assert.match(
        pushLibrarySource,
        /CURRENT_PUSH_DEVICE_IDENTIFIER_STORAGE_KEY/,
    );
    assert.match(pushLibrarySource, /deleteToken\(messaging\)/);
    assert.match(pushLibrarySource, /clearPersistedCurrentPushToken/);
    assert.match(pushLibrarySource, /getOrCreatePushDeviceIdentifier/);
    assert.match(pushLibrarySource, /getRegistrations\(\)/);
    assert.match(pushLibrarySource, /firebase-cloud-messaging-push-scope/);
    assert.match(
        pushLibrarySource,
        /cleanupLegacyFirebaseMessagingServiceWorker/,
    );
    assert.match(
        pushLibrarySource,
        /isFirebaseMessagingServiceWorkerRegistration/,
    );
    assert.match(pushLibrarySource, /readCurrentPushDeviceContext/);
    assert.match(pushLibrarySource, /hasPendingServiceWorkerRegistration/);
    assert.match(
        pushLibrarySource,
        /waitForStableFirebaseMessagingServiceWorker/,
    );
    assert.match(
        pushLibrarySource,
        /SERVICE_WORKER_STABILITY_TIMEOUT_MS = 15000/,
    );
    assert.match(pushLibrarySource, /waitForWorkerStateChange/);
    assert.match(pushLibrarySource, /addEventListener\('statechange'/);
    assert.match(pushLibrarySource, /Promise\.race\(\[/);
    assert.match(pushLibrarySource, /push-service-worker-not-active/);
    assert.match(
        pushLibrarySource,
        /isRecoverableServiceWorkerActivationError/,
    );
    assert.match(pushLibrarySource, /serviceWorkerRegistrationPromise = null/);
    assert.match(
        pushLibrarySource,
        /foreground listener deferred: service worker not active yet/,
    );
    assert.match(pushLibrarySource, /cleanupCurrentBrowserPushRegistration/);
    assert.match(pushLibrarySource, /PUSH_DEBUG_STORAGE_KEY/);
    assert.match(pushLibrarySource, /PUSH_DEBUG_META_SELECTOR/);
    assert.match(pushLibrarySource, /pushDebugEnabled/);
    assert.match(pushLibrarySource, /pushInfo/);
    assert.match(pushLibrarySource, /pushWarn/);
    assert.match(pushLibrarySource, /registration failed - storage error/);
    assert.match(pushLibrarySource, /PUSH_STORAGE_ERROR_RECOVERY_DELAY_MS/);
    assert.match(pushLibrarySource, /onMessage\(messaging,/);
    assert.match(pushLibrarySource, /initializeForegroundPushNotifications/);
    assert.match(
        pushLibrarySource,
        /synchronizeCurrentBrowserPushRegistration/,
    );
    assert.match(pushLibrarySource, /foreground payload received/);
    assert.match(pushLibrarySource, /fcmMessageId\?: string/);
    assert.match(pushLibrarySource, /duplicate foreground payload skipped/);
    assert.match(pushLibrarySource, /registration\.getNotifications\(/);
    assert.match(pushLibrarySource, /tag: identity\.tag/);
    assert.match(pushLibrarySource, /browser registration synchronized/);
    assert.match(pushLibrarySource, /service worker update detected/);
    assert.match(pushLibrarySource, /showNotification\(/);
    assert.match(
        pushLibrarySource,
        /DEFAULT_PUSH_NOTIFICATION_ICON = '\/pwa\/icons\/icon-192\.png'/,
    );
    assert.match(
        pushLibrarySource,
        /DEFAULT_PUSH_NOTIFICATION_BADGE = '\/pwa\/icons\/icon-maskable-192\.png'/,
    );
});

test('application bootstrap synchronizes the current browser push registration without relying on the profile toggle UI', () => {
    assert.match(appSource, /cleanupLegacyFirebaseMessagingServiceWorker/);
    assert.match(appSource, /synchronizeCurrentBrowserPushRegistration/);
    assert.match(appSource, /storePushTokenAction\(\)\.url/);
    assert.match(appSource, /destroyPushTokenAction\(\)\.url/);
    assert.match(appSource, /push_notifications_enabled/);
    assert.match(appSource, /initializeForegroundPushNotifications\(\)/);
    assert.match(appSource, /post-render initialization skipped/);
    assert.match(appSource, /await import\('@\/lib\/push-notifications'\)/);
});

test('legacy firebase messaging worker is a cleanup shim and no longer initializes firebase messaging', () => {
    assert.match(
        legacyFirebaseMessagingWorkerSource,
        /legacy-firebase-messaging-sw-unregistered/,
    );
    assert.match(
        legacyFirebaseMessagingWorkerSource,
        /self\.registration\.unregister\(\)/,
    );
    assert.match(
        legacyFirebaseMessagingWorkerSource,
        /INIT_FIREBASE_MESSAGING/,
    );
    assert.doesNotMatch(
        legacyFirebaseMessagingWorkerSource,
        /firebase-messaging-compat/,
    );
    assert.doesNotMatch(
        legacyFirebaseMessagingWorkerSource,
        /onBackgroundMessage/,
    );
    assert.doesNotMatch(
        legacyFirebaseMessagingWorkerSource,
        /showNotification\(/,
    );
});

test('the root service worker initializes Firebase from a postMessage config and opens the target url on notification click', () => {
    assert.match(
        serviceWorkerSource,
        /firebasejs\/12\.12\.0\/firebase-app-compat\.js/,
    );
    assert.match(
        serviceWorkerSource,
        /FIREBASE_MESSAGING_CONFIG = config\.firebase_messaging/,
    );
    assert.match(
        serviceWorkerSource,
        /DEBUG_LOGGING_ENABLED = config\.debug_logging === true/,
    );
    assert.match(serviceWorkerSource, /pushSwInfo/);
    assert.match(serviceWorkerSource, /pushSwWarn/);
    assert.match(serviceWorkerSource, /onBackgroundMessage/);
    assert.match(serviceWorkerSource, /addEventListener\('push'/);
    assert.match(
        serviceWorkerSource,
        /addEventListener\('pushsubscriptionchange'/,
    );
    assert.match(serviceWorkerSource, /showNotification/);
    assert.match(serviceWorkerSource, /background payload received/);
    assert.match(serviceWorkerSource, /payload\.fcmMessageId/);
    assert.match(serviceWorkerSource, /raw push event received/);
    assert.match(
        serviceWorkerSource,
        /if \(firebaseMessagingInitialized\) \{[\s\S]*raw push event delegated to Firebase Messaging/,
    );
    assert.match(serviceWorkerSource, /duplicate notification skipped/);
    assert.match(serviceWorkerSource, /deduplicationKey/);
    assert.match(serviceWorkerSource, /resolvePushDiagnosticBranch/);
    assert.match(serviceWorkerSource, /buildPushDiagnosticContext/);
    assert.match(
        serviceWorkerSource,
        /timestamp: new Date\(\)\.toISOString\(\)/,
    );
    assert.match(
        serviceWorkerSource,
        /branch: resolvePushDiagnosticBranch\(source\)/,
    );
    assert.match(
        serviceWorkerSource,
        /broadcast_uuid: payload\.data\?\.broadcast_uuid \?\? null/,
    );
    assert.match(
        serviceWorkerSource,
        /fcmMessageId: payload\.fcmMessageId \?\? null/,
    );
    assert.match(serviceWorkerSource, /return 'fcm-background'/);
    assert.match(serviceWorkerSource, /return 'raw-push'/);
    assert.match(serviceWorkerSource, /notification handling reserved/);
    assert.match(
        serviceWorkerSource,
        /PUSH_DEDUP_CACHE = 'soamco-push-dedup-v1'/,
    );
    assert.match(
        serviceWorkerSource,
        /const inFlightPushReservations = new Map\(\)/,
    );
    assert.match(serviceWorkerSource, /pruneInFlightPushReservations/);
    assert.match(serviceWorkerSource, /readRecentPushDedupReservation/);
    assert.match(serviceWorkerSource, /reserveRecentPushDedup/);
    assert.match(serviceWorkerSource, /clearRecentPushDedupReservation/);
    assert.match(
        serviceWorkerSource,
        /inFlightPushReservations\.set\(deduplicationKey, reservationExpiresAt\)/,
    );
    assert.match(
        serviceWorkerSource,
        /inFlightPushReservations\.get\(deduplicationKey\) > now/,
    );
    assert.match(serviceWorkerSource, /reason: 'in-flight-memory'/);
    assert.match(
        serviceWorkerSource,
        /inFlightPushReservations\.delete\(deduplicationKey\)/,
    );
    assert.match(serviceWorkerSource, /reason: 'recent-cache'/);
    assert.match(
        serviceWorkerSource,
        /reservedBy: existingReservation\.source/,
    );
    assert.match(serviceWorkerSource, /dedup reservation write failed/);
    assert.match(
        serviceWorkerSource,
        /recentlyHandledPushMessages\.set\(deduplicationKey, reservationExpiresAt\)/,
    );
    assert.match(serviceWorkerSource, /notification shown/);
    assert.match(
        serviceWorkerSource,
        /DEFAULT_PUSH_NOTIFICATION_ICON = '\/pwa\/icons\/icon-192\.png'/,
    );
    assert.match(
        serviceWorkerSource,
        /DEFAULT_PUSH_NOTIFICATION_BADGE = '\/pwa\/icons\/icon-maskable-192\.png'/,
    );
    assert.match(serviceWorkerSource, /skipWaiting/);
    assert.match(serviceWorkerSource, /clients\.claim\(\)/);
    assert.match(serviceWorkerSource, /claimClientsWhenActive/);
    assert.match(
        serviceWorkerSource,
        /activeWorker\.scriptURL !== self\.location\.href/,
    );
    assert.match(
        serviceWorkerSource,
        /clients claim skipped: worker not active/,
    );
    assert.match(
        serviceWorkerSource,
        /try \{\s*await self\.clients\.claim\(\);/,
    );
    assert.match(serviceWorkerSource, /clients claim failed/);
    assert.doesNotMatch(serviceWorkerSource, /INIT_FIREBASE_MESSAGING/);
    assert.match(
        serviceWorkerSource,
        /initializeFirebaseMessaging\(FIREBASE_MESSAGING_CONFIG \?\? {}\)/,
    );
    assert.match(serviceWorkerSource, /clients\.openWindow/);
});

for (const sdkAvailable of [true, false]) {
    for (const badgeSupported of [true, false]) {
        test(`background data push displays without an open page (SDK: ${sdkAvailable}, badge: ${badgeSupported})`, async () => {
            const listeners = {};
            const shown = [];
            let releaseBadge;
            const badgeFetch = new Promise((resolve) => {
                releaseBadge = resolve;
            });
            const cache = new Map();
            let backgroundHandler;
            const config = {
                cache_names: {},
                precache_urls: [],
                notification_inbox_url: '/notifications/preview',
                firebase_messaging: {
                    apiKey: 'test',
                    projectId: 'test',
                    appId: 'test',
                    messagingSenderId: 'test',
                },
            };
            const script = serviceWorkerSource
                .replace(/'{{ \$config\['version'\] }}'/g, "'test'")
                .replace(
                    /'{{ \$config\['offline_url'\] }}'/g,
                    "'/offline.html'",
                )
                .replace(
                    /^const config = .*;$/m,
                    `const config = ${JSON.stringify(config)};`,
                )
                .replace(
                    /\{!! .*file_get_contents.* !!\}/g,
                    JSON.stringify('offline'),
                );
            runInNewContext(script, {
                console,
                Response,
                URL,
                fetch: () => badgeFetch,
                firebase: sdkAvailable
                    ? {
                          initializeApp() {},
                          messaging: () => ({
                              onBackgroundMessage(handler) {
                                  backgroundHandler = handler;
                              },
                          }),
                      }
                    : undefined,
                self: {
                    location: new URL('https://soamco.test/service-worker.js'),
                    navigator: {
                        onLine: true,
                        ...(badgeSupported
                            ? {
                                  setAppBadge: async () => {
                                      throw new Error('badge denied');
                                  },
                                  clearAppBadge: async () => {},
                              }
                            : {}),
                    },
                    importScripts() {
                        if (!sdkAvailable) {
                            throw new Error('offline SDK');
                        }
                    },
                    addEventListener: (name, handler) => {
                        listeners[name] = handler;
                    },
                    registration: {
                        getNotifications: async () => [],
                        showNotification: async (title, options) => {
                            shown.push({ title, options });
                        },
                    },
                },
                caches: {
                    open: async () => ({
                        match: async (key) => cache.get(key)?.clone(),
                        put: async (key, value) => {
                            cache.set(key, value);
                        },
                        delete: async (key) => cache.delete(key),
                    }),
                },
            });
            const payload = {
                data: {
                    title: 'Promemoria',
                    body: 'Test locale',
                    url: '/dashboard',
                    broadcast_uuid: 'test-id',
                },
            };
            const pending = [];
            listeners.push({
                data: { json: () => payload },
                waitUntil: (promise) => pending.push(promise),
            });

            if (sdkAvailable) {
                assert.equal(pending.length, badgeSupported ? 1 : 0);
                await backgroundHandler(payload);
            } else {
                assert.equal(pending.length, badgeSupported ? 2 : 1);
                await pending[pending.length - 1];
            }

            assert.equal(shown.length, 1);
            assert.equal(shown[0].title, 'Promemoria');
            assert.equal(shown[0].options.body, 'Test locale');
            assert.equal(shown[0].options.data.url, '/dashboard');
            releaseBadge({ ok: true, json: async () => ({ unread_count: 3 }) });
            await Promise.all(pending);
            assert.equal(shown.length, 1);
        });
    }
}
