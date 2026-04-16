import type { FirebaseApp } from 'firebase/app';
import { initializeApp } from 'firebase/app';
import {
    deleteToken,
    getMessaging,
    getToken,
    isSupported,
    onMessage,
} from 'firebase/messaging';
import type {
    Messaging,
    MessagePayload,
    Unsubscribe,
} from 'firebase/messaging';

type FirebaseMessagingConfig = {
    apiKey: string;
    authDomain: string;
    projectId: string;
    storageBucket: string;
    messagingSenderId: string;
    appId: string;
    vapidKey: string;
};

const CURRENT_PUSH_TOKEN_STORAGE_KEY = 'soamco-budget:push-web-token';
const CURRENT_PUSH_DEVICE_IDENTIFIER_STORAGE_KEY =
    'soamco-budget:push-web-device-identifier';
const CURRENT_PUSH_SERVICE_WORKER_MISSING_SINCE_STORAGE_KEY =
    'soamco-budget:push-web-sw-missing-since';
const PUSH_DEBUG_STORAGE_KEY = 'soamco-budget:debug-push';
const FIREBASE_MESSAGING_SERVICE_WORKER_URL = '/service-worker.js';
const LEGACY_FIREBASE_MESSAGING_SERVICE_WORKER_URL =
    '/firebase-messaging-sw.js';
const LEGACY_FIREBASE_MESSAGING_SCOPE_SUFFIX =
    '/firebase-cloud-messaging-push-scope';
const DEFAULT_PUSH_NOTIFICATION_ICON = '/pwa/icons/icon-192.png';
const DEFAULT_PUSH_NOTIFICATION_BADGE = '/pwa/icons/icon-maskable-192.png';
const ASSET_VERSION_META_SELECTOR = 'meta[name="soamco-asset-version"]';
const PUSH_DEBUG_META_SELECTOR = 'meta[name="soamco-push-debug"]';
const SERVICE_WORKER_MISSING_CLEANUP_GRACE_PERIOD_MS = 30 * 1000;
const PUSH_STORAGE_ERROR_RECOVERY_DELAY_MS = 1500;
const RECENT_PUSH_MESSAGE_TTL_MS = 30 * 1000;
let firebaseApp: FirebaseApp | null = null;
let firebaseMessaging: Messaging | null = null;
let serviceWorkerRegistrationPromise:
    | Promise<ServiceWorkerRegistration>
    | null = null;
let foregroundMessageUnsubscribe: Unsubscribe | null = null;
let hasBoundServiceWorkerLifecycleLogging = false;
const observedServiceWorkerRegistrations = new WeakSet<ServiceWorkerRegistration>();
const recentlyHandledForegroundPushMessages = new Map<string, number>();

export type CurrentPushDeviceContext = {
    hasSupportedBrowser: boolean;
    hasValidConfig: boolean;
    permission: NotificationPermission | 'unsupported';
    hasExplicitServiceWorkerRegistration: boolean;
    hasPendingServiceWorkerRegistration: boolean;
    hasLegacyFirebaseMessagingScope: boolean;
    persistedToken: string | null;
    deviceIdentifier: string | null;
};

export type PushRegistrationSyncResult =
    | {
          status:
              | 'skipped'
              | 'registered'
              | 'reused'
              | 'reactivated'
              | 'rotated'
              | 'realigned';
          token: string | null;
      }
    | {
          status: 'failed';
          token: string | null;
          error: string;
      };

type BrowserNotificationPayload = {
    title: string;
    options: NotificationOptions;
};

type PushNotificationIdentity = {
    deduplicationKey: string;
    tag: string;
};

type CurrentDeviceCleanupOptions = {
    destroyUrl?: string;
    token?: string | null;
    reason: string;
};

function envValue(key: keyof ImportMetaEnv): string {
    const value = import.meta.env[key];

    return typeof value === 'string' ? value.trim() : '';
}

function pushDebugEnabled(): boolean {
    if (import.meta.env.DEV) {
        return true;
    }

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return false;
    }

    if (
        document
            .querySelector<HTMLMetaElement>(PUSH_DEBUG_META_SELECTOR)
            ?.content?.trim() === 'true'
    ) {
        return true;
    }

    return window.localStorage.getItem(PUSH_DEBUG_STORAGE_KEY) === 'true';
}

