import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pushLibrarySource = readFileSync(
    new URL('../../resources/js/lib/push-notifications.ts', import.meta.url),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const serviceWorkerSource = readFileSync(
    new URL('../../resources/views/pwa/service-worker.blade.php', import.meta.url),
    'utf8',
);

test('push notifications library reads firebase config from Vite env and registers the FCM service worker', () => {
    assert.match(pushLibrarySource, /VITE_FIREBASE_API_KEY/);
    assert.match(pushLibrarySource, /VITE_FIREBASE_VAPID_PUBLIC_KEY/);
    assert.match(pushLibrarySource, /navigator\.serviceWorker\.register/);
    assert.match(pushLibrarySource, /\/service-worker\.js/);
    assert.match(pushLibrarySource, /getToken\(messaging,/);
    assert.match(
        pushLibrarySource,
        /serviceWorkerRegistration,\s*\n\s*}\);/,
    );
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
    assert.match(
        pushLibrarySource,
        /firebase-cloud-messaging-push-scope/,
    );
    assert.match(
        pushLibrarySource,
        /isFirebaseMessagingServiceWorkerRegistration/,
    );
    assert.match(
        pushLibrarySource,
        /readCurrentPushDeviceContext/,
    );
    assert.match(
        pushLibrarySource,
        /hasPendingServiceWorkerRegistration/,
    );
    assert.match(
        pushLibrarySource,
        /waitForStableFirebaseMessagingServiceWorker/,
    );
    assert.match(
        pushLibrarySource,
        /push-service-worker-not-active/,
    );
    assert.match(
        pushLibrarySource,
        /cleanupCurrentBrowserPushRegistration/,
    );
    assert.match(pushLibrarySource, /PUSH_DEBUG_STORAGE_KEY/);
    assert.match(pushLibrarySource, /PUSH_DEBUG_META_SELECTOR/);
    assert.match(pushLibrarySource, /pushDebugEnabled/);
    assert.match(pushLibrarySource, /pushInfo/);
    assert.match(pushLibrarySource, /pushWarn/);
    assert.match(
        pushLibrarySource,
        /registration failed - storage error/,
    );
    assert.match(
        pushLibrarySource,
        /PUSH_STORAGE_ERROR_RECOVERY_DELAY_MS/,
    );
    assert.match(pushLibrarySource, /onMessage\(messaging,/);
    assert.match(
        pushLibrarySource,
        /initializeForegroundPushNotifications/,
    );
    assert.match(
        pushLibrarySource,
        /synchronizeCurrentBrowserPushRegistration/,
    );
    assert.match(pushLibrarySource, /foreground payload received/);
    assert.match(pushLibrarySource, /browser registration synchronized/);
    assert.match(pushLibrarySource, /service worker update detected/);
    assert.match(pushLibrarySource, /showNotification\(/);
    assert.match(pushLibrarySource, /DEFAULT_PUSH_NOTIFICATION_ICON = '\/pwa\/icons\/icon-192\.png'/);
    assert.match(pushLibrarySource, /DEFAULT_PUSH_NOTIFICATION_BADGE = '\/pwa\/icons\/icon-maskable-192\.png'/);
});

test('application bootstrap synchronizes the current browser push registration without relying on the profile toggle UI', () => {
    assert.match(appSource, /synchronizeCurrentBrowserPushRegistration/);
    assert.match(appSource, /storePushTokenAction\(\)\.url/);
    assert.match(appSource, /destroyPushTokenAction\(\)\.url/);
    assert.match(appSource, /push_notifications_enabled/);
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
    assert.match(serviceWorkerSource, /raw push event received/);
    assert.match(serviceWorkerSource, /duplicate notification skipped/);
    assert.match(serviceWorkerSource, /notification shown/);
    assert.match(serviceWorkerSource, /DEFAULT_PUSH_NOTIFICATION_ICON = '\/pwa\/icons\/icon-192\.png'/);
    assert.match(serviceWorkerSource, /DEFAULT_PUSH_NOTIFICATION_BADGE = '\/pwa\/icons\/icon-maskable-192\.png'/);
    assert.match(serviceWorkerSource, /skipWaiting/);
    assert.doesNotMatch(serviceWorkerSource, /INIT_FIREBASE_MESSAGING/);
    assert.doesNotMatch(serviceWorkerSource, /clients\.claim/);
    assert.match(serviceWorkerSource, /initializeFirebaseMessaging\(FIREBASE_MESSAGING_CONFIG \?\? {}\)/);
    assert.match(serviceWorkerSource, /clients\.openWindow/);
});
