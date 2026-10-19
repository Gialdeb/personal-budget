import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { transformSync } from 'esbuild';

const read = (file) =>
    readFileSync(new URL(`../../${file}`, import.meta.url), 'utf8');
const library = read('resources/js/lib/push-notifications.ts');
const profile = read('resources/js/pages/settings/Profile.vue');
function extract(source, name, next) {
    return source.slice(
        source.indexOf(`async function ${name}`),
        source.indexOf(next, source.indexOf(`async function ${name}`)),
    );
}
function compile(source, bindings) {
    const code = transformSync(source, { loader: 'ts', format: 'cjs' }).code;
    runInNewContext(code, bindings);

    return bindings;
}
function syncHarness(status, failure = false) {
    status = {
        current_device_registered: true,
        current_device_disabled: false,
        ...status,
    };
    const calls = [];
    const bindings = compile(
        extract(
            library,
            'synchronizeCurrentBrowserPushRegistration',
            '\nexport async function clearCurrentBrowserPushToken',
        ),
        {
            readFirebaseMessagingConfig: () => ({}),
            supportsWebPushRegistration: async () => true,
            Notification: { permission: 'granted' },
            readPersistedCurrentPushToken: () => 'saved-token',
            getOrCreatePushDeviceIdentifier: () => 'phone',
            readCurrentServiceWorkerVersion: () => 'v1',
            Intl,
            pushInfo: () => {},
            pushWarn: () => {},
            clearMissingServiceWorkerCleanupDeadline: () => {},
            isSupportedLifecycle: () => true,
            cleanupCurrentBrowserPushRegistration: async () =>
                calls.push('cleanup'),
            registerCurrentBrowserPushToken: async () => {
                calls.push('getToken');

                return 'fresh-token';
            },
            submitPushTokenRequest: async (url, method, payload) => {
                calls.push({ url, method, payload });

                if (failure) {
                    throw new Error('network unavailable');
                }

                return {
                    push:
                        url === 'status'
                            ? status
                            : { current_device_enabled: true },
                };
            },
        },
    );

    return {
        calls,
        run: () =>
            bindings.synchronizeCurrentBrowserPushRegistration({
                isAuthenticated: true,
                featureEnabled: true,
                storeUrl: 'store',
                statusUrl: 'status',
            }),
    };
}

for (const status of [
    {
        global_enabled: true,
        current_device_registered: true,
        current_device_disabled: true,
    },
    { global_enabled: false, current_device_registered: true },
    { global_enabled: true, current_device_registered: false },
]) {
    test(`automatic sync requires saved consent: ${JSON.stringify(status)}`, async () => {
        const h = syncHarness(status);
        assert.equal((await h.run()).status, 'skipped');
        assert.equal(h.calls.length, 1);
    });
}