function pushInfo(message: string, context?: unknown): void {
    if (!pushDebugEnabled()) {
        return;
    }

    if (typeof context === 'undefined') {
        console.info(message);

        return;
    }

    console.info(message, context);
}

function pushWarn(message: string, context?: unknown): void {
    if (!pushDebugEnabled()) {
        return;
    }

    if (typeof context === 'undefined') {
        console.warn(message);

        return;
    }

    console.warn(message, context);
}

function isRecoverableChromeStorageError(error: unknown): boolean {
    const message =
        error instanceof Error ? error.message.toLowerCase() : String(error).toLowerCase();

    return message.includes('registration failed - storage error');
}

export function readFirebaseMessagingConfig(): FirebaseMessagingConfig | null {
    const config = {
        apiKey: envValue('VITE_FIREBASE_API_KEY'),
        authDomain: envValue('VITE_FIREBASE_AUTH_DOMAIN'),
        projectId: envValue('VITE_FIREBASE_PROJECT_ID'),
        storageBucket: envValue('VITE_FIREBASE_STORAGE_BUCKET'),
        messagingSenderId: envValue('VITE_FIREBASE_MESSAGING_SENDER_ID'),
        appId: envValue('VITE_FIREBASE_APP_ID'),
        vapidKey: envValue('VITE_FIREBASE_VAPID_PUBLIC_KEY'),
    };

    if (Object.values(config).some((value) => value === '')) {
        return null;
    }

    return config;
}

export async function supportsWebPushRegistration(): Promise<boolean> {
    if (
        typeof window === 'undefined' ||
        !('Notification' in window) ||
        !('serviceWorker' in navigator) ||
        !('PushManager' in window)
    ) {
        return false;
    }

    return isSupported();
}

export async function requestNotificationPermission(): Promise<NotificationPermission> {
    if (Notification.permission === 'granted') {
        return 'granted';
    }

    return Notification.requestPermission();
}

function ensureFirebaseApp(config: FirebaseMessagingConfig): FirebaseApp {
    if (firebaseApp !== null) {
        return firebaseApp;
    }

    firebaseApp = initializeApp({
        apiKey: config.apiKey,
        authDomain: config.authDomain,
        projectId: config.projectId,
        storageBucket: config.storageBucket,
        messagingSenderId: config.messagingSenderId,
        appId: config.appId,
    });

    return firebaseApp;
}

function ensureFirebaseMessaging(config: FirebaseMessagingConfig): Messaging {
    if (firebaseMessaging !== null) {
        return firebaseMessaging;
    }

    firebaseMessaging = getMessaging(ensureFirebaseApp(config));

    return firebaseMessaging;
}

function serviceWorkerUrl(config: FirebaseMessagingConfig): string {
    void config;

    return FIREBASE_MESSAGING_SERVICE_WORKER_URL;
}

async function unregisterLegacyFirebaseMessagingRegistration(): Promise<void> {
    const registrations = await navigator.serviceWorker.getRegistrations();

    await Promise.all(
        registrations
            .filter(
                (registration) =>
                    registration.scope.endsWith(
                        LEGACY_FIREBASE_MESSAGING_SCOPE_SUFFIX,
                    ) ||
                    [
                        registration.active,
                        registration.waiting,
                        registration.installing,
                    ].some((worker) =>
                        worker?.scriptURL.includes(
                            LEGACY_FIREBASE_MESSAGING_SERVICE_WORKER_URL,
                        ),
                    ),
            )
            .map((registration) => registration.unregister()),
    );
}

async function getServiceWorkerRegistrations(): Promise<ServiceWorkerRegistration[]> {
    return navigator.serviceWorker.getRegistrations();
}

function isFirebaseMessagingServiceWorkerRegistration(
    registration: ServiceWorkerRegistration,
): boolean {
    return [
        registration.active,
        registration.waiting,
        registration.installing,
    ].some(
        (worker) =>
            worker?.scriptURL.includes(FIREBASE_MESSAGING_SERVICE_WORKER_URL) ===
            true,
    );
}

function hasActiveFirebaseMessagingServiceWorkerRegistration(
    registration: ServiceWorkerRegistration,
): boolean {
    return (
        registration.active?.scriptURL.includes(
            FIREBASE_MESSAGING_SERVICE_WORKER_URL,
        ) === true
    );
}

