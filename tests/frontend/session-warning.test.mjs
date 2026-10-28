import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const dialogSource = readFileSync(
    new URL(
        '../../resources/js/components/SessionWarningDialog.vue',
        import.meta.url,
    ),
    'utf8',
);
const composableSource = readFileSync(
    new URL(
        '../../resources/js/composables/useSessionWarning.ts',
        import.meta.url,
    ),
    'utf8',
);
const layoutSource = readFileSync(
    new URL(
        '../../resources/js/layouts/app/AppSidebarLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const appMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/app.ts', import.meta.url),
    'utf8',
);

test('session warning dialog is mounted globally in the authenticated app shell', () => {
    assert.match(layoutSource, /import SessionWarningDialog/);
    assert.match(layoutSource, /<SessionWarningDialog \/>/);
});

test('session warning composable coordinates reverb warning events and http keep alive', () => {
    assert.match(composableSource, /listenOnPrivateChannel/);
    assert.match(composableSource, /users\.\$\{userUuid}\.session/);
    assert.match(composableSource, /session\.state\.updated/);
    assert.match(composableSource, /status as sessionStatus/);
    assert.match(composableSource, /sessionStatus\.url\(\)/);
    assert.doesNotMatch(composableSource, /triggerWarning\.url\(\)/);
    assert.match(composableSource, /keepAlive\.url\(\)/);
    assert.match(composableSource, /X-CSRF-TOKEN/);
    assert.match(composableSource, /credentials:\s*'same-origin'/);
    assert.match(composableSource, /WARNING_TRIGGER_LOCK_KEY/);
    assert.match(composableSource, /DEFAULT_WARNING_WINDOW_SECONDS = 300/);
    assert.match(
        composableSource,
        /DEFAULT_SESSION_LIFETIME_SECONDS = 180 \* 60/,
    );
    assert.match(
        composableSource,
        /DEFAULT_AUTO_KEEP_ALIVE_THRESHOLD_SECONDS = 15 \* 60/,
    );
    assert.match(composableSource, /AUTO_KEEP_ALIVE_MIN_INTERVAL_MS = 60_000/);
    assert.match(composableSource, /sessionState\.value\.isOpen = true/);
    assert.doesNotMatch(
        composableSource,
        /sessionState\.value\.isExpired = secondsRemaining === 0/,
    );
    assert.doesNotMatch(composableSource, /clampWarningPayload/);
    assert.match(composableSource, /state:\s*'refreshed'/);
    assert.match(composableSource, /void verifySessionStillValid\(\)/);
    assert.match(composableSource, /isStandaloneDisplayMode\(\)/);
    assert.match(composableSource, /isLikelyMobileContext\(\)/);
    assert.match(composableSource, /isEligibleAutoKeepAliveContext\(\)/);
    assert.match(composableSource, /void keepSessionAlive\(true\)/);
    assert.match(composableSource, /autoKeepAliveEnabled/);
    assert.match(composableSource, /autoKeepAliveThresholdSeconds/);
    assert.match(composableSource, /response\.status === 401/);
    assert.match(composableSource, /response\.status === 419/);
});

