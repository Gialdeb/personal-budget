import { router, usePage } from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    readonly,
    ref,
    watch,
} from 'vue';
import {
    keepAlive,
    status as sessionStatus,
    triggerWarning,
} from '@/actions/App/Http/Controllers/SessionActivityController';
import { listenOnPrivateChannel } from '@/lib/realtime/echo';
import { home, login, logout } from '@/routes';
import type {
    Auth,
    SessionWarningRealtimePayload,
    SessionWarningSharedData,
} from '@/types';

type SessionUiSyncEventType =
    | 'warning-opened'
    | 'warning-refreshed'
    | 'warning-dismissed'
    | 'signout-requested';

type SessionUiSyncPayload = {
    id: string;
    source: string;
    type: SessionUiSyncEventType;
    expiresAt: string | null;
    warningWindowSeconds: number;
    sessionLifetimeSeconds: number;
    occurredAt: number;
};

const WARNING_TRIGGER_LOCK_KEY = 'soamco-budget-session-warning-lock';
const SESSION_UI_SYNC_STORAGE_KEY = 'soamco-budget-session-warning-sync';
const SESSION_UI_SYNC_CHANNEL_NAME = 'soamco-budget-session-warning';
const DEFAULT_WARNING_WINDOW_SECONDS = 300;
const DEFAULT_SESSION_LIFETIME_SECONDS = 120 * 60;

const sessionState = ref({
    isOpen: false,
    isExpired: false,
    isCheckingExpiry: false,
    keepAlivePending: false,
    keepAliveError: false,
    expiresAt: null as string | null,
    warningWindowSeconds: DEFAULT_WARNING_WINDOW_SECONDS,
    sessionLifetimeSeconds: DEFAULT_SESSION_LIFETIME_SECONDS,
    secondsRemaining: DEFAULT_WARNING_WINDOW_SECONDS,
});

let realtimeSubscriptionCount = 0;
let activeRealtimeUserUuid: string | null = null;
let unsubscribeFromRealtime: (() => void) | null = null;
let warningTimeout: ReturnType<typeof window.setTimeout> | null = null;
let countdownInterval: ReturnType<typeof window.setInterval> | null = null;
let activityListenerAttached = false;
let syncListenerAttached = false;
let syncChannel: BroadcastChannel | null = null;
let lastProcessedSyncEventId: string | null = null;
let redirectingAfterSessionExpiry = false;
let expiryVerificationRequest: Promise<boolean> | null = null;
const tabId =
    typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `tab-${Math.random().toString(36).slice(2)}`;

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function readWarningLock(): { expiresAt: number } | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const rawValue = window.localStorage.getItem(WARNING_TRIGGER_LOCK_KEY);

        if (!rawValue) {
            return null;
        }

        return JSON.parse(rawValue) as { expiresAt: number };
    } catch {
        return null;
    }
}

function writeWarningLock(expiresAt: number): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        WARNING_TRIGGER_LOCK_KEY,
        JSON.stringify({ expiresAt }),
    );
}

function clearWarningLock(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(WARNING_TRIGGER_LOCK_KEY);
}

function clearSessionUiSyncState(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(SESSION_UI_SYNC_STORAGE_KEY);
}