function bindServiceWorkerLifecycleLogging(
    registration: ServiceWorkerRegistration,
): void {
    if (!hasBoundServiceWorkerLifecycleLogging) {
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            pushInfo('[push] service worker controller changed');
        });

        hasBoundServiceWorkerLifecycleLogging = true;
    }

    if (observedServiceWorkerRegistrations.has(registration)) {
        return;
    }

    observedServiceWorkerRegistrations.add(registration);

    registration.addEventListener('updatefound', () => {
        const nextWorker = registration.installing;

        if (!nextWorker) {
            return;
        }

        pushInfo('[push] service worker update detected', {
            scriptURL: nextWorker.scriptURL,
        });

        nextWorker.addEventListener('statechange', () => {
            pushInfo('[push] service worker state changed', {
                state: nextWorker.state,
            });
        });
    });
}

function buildBrowserNotificationPayload(
    payload: MessagePayload,
): BrowserNotificationPayload {
    const notification = payload.notification ?? {};
    const link =
        payload.fcmOptions?.link ?? payload.data?.url ?? payload.data?.link ?? '/';
    const identity = resolvePushNotificationIdentity(payload);

    return {
        title: notification.title || payload.data?.title || 'Soamco Budget',
        options: {
            body: notification.body || payload.data?.body || '',
            icon: notification.icon || DEFAULT_PUSH_NOTIFICATION_ICON,
            badge: notification.badge || DEFAULT_PUSH_NOTIFICATION_BADGE,
            data: {
                url: link,
                deduplicationKey: identity.deduplicationKey,
            },
            tag: identity.tag,
        },
    };
}

function resolvePushNotificationIdentity(
    payload: MessagePayload,
): PushNotificationIdentity {
    const notification = payload.notification ?? {};
    const link =
        payload.fcmOptions?.link ?? payload.data?.url ?? payload.data?.link ?? '/';
    const title = notification.title || payload.data?.title || 'Soamco Budget';
    const body = notification.body || payload.data?.body || '';
    const rawKey =
        payload.data?.broadcast_uuid ??
        payload.fcmMessageId ??
        notification.tag ??
        payload.data?.tag ??
        `${link}:${title}:${body}`;
    const deduplicationKey = String(rawKey).trim() || `${link}:${title}:${body}`;

    return {
        deduplicationKey,
        tag: `push:${deduplicationKey}`,
    };
}

function pruneRecentlyHandledForegroundPushMessages(now: number): void {
    for (const [key, expiresAt] of recentlyHandledForegroundPushMessages.entries()) {
        if (expiresAt <= now) {
            recentlyHandledForegroundPushMessages.delete(key);
        }
    }
}

function wasForegroundPushMessageHandledRecently(deduplicationKey: string): boolean {
    const now = Date.now();

    pruneRecentlyHandledForegroundPushMessages(now);

    return (
        recentlyHandledForegroundPushMessages.get(deduplicationKey) ?? 0
    ) > now;
}

function markForegroundPushMessageHandled(deduplicationKey: string): void {
    const now = Date.now();

    pruneRecentlyHandledForegroundPushMessages(now);
    recentlyHandledForegroundPushMessages.set(
        deduplicationKey,
        now + RECENT_PUSH_MESSAGE_TTL_MS,
    );
}

async function shouldSkipForegroundNotification(
    registration: ServiceWorkerRegistration,
    payload: MessagePayload,
): Promise<boolean> {
    const identity = resolvePushNotificationIdentity(payload);

    if (wasForegroundPushMessageHandledRecently(identity.deduplicationKey)) {
        pushInfo('[push] duplicate foreground payload skipped', {
            deduplicationKey: identity.deduplicationKey,
            reason: 'recent-memory',
        });

        return true;
    }

    const existingNotifications = await registration.getNotifications({
        tag: identity.tag,
    });

    if (existingNotifications.length > 0) {
        markForegroundPushMessageHandled(identity.deduplicationKey);
        pushInfo('[push] duplicate foreground payload skipped', {
            deduplicationKey: identity.deduplicationKey,
            tag: identity.tag,
            reason: 'existing-notification',
        });

        return true;
    }

    return false;
}