test('session warning composable uses broadcast channel and storage as best effort cross-tab ui sync', () => {
    assert.match(
        composableSource,
        /type SessionUiSyncEventType\s*=\s*\| 'warning-opened'/,
    );
    assert.match(composableSource, /'warning-refreshed'/);
    assert.match(composableSource, /'warning-dismissed'/);
    assert.match(composableSource, /'signout-requested'/);
    assert.match(composableSource, /BroadcastChannel/);
    assert.match(composableSource, /SESSION_UI_SYNC_STORAGE_KEY/);
    assert.match(composableSource, /SESSION_UI_SYNC_CHANNEL_NAME/);
    assert.match(composableSource, /syncChannel\?\.postMessage\(payload\)/);
    assert.match(
        composableSource,
        /window\.localStorage\.setItem\(\s*SESSION_UI_SYNC_STORAGE_KEY/,
    );
    assert.match(
        composableSource,
        /window\.addEventListener\('storage', handleStorageSync\)/,
    );
    assert.match(
        composableSource,
        /new BroadcastChannel\(SESSION_UI_SYNC_CHANNEL_NAME\)/,
    );
    assert.match(composableSource, /postUiSyncEvent\('signout-requested'\)/);
    assert.match(composableSource, /window\.location\.assign\(url\)/);
    assert.match(composableSource, /redirectToLogin\(\)/);
});

test('session warning dialog exposes localized countdown and recovery actions', () => {
    assert.match(dialogSource, /data-test="session-warning-dialog"/);
    assert.match(dialogSource, /app\.sessionWarning\.title/);
    assert.match(dialogSource, /app\.sessionWarning\.message/);
    assert.match(dialogSource, /app\.sessionWarning\.checkingMessage/);
    assert.match(dialogSource, /app\.sessionWarning\.checkingLabel/);
    assert.match(dialogSource, /app\.sessionWarning\.keepAlive/);
    assert.match(dialogSource, /app\.sessionWarning\.logout/);
    assert.match(dialogSource, /app\.sessionWarning\.signInAgain/);
    assert.match(dialogSource, /app\.sessionWarning\.home/);
    assert.match(dialogSource, /@escape-key-down\.prevent/);
    assert.match(dialogSource, /@pointer-down-outside\.prevent/);
    assert.match(dialogSource, /@interact-outside\.prevent/);
    assert.match(
        dialogSource,
        /class="h-\[100dvh] max-h-\[100dvh] max-w-none overflow-hidden rounded-none/,
    );
    assert.match(appMessagesSource, /Sessione in scadenza/);
    assert.match(appMessagesSource, /Session expiring soon/);
    assert.match(appMessagesSource, /Verifica sessione/);
    assert.match(appMessagesSource, /Checking session/);
    assert.match(appMessagesSource, /Accedi di nuovo/);
    assert.match(appMessagesSource, /Sign in again/);
});

async function sessionHarness() {
    const { runInNewContext } = await import('node:vm');
    const { default: ts } = await import('typescript');
    const timers = new Map();
    let now = Date.parse('2026-10-28T10:00:00Z');
    let nextId = 0;
    const requests = [];
    const storage = new Map();
    const scope = {
        Date: class extends Date {
            static now() {
                return now;
            }
        },
        crypto: { randomUUID: () => 'current-tab' },
        ref: (value) => ({ value }),
        window: {
            navigator: { standalone: true },
            matchMedia: () => ({ matches: false }),
            localStorage: {
                getItem: (key) => storage.get(key),
                setItem: (key, value) => storage.set(key, value),
                removeItem: (key) => storage.delete(key),
            },
            setTimeout: (callback) => {
                const id = ++nextId;
                timers.set(id, callback);

                return id;
            },
            clearTimeout: (id) => timers.delete(id),
            setInterval: (callback) => {
                const id = ++nextId;
                timers.set(id, callback);

                return id;
            },
            clearInterval: (id) => timers.delete(id),
            location: {
                assign: () => {
                    throw new Error('Unexpected navigation');
                },
            },
        },
        document: { visibilityState: 'visible' },
        sessionStatus: { url: () => '/session/status' },
        triggerWarning: { url: () => '/session/warning' },
        router: { flushAll() {} },
        login: () => ({ url: '/login' }),
        fetch: async (url) => {
            requests.push(url);

            return {
                ok: true,
                status: 200,
                json: async () => payload(720 * 60),
            };
        },
    };
    const payload = (seconds) => ({
        expires_at: new Date(now + seconds * 1000).toISOString(),
        warning_window_seconds: 300,
        session_lifetime_seconds: 720 * 60,
        auto_keep_alive_enabled: false,
        auto_keep_alive_threshold_seconds: 900,
    });
    const source = composableSource
        .replace(/^import[\s\S]*?from ['"][^'"]+['"];\n/gm, '')
        .replace(
            'export function useSessionWarning',
            'function useSessionWarning',
        );
    runInNewContext(
        ts.transpile(source, { target: ts.ScriptTarget.ES2022 }) +
            '; this.api = { sessionState, applySharedSessionWarning, applyUiSyncPayload, handleRealtimeUpdate, openWarning, handleVisibilityChange, handleWindowFocus, scheduleWarningTrigger, ensureCountdownInterval, closeWarning, updateCountdown, showScheduledWarning };',
        scope,
    );

    return {
        api: scope.api,
        scope,
        payload,
        requests,
        timers,
        advance: (seconds) => {
            now += seconds * 1000;
        },
    };
}

test('a warning persisted before login cannot reopen or shorten a fresh twelve hour session', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(43200));
    h.api.applyUiSyncPayload({
        id: 'old',
        source: 'old-tab',
        type: 'warning-opened',
        occurredAt: Date.parse('2026-10-28T09:00:00Z'),
        expiresAt: '2026-10-28T09:05:00Z',
        warningWindowSeconds: 300,
        sessionLifetimeSeconds: 10800,
    });
    assert.equal(h.api.sessionState.value.isOpen, false);
    assert.equal(h.api.sessionState.value.secondsRemaining, 43200);
});