function buildSyncPayload(type: SessionUiSyncEventType): SessionUiSyncPayload {
    return {
        id:
            typeof crypto !== 'undefined' &&
            typeof crypto.randomUUID === 'function'
                ? crypto.randomUUID()
                : `${tabId}-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        source: tabId,
        type,
        expiresAt: sessionState.value.expiresAt,
        warningWindowSeconds: sessionState.value.warningWindowSeconds,
        sessionLifetimeSeconds: sessionState.value.sessionLifetimeSeconds,
        occurredAt: Date.now(),
    };
}

function postUiSyncEvent(type: SessionUiSyncEventType): void {
    if (typeof window === 'undefined') {
        return;
    }

    const payload = buildSyncPayload(type);

    lastProcessedSyncEventId = payload.id;

    try {
        syncChannel?.postMessage(payload);
    } catch {
        // Ignore channel delivery issues and rely on storage fallback.
    }

    try {
        window.localStorage.setItem(
            SESSION_UI_SYNC_STORAGE_KEY,
            JSON.stringify(payload),
        );
    } catch {
        // Ignore storage write issues and keep local UX working.
    }
}

function readStoredUiSyncEvent(): SessionUiSyncPayload | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const rawValue = window.localStorage.getItem(
            SESSION_UI_SYNC_STORAGE_KEY,
        );

        if (!rawValue) {
            return null;
        }

        return JSON.parse(rawValue) as SessionUiSyncPayload;
    } catch {
        return null;
    }
}

function expiresAtTimestamp(): number | null {
    if (!sessionState.value.expiresAt) {
        return null;
    }

    const timestamp = new Date(sessionState.value.expiresAt).getTime();

    return Number.isNaN(timestamp) ? null : timestamp;
}

function updateCountdown(): void {
    const expiryTimestamp = expiresAtTimestamp();

    if (expiryTimestamp === null) {
        sessionState.value.secondsRemaining =
            sessionState.value.warningWindowSeconds;
        sessionState.value.isExpired = false;

        return;
    }

    const secondsRemaining = Math.max(
        0,
        Math.ceil((expiryTimestamp - Date.now()) / 1000),
    );

    sessionState.value.secondsRemaining = secondsRemaining;

    if (secondsRemaining === 0) {
        sessionState.value.isOpen = true;
        sessionState.value.keepAlivePending = false;
        void verifySessionStillValid();
    }
}

function stopCountdownInterval(): void {
    if (countdownInterval !== null) {
        window.clearInterval(countdownInterval);
        countdownInterval = null;
    }
}

function ensureCountdownInterval(): void {
    if (countdownInterval !== null) {
        return;
    }

    countdownInterval = window.setInterval(() => {
        updateCountdown();
    }, 1000);
}

function clearWarningTimeout(): void {
    if (warningTimeout !== null) {
        window.clearTimeout(warningTimeout);
        warningTimeout = null;
    }
}

function applySharedSessionWarning(
    payload: SessionWarningSharedData | SessionWarningRealtimePayload | null,
): void {
    if (payload === null) {
        return;
    }

    sessionState.value.expiresAt = payload.expires_at;
    sessionState.value.warningWindowSeconds = payload.warning_window_seconds;
    sessionState.value.sessionLifetimeSeconds =
        payload.session_lifetime_seconds;

    updateCountdown();
}

function scheduleWarningTrigger(): void {
    clearWarningTimeout();

    const expiryTimestamp = expiresAtTimestamp();

    if (expiryTimestamp === null) {
        return;
    }

    const triggerAt =
        expiryTimestamp - sessionState.value.warningWindowSeconds * 1000;
    const delay = Math.max(0, triggerAt - Date.now());

    warningTimeout = window.setTimeout(() => {
        void requestWarningBroadcast();
    }, delay);
}

function clampWarningPayload(
    payload: SessionWarningRealtimePayload,
): SessionWarningRealtimePayload {
    const maxWarningExpiryTimestamp =
        Date.now() + payload.warning_window_seconds * 1000;
    const payloadExpiryTimestamp = new Date(payload.expires_at).getTime();
    const clampedExpiryTimestamp = Number.isNaN(payloadExpiryTimestamp)
        ? maxWarningExpiryTimestamp
        : Math.min(payloadExpiryTimestamp, maxWarningExpiryTimestamp);

    return {
        ...payload,
        expires_at: new Date(clampedExpiryTimestamp).toISOString(),
    };
}

function openWarning(
    payload: SessionWarningRealtimePayload,
    shouldSync = false,
): void {
    applySharedSessionWarning(clampWarningPayload(payload));
    sessionState.value.isOpen = true;
    sessionState.value.isExpired = false;
    sessionState.value.isCheckingExpiry = false;
    sessionState.value.keepAliveError = false;
    ensureCountdownInterval();

    if (shouldSync) {
        postUiSyncEvent('warning-opened');
    }
}

function closeWarning(shouldSync = false): void {
    sessionState.value.isOpen = false;
    sessionState.value.isExpired = false;
    sessionState.value.isCheckingExpiry = false;
    sessionState.value.keepAliveError = false;
    stopCountdownInterval();

    if (shouldSync) {
        postUiSyncEvent('warning-dismissed');
    }
}

function applyUiSyncPayload(payload: SessionUiSyncPayload | null): void {
    if (
        payload === null ||
        payload.id === lastProcessedSyncEventId ||
        payload.source === tabId
    ) {
        return;
    }

    lastProcessedSyncEventId = payload.id;

    const syncState: SessionWarningRealtimePayload = {
        state: payload.type === 'warning-opened' ? 'warning' : 'refreshed',
        expires_at:
            payload.expiresAt ??
            nowFallbackIsoString(payload.sessionLifetimeSeconds),
        warning_window_seconds: payload.warningWindowSeconds,
        session_lifetime_seconds: payload.sessionLifetimeSeconds,
    };

    if (payload.type === 'warning-opened') {
        openWarning(syncState, false);

        return;
    }

    if (payload.type === 'warning-refreshed') {
        applySharedSessionWarning(syncState);
        clearWarningLock();
        closeWarning(false);
        scheduleWarningTrigger();

        return;
    }

    if (payload.type === 'warning-dismissed') {
        closeWarning(false);
        scheduleWarningTrigger();

        return;
    }

    if (payload.type === 'signout-requested') {
        redirectToLogin();

        return;
    }

}

function nowFallbackIsoString(sessionLifetimeSeconds: number): string {
    return new Date(Date.now() + sessionLifetimeSeconds * 1000).toISOString();
}

function handleRealtimeUpdate(payload: SessionWarningRealtimePayload): void {
    if (payload.state === 'warning') {
        openWarning(payload, true);

        return;
    }

    applySharedSessionWarning(payload);
    clearWarningLock();
    closeWarning(true);
    postUiSyncEvent('warning-refreshed');
    scheduleWarningTrigger();
}

function confirmExpiredState(): void {
    clearWarningLock();
    sessionState.value.keepAlivePending = false;
    sessionState.value.keepAliveError = false;
    sessionState.value.isCheckingExpiry = false;
    sessionState.value.isExpired = true;
    sessionState.value.isOpen = true;
    stopCountdownInterval();
}

async function verifySessionStillValid(): Promise<boolean> {
    if (sessionState.value.isExpired) {
        return true;
    }

    if (expiryVerificationRequest !== null) {
        return expiryVerificationRequest;
    }

    sessionState.value.isCheckingExpiry = true;

    expiryVerificationRequest = (async () => {
        try {
            const response = await fetch(sessionStatus.url(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (
                response.redirected ||
                response.status === 401 ||
                response.status === 419
            ) {
                confirmExpiredState();

                return true;
            }

            if (!response.ok) {
                sessionState.value.keepAliveError = true;

                return false;
            }

            const payload =
                (await response.json()) as SessionWarningRealtimePayload;

            applySharedSessionWarning({
                ...payload,
                state: 'refreshed',
            });
            clearWarningLock();
            closeWarning(false);
            postUiSyncEvent('warning-refreshed');
            scheduleWarningTrigger();

            return false;
        } catch {
            sessionState.value.keepAliveError = true;

            return false;
        } finally {
            sessionState.value.isCheckingExpiry = false;
            expiryVerificationRequest = null;
        }
    })();

    return expiryVerificationRequest;
}

function redirectToUrl(url: string): void {
    if (typeof window === 'undefined' || redirectingAfterSessionExpiry) {
        return;
    }

    redirectingAfterSessionExpiry = true;
    clearWarningLock();
    clearSessionUiSyncState();
    router.flushAll();
    window.location.assign(url);
}

function redirectToLogin(): void {
    redirectToUrl(login().url);
}

function redirectToHome(): void {
    redirectToUrl(home().url);
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

    unsubscribeFromRealtime =
        listenOnPrivateChannel<SessionWarningRealtimePayload>(
            `users.${userUuid}.session`,
            'session.state.updated',
            handleRealtimeUpdate,
        );
    activeRealtimeUserUuid = userUuid;
}

function acquireWarningTriggerLock(): boolean {
    const existingLock = readWarningLock();

    if (existingLock !== null && existingLock.expiresAt > Date.now()) {
        return false;
    }

    writeWarningLock(Date.now() + 30_000);

    return true;
}

async function requestWarningBroadcast(): Promise<void> {
    updateCountdown();

    if (
        sessionState.value.isOpen ||
        sessionState.value.isExpired ||
        sessionState.value.secondsRemaining >
            sessionState.value.warningWindowSeconds
    ) {
        return;
    }

    if (!acquireWarningTriggerLock()) {
        return;
    }

    try {
        const response = await fetch(triggerWarning.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });

        if (
            response.redirected ||
            response.status === 401 ||
            response.status === 419
        ) {
            clearWarningLock();

            confirmExpiredState();

            return;
        }

        if (!response.ok) {
            clearWarningLock();
            sessionState.value.keepAliveError = true;
        }
    } catch {
        clearWarningLock();
        sessionState.value.keepAliveError = true;
    }
}

function handleVisibilityChange(): void {
    updateCountdown();
    applyUiSyncPayload(readStoredUiSyncEvent());

    if (document.visibilityState === 'visible') {
        if (sessionState.value.secondsRemaining === 0) {
            void verifySessionStillValid();
        }

        scheduleWarningTrigger();
    }
}

function handleWindowFocus(): void {
    updateCountdown();
    applyUiSyncPayload(readStoredUiSyncEvent());

    if (sessionState.value.secondsRemaining === 0) {
        void verifySessionStillValid();
    }

    scheduleWarningTrigger();
}

function attachActivityListeners(): void {
    if (activityListenerAttached || typeof window === 'undefined') {
        return;
    }

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', handleWindowFocus);
    activityListenerAttached = true;
}

function detachActivityListeners(): void {
    if (!activityListenerAttached || typeof window === 'undefined') {
        return;
    }

    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('focus', handleWindowFocus);
    activityListenerAttached = false;
}

function handleStorageSync(event: StorageEvent): void {
    if (event.key !== SESSION_UI_SYNC_STORAGE_KEY || !event.newValue) {
        return;
    }

    try {
        applyUiSyncPayload(JSON.parse(event.newValue) as SessionUiSyncPayload);
    } catch {
        // Ignore malformed payloads from older tabs or manual edits.
    }
}

function handleBroadcastChannelMessage(
    event: MessageEvent<SessionUiSyncPayload>,
): void {
    applyUiSyncPayload(event.data);
}

function attachUiSyncListeners(): void {
    if (syncListenerAttached || typeof window === 'undefined') {
        return;
    }

    if (typeof BroadcastChannel !== 'undefined') {
        syncChannel = new BroadcastChannel(SESSION_UI_SYNC_CHANNEL_NAME);
        syncChannel.addEventListener('message', handleBroadcastChannelMessage);
    }

    window.addEventListener('storage', handleStorageSync);
    syncListenerAttached = true;
}

function detachUiSyncListeners(): void {
    if (!syncListenerAttached || typeof window === 'undefined') {
        return;
    }

    if (syncChannel !== null) {
        syncChannel.removeEventListener(
            'message',
            handleBroadcastChannelMessage,
        );
        syncChannel.close();
        syncChannel = null;
    }

    window.removeEventListener('storage', handleStorageSync);
    syncListenerAttached = false;
}

export function useSessionWarning() {
    const page = usePage();
    const auth = computed(() => page.props.auth as Auth);
    const sharedSessionWarning = computed(
        () =>
            (page.props.sessionWarning ??
                null) as SessionWarningSharedData | null,
    );

    const countdownLabel = computed(() => {
        const totalSeconds = Math.max(0, sessionState.value.secondsRemaining);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        return `${minutes}:${String(seconds).padStart(2, '0')}`;
    });

    async function staySignedIn(): Promise<void> {
        if (sessionState.value.keepAlivePending) {
            return;
        }

        sessionState.value.keepAlivePending = true;
        sessionState.value.keepAliveError = false;
        sessionState.value.isCheckingExpiry = false;

        try {
            const response = await fetch(keepAlive.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': readCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });

            if (!response.ok) {
                sessionState.value.keepAliveError = true;

                if (
                    response.redirected ||
                    response.status === 401 ||
                    response.status === 419
                ) {
                    confirmExpiredState();
                }

                return;
            }

            const payload =
                (await response.json()) as SessionWarningRealtimePayload;

            handleRealtimeUpdate({
                ...payload,
                state: 'refreshed',
            });
        } catch {
            sessionState.value.keepAliveError = true;
        } finally {
            sessionState.value.keepAlivePending = false;
        }
    }

    function signOut(): void {
        postUiSyncEvent('signout-requested');
        router.flushAll();
        router.post(logout().url);
    }

    function signInAgain(): void {
        postUiSyncEvent('signout-requested');
        redirectToLogin();
    }

    function goToHome(): void {
        postUiSyncEvent('signout-requested');
        redirectToHome();
    }

    watch(
        sharedSessionWarning,
        (value) => {
            applySharedSessionWarning(value);
            scheduleWarningTrigger();
        },
        { immediate: true },
    );

    watch(
        () => auth.value?.user?.uuid ?? null,
        (userUuid) => {
            ensureRealtimeSubscription(userUuid);
        },
        { immediate: true },
    );

    onMounted(() => {
        realtimeSubscriptionCount += 1;
        attachActivityListeners();
        attachUiSyncListeners();
        ensureCountdownInterval();
        updateCountdown();
        applyUiSyncPayload(readStoredUiSyncEvent());
        scheduleWarningTrigger();
    });

    onBeforeUnmount(() => {
        realtimeSubscriptionCount = Math.max(0, realtimeSubscriptionCount - 1);

        if (realtimeSubscriptionCount === 0) {
            unsubscribeFromRealtime?.();
            unsubscribeFromRealtime = null;
            activeRealtimeUserUuid = null;
            clearWarningTimeout();
            stopCountdownInterval();
            detachActivityListeners();
            detachUiSyncListeners();
        }
    });

    return {
        state: readonly(sessionState),
        countdownLabel,
        staySignedIn,
        signOut,
        signInAgain,
        goToHome,
    };
}