async function resolveFirebaseMessagingServiceWorkerRegistration(
    config: FirebaseMessagingConfig,
): Promise<ServiceWorkerRegistration> {
    await unregisterLegacyFirebaseMessagingRegistration();

    const existingRegistration = await navigator.serviceWorker.getRegistration('/');

    if (
        existingRegistration &&
        hasActiveFirebaseMessagingServiceWorkerRegistration(existingRegistration)
    ) {
        bindServiceWorkerLifecycleLogging(existingRegistration);

        return existingRegistration;
    }

    const registration = await navigator.serviceWorker.register(
        serviceWorkerUrl(config),
        {
            scope: '/',
            updateViaCache: 'none',
        },
    );

    bindServiceWorkerLifecycleLogging(registration);

    return registration;
}

function isStableFirebaseMessagingRegistration(
    registration: ServiceWorkerRegistration,
): boolean {
    return (
        hasActiveFirebaseMessagingServiceWorkerRegistration(registration) &&
        registration.active !== null &&
        registration.active.state === 'activated' &&
        registration.installing === null
    );
}

async function waitForServiceWorkerController(
    timeoutMs: number,
): Promise<ServiceWorker | null> {
    if (navigator.serviceWorker.controller !== null) {
        return navigator.serviceWorker.controller;
    }

    return new Promise((resolve) => {
        const timeoutId = window.setTimeout(() => {
            navigator.serviceWorker.removeEventListener(
                'controllerchange',
                handleControllerChange,
            );
            resolve(navigator.serviceWorker.controller);
        }, timeoutMs);

        const handleControllerChange = (): void => {
            if (navigator.serviceWorker.controller === null) {
                return;
            }

            window.clearTimeout(timeoutId);
            navigator.serviceWorker.removeEventListener(
                'controllerchange',
                handleControllerChange,
            );
            resolve(navigator.serviceWorker.controller);
        };

        navigator.serviceWorker.addEventListener(
            'controllerchange',
            handleControllerChange,
        );
    });
}

async function waitForStableFirebaseMessagingServiceWorker(
    registration: ServiceWorkerRegistration,
    timeoutMs = 10000,
): Promise<ServiceWorkerRegistration> {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() <= deadline) {
        const rootRegistration =
            (await navigator.serviceWorker.getRegistration('/')) ?? registration;

        bindServiceWorkerLifecycleLogging(rootRegistration);

        if (!isStableFirebaseMessagingRegistration(rootRegistration)) {
            await new Promise((resolve) => window.setTimeout(resolve, 150));
            continue;
        }

        const controller = await waitForServiceWorkerController(1500);

        if (
            controller === null ||
            controller.scriptURL.includes(FIREBASE_MESSAGING_SERVICE_WORKER_URL)
        ) {
            return rootRegistration;
        }

        await new Promise((resolve) => window.setTimeout(resolve, 150));
    }

    throw new Error('push-service-worker-not-active');
}

export async function readCurrentPushDeviceContext(): Promise<CurrentPushDeviceContext> {
    const hasValidConfig = readFirebaseMessagingConfig() !== null;
    const persistedToken = readPersistedCurrentPushToken();
    const deviceIdentifier = readPushDeviceIdentifier();
    const hasSupportedBrowser = await supportsWebPushRegistration();

    if (!hasSupportedBrowser) {
        return {
            hasSupportedBrowser: false,
            hasValidConfig,
            permission:
                typeof Notification === 'undefined'
                    ? 'unsupported'
                    : Notification.permission,
            hasExplicitServiceWorkerRegistration: false,
            hasPendingServiceWorkerRegistration: false,
            hasLegacyFirebaseMessagingScope: false,
            persistedToken,
            deviceIdentifier,
        };
    }

    await unregisterLegacyFirebaseMessagingRegistration();

    const registrations = await getServiceWorkerRegistrations();
    const hasExplicitServiceWorkerRegistration = registrations.some(
        (registration) =>
            registration.scope.endsWith('/') &&
            registration.active !== null &&
            registration.active.state === 'activated' &&
            isFirebaseMessagingServiceWorkerRegistration(registration),
    );
    const hasPendingServiceWorkerRegistration = registrations.some(
        (registration) =>
            registration.scope.endsWith('/') &&
            isFirebaseMessagingServiceWorkerRegistration(registration) &&
            (registration.installing !== null ||
                registration.waiting !== null ||
                registration.active?.state === 'activating'),
    );
    const hasLegacyFirebaseMessagingScope = registrations.some((registration) =>
        registration.scope.endsWith(LEGACY_FIREBASE_MESSAGING_SCOPE_SUFFIX),
    );

    return {
        hasSupportedBrowser: true,
        hasValidConfig,
        permission: Notification.permission,
        hasExplicitServiceWorkerRegistration,
        hasPendingServiceWorkerRegistration,
        hasLegacyFirebaseMessagingScope,
        persistedToken,
        deviceIdentifier,
    };
}

