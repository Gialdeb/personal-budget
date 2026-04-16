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
    assert.match(composableSource, /triggerWarning\.url\(\)/);
    assert.match(composableSource, /keepAlive\.url\(\)/);
    assert.match(composableSource, /X-CSRF-TOKEN/);
    assert.match(composableSource, /credentials:\s*'same-origin'/);
    assert.match(composableSource, /WARNING_TRIGGER_LOCK_KEY/);
    assert.match(composableSource, /DEFAULT_WARNING_WINDOW_SECONDS = 300/);
    assert.match(composableSource, /sessionState\.value\.isOpen = true/);
    assert.doesNotMatch(
        composableSource,
        /sessionState\.value\.isExpired = secondsRemaining === 0/,
    );
    assert.match(
        composableSource,
        /Math\.min\(payloadExpiryTimestamp, maxWarningExpiryTimestamp\)/,
    );
    assert.match(composableSource, /state:\s*'refreshed'/);
    assert.match(composableSource, /void verifySessionStillValid\(\)/);
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