test('a warning event cannot clamp the server expiry to five minutes', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(43200));
    h.api.handleRealtimeUpdate({ ...h.payload(43200), state: 'warning' });
    assert.equal(h.api.sessionState.value.secondsRemaining, 43200);
    assert.equal(h.api.sessionState.value.isOpen, false);
});

test('scheduled warning uses the real remaining time without a request that renews the session', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(240));
    h.api.showScheduledWarning();
    assert.equal(h.api.sessionState.value.isOpen, true);
    assert.equal(h.api.sessionState.value.secondsRemaining, 240);
    assert.equal(h.requests.length, 0);
    h.advance(90);
    h.api.updateCountdown();
    assert.equal(h.api.sessionState.value.secondsRemaining, 150);
});

test('iOS resume verifies a stale expiry once without flashing a warning or navigating', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(60));
    h.scope.document.visibilityState = 'hidden';
    h.advance(120);
    h.api.handleVisibilityChange();
    assert.equal(h.requests.length, 0);
    assert.equal(h.api.sessionState.value.isOpen, false);
    h.scope.document.visibilityState = 'visible';
    h.api.handleVisibilityChange();
    h.api.handleWindowFocus();
    assert.equal(h.api.sessionState.value.isOpen, false);
    await new Promise(setImmediate);
    assert.equal(h.requests.length, 1);
    assert.equal(h.api.sessionState.value.secondsRemaining, 43200);
    assert.equal(h.api.sessionState.value.isOpen, false);
});

test('a renewed session closes the old warning and keeps only one countdown timer', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(240));
    h.api.openWarning({ ...h.payload(240), state: 'warning' });
    h.api.ensureCountdownInterval();
    assert.equal(h.timers.size, 1);
    h.api.applySharedSessionWarning(h.payload(43200));
    assert.equal(h.api.sessionState.value.isOpen, false);
    h.api.ensureCountdownInterval();
    assert.equal(h.timers.size, 1);
});

test('stale signout from before login never redirects the fresh session', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(43200));
    h.api.applyUiSyncPayload({
        id: 'old-logout',
        source: 'old-tab',
        type: 'signout-requested',
        occurredAt: Date.parse('2026-10-28T09:00:00Z'),
    });
    assert.equal(h.api.sessionState.value.isOpen, false);
});

test('a real expiry on iOS resume is confirmed by the server without automatic logout', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(60));
    h.advance(120);
    h.scope.fetch = async () => ({ ok: false, status: 401 });
    h.api.handleWindowFocus();
    await new Promise(setImmediate);
    assert.equal(h.api.sessionState.value.secondsRemaining, 0);
    assert.equal(h.api.sessionState.value.isExpired, true);
    assert.equal(h.api.sessionState.value.isOpen, true);
});

test('rescheduling does not duplicate timers and suspension hides an obsolete warning', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(240));
    h.api.openWarning({ ...h.payload(240), state: 'warning' });
    h.api.scheduleWarningTrigger();
    h.api.scheduleWarningTrigger();
    assert.equal(h.timers.size, 2);
    h.scope.document.visibilityState = 'hidden';
    h.api.handleVisibilityChange();
    assert.equal(h.api.sessionState.value.isOpen, false);
    assert.equal(h.timers.size, 1);
});

test('late warning and renewal events cannot rewind a more recent server deadline', async () => {
    const h = await sessionHarness();
    h.api.applySharedSessionWarning(h.payload(43200));

    for (const state of ['warning', 'refreshed']) {
        h.api.handleRealtimeUpdate({ ...h.payload(240), state });
    }

    assert.equal(h.api.sessionState.value.secondsRemaining, 43200);
    assert.equal(h.api.sessionState.value.isOpen, false);
    assert.equal(h.api.sessionState.value.autoKeepAliveEnabled, false);
});