export async function registerFirebaseMessagingServiceWorker(): Promise<ServiceWorkerRegistration> {
    const config = readFirebaseMessagingConfig();

    if (config === null) {
        throw new Error('firebase-config-missing');
    }

    if (serviceWorkerRegistrationPromise === null) {
        serviceWorkerRegistrationPromise =
            resolveFirebaseMessagingServiceWorkerRegistration(config);
    }

    let registration = await serviceWorkerRegistrationPromise;

    if (!isFirebaseMessagingServiceWorkerRegistration(registration)) {
        serviceWorkerRegistrationPromise =
            resolveFirebaseMessagingServiceWorkerRegistration(config);
        registration = await serviceWorkerRegistrationPromise;
    }

    const readyRegistration = await navigator.serviceWorker.ready.catch(
        () => registration,
    );
    const candidateRegistration = hasActiveFirebaseMessagingServiceWorkerRegistration(
        readyRegistration,
    )
        ? readyRegistration
        : registration;

    return waitForStableFirebaseMessagingServiceWorker(candidateRegistration);
}

export async function initializeForegroundPushNotifications(): Promise<void> {
    const config = readFirebaseMessagingConfig();

    if (config === null) {
        pushInfo('[push] foreground listener skipped: firebase config missing');

        return;
    }

    const supported = await supportsWebPushRegistration();

    if (!supported) {
        pushInfo('[push] foreground listener skipped: browser unsupported');

        return;
    }

    if (Notification.permission !== 'granted') {
        pushInfo(
            '[push] foreground listener skipped: notification permission not granted',
            { permission: Notification.permission },
        );

        return;
    }

    await registerFirebaseMessagingServiceWorker();

    if (foregroundMessageUnsubscribe !== null) {
        return;
    }

    const messaging = ensureFirebaseMessaging(config);

    foregroundMessageUnsubscribe = onMessage(messaging, async (payload) => {
        pushInfo('[push] foreground payload received', payload);

        const notificationPayload = buildBrowserNotificationPayload(payload);

        try {
            const registration = await registerFirebaseMessagingServiceWorker();
            const deduplicationKey =
                String(
                    notificationPayload.options.data?.deduplicationKey ??
                        resolvePushNotificationIdentity(payload).deduplicationKey,
                ) || 'unknown';

            if (await shouldSkipForegroundNotification(registration, payload)) {
                return;
            }

            await registration.showNotification(
                notificationPayload.title,
                notificationPayload.options,
            );
            markForegroundPushMessageHandled(deduplicationKey);

            pushInfo('[push] foreground notification shown', {
                title: notificationPayload.title,
                url: notificationPayload.options.data?.url ?? '/',
                tag: notificationPayload.options.tag,
                deduplicationKey,
            });
        } catch (error) {
            pushWarn('[push] foreground notification failed', error);
        }
    });

    pushInfo('[push] foreground listener initialized');
}

export async function getCurrentPushToken(): Promise<string | null> {
    const config = readFirebaseMessagingConfig();

    if (config === null) {
        throw new Error('firebase-config-missing');
    }

    const serviceWorkerRegistration =
        await registerFirebaseMessagingServiceWorker();
    const messaging = ensureFirebaseMessaging(config);
    const token = await getToken(messaging, {
        vapidKey: config.vapidKey,
        serviceWorkerRegistration,
    });

    if (!token) {
        return null;
    }

    persistCurrentPushToken(token);

    return token;
}

