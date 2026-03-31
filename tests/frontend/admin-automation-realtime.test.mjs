import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const echoSource = readFileSync(
    new URL('../../resources/js/lib/realtime/echo.ts', import.meta.url),
    'utf8',
);

const composableSource = readFileSync(
    new URL(
        '../../resources/js/composables/useAdminAutomationRealtime.ts',
        import.meta.url,
    ),
    'utf8',
);

const pageSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/Automation/Index.vue',
        import.meta.url,
    ),
    'utf8',
);

const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);

test('realtime client is initialized through a singleton helper', () => {
    assert.match(echoSource, /window\.__soamcoBudgetEcho/);
    assert.match(echoSource, /function createRealtimeClient/);
    assert.match(echoSource, /new Echo\(/);
    assert.match(echoSource, /VITE_REVERB_APP_KEY/);
});

test('automation admin page keeps realtime wiring isolated in its composable', () => {
    assert.match(pageSource, /useAdminAutomationRealtime/);
    assert.doesNotMatch(pageSource, /new Echo\(/);
    assert.doesNotMatch(pageSource, /pusher-js/);
    assert.doesNotMatch(pageSource, /laravel-echo/);
});

test('automation realtime composable listens on the private admin channel and reloads scoped props', () => {
    assert.match(composableSource, /admin\.automation\.runs/);
    assert.match(composableSource, /automation\.run\.updated/);
    assert.match(composableSource, /router\.reload/);
    assert.match(composableSource, /only,/);
});

test('application bootstrap does not perform a second realtime initialization', () => {
    assert.doesNotMatch(appSource, /configureEcho/);
    assert.doesNotMatch(appSource, /new Echo\(/);
});
