import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const profileSource = readFileSync(
    new URL('../../resources/js/pages/settings/Profile.vue', import.meta.url),
    'utf8',
);

test('settings profile renders notification preferences section and save action', () => {
    assert.match(profileSource, /settings\.profile\.notifications\.title/);
    assert.match(
        profileSource,
        /settings\.profile\.notifications\.description/,
    );
    assert.match(profileSource, /settings\.profile\.notifications\.push\.title/);
    assert.match(profileSource, /settings\.profile\.notifications\.save/);
    assert.match(profileSource, /updateNotificationPreferencesAction/);
    assert.doesNotMatch(profileSource, /Token attivi disponibili/);
});

test('settings profile exposes email and dashboard notification toggles', () => {
    assert.match(
        profileSource,
        /settings\.profile\.notifications\.channels\.email/,
    );
    assert.match(
        profileSource,
        /settings\.profile\.notifications\.channels\.dashboard/,
    );
    assert.doesNotMatch(profileSource, />\s*Dashboard\s*</);
    assert.match(profileSource, /email_enabled/);
    assert.match(profileSource, /in_app_enabled/);
    assert.match(profileSource, /notificationPreferencesForm\.push\.enabled/);
});

test('settings profile handles web push registration and deregistration from the toggle', () => {
    assert.match(profileSource, /togglePushWebPreference/);
    assert.match(profileSource, /initializePushWebDeviceState/);
    assert.match(profileSource, /pushWebDeviceState/);
    assert.match(profileSource, /isPushWebDeviceEnabled/);
    assert.match(profileSource, /requestNotificationPermission/);
    assert.match(profileSource, /registerCurrentBrowserPushToken/);
    assert.match(profileSource, /synchronizeCurrentBrowserPushRegistration/);
    assert.match(profileSource, /storePushTokenAction\(\)\.url/);
    assert.match(profileSource, /pushTokenStatusAction\(\)\.url/);
    assert.match(profileSource, /destroyPushTokenAction\(\)\.url/);
    assert.match(profileSource, /getOrCreatePushDeviceIdentifier/);
    assert.match(profileSource, /clearCurrentBrowserPushToken/);
    assert.match(profileSource, /cleanupCurrentBrowserPushRegistration/);
    assert.match(profileSource, /readCurrentPushDeviceContext/);
    assert.match(profileSource, /hasPendingServiceWorkerRegistration/);
    assert.match(profileSource, /pushWebActiveTokensCount/);
});

test('settings profile exposes notification preferences empty state', () => {
    assert.match(
        profileSource,
        /settings\.profile\.notifications\.empty\.title/,
    );
    assert.match(
        profileSource,
        /settings\.profile\.notifications\.empty\.description/,
    );
});

test('settings profile renders active sessions section with revoke actions', () => {
    assert.match(profileSource, /settings\.profile\.active_sessions\.title/);
    assert.match(
        profileSource,
        /settings\.profile\.active_sessions\.current_badge/,
    );
    assert.match(
        profileSource,
        /settings\.profile\.active_sessions\.actions\.revoke/,
    );
    assert.match(
        profileSource,
        /settings\.profile\.active_sessions\.actions\.revoke_others/,
    );
    assert.match(
        profileSource,
        /settings\.profile\.active_sessions\.empty\.title/,
    );
    assert.match(profileSource, /submitSessionRevocation/);
    assert.match(profileSource, /submitRevokeOtherSessions/);
});

test('settings profile keeps administrative support consent readable on mobile', () => {
    assert.match(profileSource, /settings\.profile\.impersonation\.title/);
    assert.match(
        profileSource,
        /rounded-\[1\.4rem][\s\S]*sm:flex sm:items-start sm:gap-4[\s\S]*sm:rounded-\[1\.75rem]/,
    );
    assert.match(
        profileSource,
        /class="grid gap-3 rounded-\[1\.2rem][\s\S]*sm:flex sm:items-start sm:gap-3/,
    );
    assert.match(
        profileSource,
        /class="flex flex-col gap-3 sm:flex-row sm:items-center"/,
    );
    assert.match(
        profileSource,
        /inline-flex rounded-full px-3 py-1 text-xs font-medium/,
    );
});