export async function registerCurrentBrowserPushToken(): Promise<string> {
    try {
        const token = await getCurrentPushToken();

        if (token) {
            return token;
        }
    } catch (error) {
        if (!isRecoverableChromeStorageError(error)) {
            throw error;
        }

        serviceWorkerRegistrationPromise = null;
        await new Promise((resolve) =>
            window.setTimeout(resolve, PUSH_STORAGE_ERROR_RECOVERY_DELAY_MS),
        );
    }

    const retriedToken = await getCurrentPushToken();

    if (retriedToken) {
        return retriedToken;
    }

    throw new Error('firebase-token-missing');
}

export async function synchronizeCurrentBrowserPushRegistration({
    isAuthenticated,
    featureEnabled,
    locale,
    storeUrl,
    destroyUrl,
}: {
    isAuthenticated: boolean;
    featureEnabled: boolean;
    locale?: string | null;
    storeUrl: string;
    destroyUrl?: string;
}): Promise<PushRegistrationSyncResult> {
    if (!isAuthenticated || !featureEnabled) {
        return {
            status: 'skipped',
            token: null,
        };
    }

    if (readFirebaseMessagingConfig() === null) {
        return {
            status: 'skipped',
            token: null,
        };
    }

    const supported = await supportsWebPushRegistration();

    if (!supported) {
        await cleanupCurrentBrowserPushRegistration({
            destroyUrl,
            token: readPersistedCurrentPushToken(),
            reason: 'browser_unsupported',
        });

        return {
            status: 'skipped',
            token: null,
        };
    }

    if (Notification.permission !== 'granted') {
        await cleanupCurrentBrowserPushRegistration({
            destroyUrl,
            token: readPersistedCurrentPushToken(),
            reason:
                Notification.permission === 'denied'
                    ? 'permission_revoked'
                    : 'permission_not_granted',
        });

        return {
            status: 'skipped',
            token: readPersistedCurrentPushToken(),
        };
    }

    const previousToken = readPersistedCurrentPushToken();

    try {
        const token = await registerCurrentBrowserPushToken();

        if (token === null || token === '') {
            await cleanupCurrentBrowserPushRegistration({
                destroyUrl,
                token: previousToken,
                reason: 'browser_token_missing',
            });

            return {
                status: 'skipped',
                token: null,
            };
        }

        const payload = await submitPushTokenRequest(storeUrl, 'POST', {
            token,
            platform: 'web',
            locale: locale ?? undefined,
            device_identifier: getOrCreatePushDeviceIdentifier(),
            service_worker_version: readCurrentServiceWorkerVersion(),
        });
        const lifecycle =
            typeof payload.push?.device_lifecycle === 'string'
                ? payload.push.device_lifecycle
                : previousToken === token
                  ? 'reused'
                  : previousToken === null
                    ? 'registered'
                    : 'rotated';

        pushInfo('[push] browser registration synchronized', {
            lifecycle,
            tokenChanged: previousToken !== token,
            recoveredFromInvalidation:
                payload.push?.recovered_from_invalidation === true,
        });
        clearMissingServiceWorkerCleanupDeadline();

        return {
            status: isSupportedLifecycle(lifecycle) ? lifecycle : 'reused',
            token,
        };
    } catch (error) {
        const message =
            error instanceof Error ? error.message : 'push-sync-failed';

        if (
            message === 'firebase-token-missing'
        ) {
            await cleanupCurrentBrowserPushRegistration({
                destroyUrl,
                token: previousToken,
                reason: 'browser_token_missing',
            });
        }

        pushWarn('[push] browser registration sync failed', {
            error: message,
        });

        return {
            status: 'failed',
            token: previousToken,
            error: message,
        };
    }
}

export async function clearCurrentBrowserPushToken(): Promise<void> {
    const config = readFirebaseMessagingConfig();

    if (config === null) {
        clearPersistedCurrentPushToken();

        return;
    }

    const supported = await supportsWebPushRegistration();

    if (!supported) {
        clearPersistedCurrentPushToken();

        return;
    }

    try {
        const messaging = ensureFirebaseMessaging(config);

        await deleteToken(messaging);
    } finally {
        clearPersistedCurrentPushToken();
    }
}