test('automatic sync checks consent before creating a token and sends an automatic registration', async () => {
    const h = syncHarness({
        global_enabled: true,
        current_device_registered: true,
        current_device_disabled: false,
    });
    assert.equal((await h.run()).status, 'rotated');
    assert.equal(h.calls[0].url, 'status');
    assert.equal(h.calls[1], 'getToken');
    assert.equal(h.calls[2].payload.automatic, true);
});
test('temporary backend error preserves browser token and subscription', async () => {
    const h = syncHarness({}, true);
    const result = await h.run();
    assert.equal(result.status, 'failed');
    assert.equal(result.token, 'saved-token');
    assert.equal(h.calls.length, 1);
});
function profileHarness() {
    const calls = [];
    const ref = (value) => ({ value });
    const bindings = {
        pushWebChecking: ref(false),
        pushWebSubmitting: ref(false),
        pushWebCheckFailed: ref(false),
        pushWebDeviceState: ref('enabled'),
        pushWebInitialized: ref(true),
        pushNotificationsFeatureEnabled: ref(true),
        isPushWebDeviceEnabled: ref(false),
        pushWebFeedback: ref(null),
        reportPushFailure: () => {},
        setPushWebFeedback: (...args) => calls.push(args),
        t: (key) => key,
        readCurrentPushDeviceContext: async () => {
            throw new Error('network');
        },
        readFirebaseMessagingConfig: () => ({}),
        requestNotificationPermission: () => {
            calls.push('permission');

            return Promise.resolve('granted');
        },
        supportsWebPushRegistration: async () => {
            calls.push('support');

            return true;
        },
        registerCurrentBrowserPushToken: async () => 'token',
        submitPushTokenRequest: async () => ({
            push: { enabled: true, active_tokens_count: 1 },
        }),
        storePushTokenAction: () => ({ url: 'store' }),
        props: {
            preferences: { locale: 'it' },
            notification_preferences: {
                push: { enabled: true, active_tokens_count: 1 },
            },
        },
        applyPushPreferenceState: () => {},
        getOrCreatePushDeviceIdentifier: () => 'phone',
        Intl,
        window: { Notification: {} },
        translatePushWebError: () => 'error',
    };
    compile(
        extract(
            profile,
            'initializePushWebDeviceState',
            '\nasync function togglePushWebPreference',
        ),
        bindings,
    );
    compile(
        extract(
            profile,
            'togglePushWebPreference',
            '\nasync function sendPushTest',
        ),
        bindings,
    );

    return { bindings, calls };
}
test('profile verification failure preserves the enabled state and offers retry', async () => {
    const { bindings: b, calls } = profileHarness();
    await b.initializePushWebDeviceState();
    assert.equal(b.pushWebDeviceState.value, 'enabled');
    assert.equal(b.pushWebCheckFailed.value, true);
    assert.match(calls[0][1], /verificationFailed/);
    b.readCurrentPushDeviceContext = async () => ({
        hasSupportedBrowser: true,
        hasValidConfig: false,
    });
    await b.initializePushWebDeviceState();
    assert.equal(b.pushWebCheckFailed.value, false);
});
test('permission request starts in the click turn before asynchronous browser checks', async () => {
    const { bindings: b, calls } = profileHarness();
    const promise = b.togglePushWebPreference();
    assert.deepEqual(calls, ['permission']);
    await promise;
    assert.equal(calls[1], 'support');
    assert.equal(b.pushWebDeviceState.value, 'enabled');
});
test('failed disable restores the enabled state', async () => {
    const { bindings: b } = profileHarness();
    b.isPushWebDeviceEnabled.value = true;
    b.destroyPushTokenAction = () => ({ url: 'destroy' });
    b.readPersistedCurrentPushToken = () => 'token';
    b.submitPushTokenRequest = async () => {
        throw new Error('network');
    };
    await b.togglePushWebPreference();
    assert.equal(b.pushWebDeviceState.value, 'enabled');
});
test('production warnings never log error objects or credential context', () => {
    const warnings = [];
    const start = library.indexOf('function pushWarn(');
    const end = library.indexOf(
        '\nfunction isRecoverableChromeStorageError',
        start,
    );
    const bindings = compile(
        library.slice(start, end).replace('export function', 'function'),
        { console: { warn: (...args) => warnings.push(args) } },
    );
    bindings.pushWarn('[push] failed', {
        token: 'secret',
        error: new Error('credential'),
    });
    assert.deepEqual(warnings[0], ['[push] failed']);
});

test('HTML session redirects are recoverable errors rather than a disabled registration', async () => {
    const b = compile(
        extract(
            profile,
            'submitPushTokenRequest',
            '\nfunction applyPushPreferenceState',
        ),
        {
            fetchWithNotifications: async () => ({
                ok: true,
                status: 200,
                headers: { get: () => 'text/html' },
            }),
            readCsrfToken: () => 'csrf',
        },
    );
    await assert.rejects(
        b.submitPushTokenRequest('status', 'POST', {}),
        /push-invalid-response/,
    );
});

test('missing or malformed status fields preserve the current enabled state', async () => {
    const b = compile(
        extract(
            profile,
            'fetchCurrentPushDeviceRegistrationStatus',
            '\nasync function initializePushWebDeviceState',
        ),
        {
            submitPushTokenRequest: async () => ({ push: {} }),
            pushTokenStatusAction: () => ({ url: 'status' }),
            getOrCreatePushDeviceIdentifier: () => 'phone',
        },
    );
    await assert.rejects(
        b.fetchCurrentPushDeviceRegistrationStatus('token'),
        /push-invalid-response/,
    );
});