export async function cleanupCurrentBrowserPushRegistration({
    destroyUrl,
    token,
    reason,
}: CurrentDeviceCleanupOptions): Promise<void> {
    const currentToken =
        token ?? readPersistedCurrentPushToken() ?? readPushDeviceIdentifier();

    if (destroyUrl && currentToken !== null) {
        try {
            await submitPushTokenRequest(destroyUrl, 'DELETE', {
                token: typeof token === 'string' && token !== '' ? token : undefined,
                platform: 'web',
                device_identifier: getOrCreatePushDeviceIdentifier(),
            });

            pushInfo('[push] browser registration cleaned up', {
                reason,
            });
        } catch (error) {
            pushWarn('[push] browser registration cleanup failed', {
                reason,
                error:
                    error instanceof Error ? error.message : 'push-cleanup-failed',
            });
        }
    }

    try {
        await clearCurrentBrowserPushToken();
    } catch {
        clearPersistedCurrentPushToken();
    }
}

export function clearMissingServiceWorkerCleanupDeadline(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(
        CURRENT_PUSH_SERVICE_WORKER_MISSING_SINCE_STORAGE_KEY,
    );
}

export function shouldCleanupMissingServiceWorker(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const storedTimestamp = window.localStorage.getItem(
        CURRENT_PUSH_SERVICE_WORKER_MISSING_SINCE_STORAGE_KEY,
    );
    const now = Date.now();

    if (storedTimestamp === null) {
        window.localStorage.setItem(
            CURRENT_PUSH_SERVICE_WORKER_MISSING_SINCE_STORAGE_KEY,
            String(now),
        );

        return false;
    }

    const missingSince = Number.parseInt(storedTimestamp, 10);

    if (!Number.isFinite(missingSince)) {
        window.localStorage.setItem(
            CURRENT_PUSH_SERVICE_WORKER_MISSING_SINCE_STORAGE_KEY,
            String(now),
        );

        return false;
    }

    return now - missingSince >= SERVICE_WORKER_MISSING_CLEANUP_GRACE_PERIOD_MS;
}

export function readPersistedCurrentPushToken(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const token = window.localStorage.getItem(CURRENT_PUSH_TOKEN_STORAGE_KEY);

    return token && token !== '' ? token : null;
}

export function persistCurrentPushToken(token: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(CURRENT_PUSH_TOKEN_STORAGE_KEY, token);
}

export function clearPersistedCurrentPushToken(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(CURRENT_PUSH_TOKEN_STORAGE_KEY);
}

export function readPushDeviceIdentifier(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const deviceIdentifier = window.localStorage.getItem(
        CURRENT_PUSH_DEVICE_IDENTIFIER_STORAGE_KEY,
    );

    return deviceIdentifier && deviceIdentifier !== '' ? deviceIdentifier : null;
}

export function getOrCreatePushDeviceIdentifier(): string {
    if (typeof window === 'undefined') {
        return 'push-device-unavailable';
    }

    const existingDeviceIdentifier = readPushDeviceIdentifier();

    if (existingDeviceIdentifier !== null) {
        return existingDeviceIdentifier;
    }

    const deviceIdentifier =
        typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
            ? crypto.randomUUID()
            : `push-device-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

    window.localStorage.setItem(
        CURRENT_PUSH_DEVICE_IDENTIFIER_STORAGE_KEY,
        deviceIdentifier,
    );

    return deviceIdentifier;
}

function readCurrentServiceWorkerVersion(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const version = document
        .querySelector<HTMLMetaElement>(ASSET_VERSION_META_SELECTOR)
        ?.content?.trim();

    return version && version !== '' ? version : null;
}

async function submitPushTokenRequest(
    url: string,
    method: 'POST' | 'DELETE',
    payload: Record<string, unknown>,
): Promise<{
    message?: string;
    push?: {
        device_lifecycle?: string;
        recovered_from_invalidation?: boolean;
    };
}> {
    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content?.trim();

    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = (await response.json().catch(() => null)) as
        | {
              message?: string;
              push?: {
                  device_lifecycle?: string;
                  recovered_from_invalidation?: boolean;
              };
          }
        | null;

    if (!response.ok) {
        throw new Error(
            data?.message || `push-request-${response.status}`,
        );
    }

    return data ?? {};
}

function isSupportedLifecycle(
    lifecycle: string,
): lifecycle is Exclude<PushRegistrationSyncResult['status'], 'skipped' | 'failed'> {
    return [
        'registered',
        'reused',
        'reactivated',
        'rotated',
        'realigned',
    ].includes(lifecycle);
}
