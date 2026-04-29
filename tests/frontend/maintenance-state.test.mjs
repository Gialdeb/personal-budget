import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const overlaySource = readFileSync(
    new URL(
        '../../resources/js/components/MaintenanceStateOverlay.vue',
        import.meta.url,
    ),
    'utf8',
);
const composableSource = readFileSync(
    new URL(
        '../../resources/js/composables/useMaintenanceState.ts',
        import.meta.url,
    ),
    'utf8',
);
const echoSource = readFileSync(
    new URL('../../resources/js/lib/realtime/echo.ts', import.meta.url),
    'utf8',
);
const middlewareSource = readFileSync(
    new URL(
        '../../app/Http/Middleware/HandleInertiaRequests.php',
        import.meta.url,
    ),
    'utf8',
);
const eventSource = readFileSync(
    new URL('../../app/Events/AppMaintenanceStateUpdated.php', import.meta.url),
    'utf8',
);
const providerSource = readFileSync(
    new URL('../../app/Providers/EventServiceProvider.php', import.meta.url),
    'utf8',
);
const messagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/app.ts', import.meta.url),
    'utf8',
);
const globalTypesSource = readFileSync(
    new URL('../../resources/js/types/global.d.ts', import.meta.url),
    'utf8',
);

test('maintenance state is shared at bootstrap and broadcast from Laravel maintenance events', () => {
    assert.match(
        middlewareSource,
        /'maintenanceState'\s*=>\s*fn \(\): array =>/,
    );
    assert.match(middlewareSource, /app\(\)->isDownForMaintenance\(\)/);
    assert.match(providerSource, /MaintenanceModeEnabled::class/);
    assert.match(providerSource, /MaintenanceModeDisabled::class/);
    assert.match(providerSource, /new AppMaintenanceStateUpdated\(true\)/);
    assert.match(providerSource, /new AppMaintenanceStateUpdated\(false\)/);
    assert.match(providerSource, /Maintenance mode activated/);
    assert.match(providerSource, /Maintenance mode deactivated/);
    assert.match(eventSource, /implements ShouldBroadcastNow/);
    assert.match(eventSource, /new Channel\('app\.maintenance'\)/);
    assert.match(eventSource, /maintenance\.state\.updated/);
});

test('maintenance state composable listens once on the public reverb channel', () => {
    assert.match(echoSource, /export function listenOnPublicChannel/);
    assert.match(echoSource, /echo\.channel\(channelName\)/);
    assert.match(composableSource, /listenOnPublicChannel/);
    assert.match(composableSource, /'app\.maintenance'/);
    assert.match(composableSource, /'maintenance\.state\.updated'/);
    assert.match(
        composableSource,
        /applyMaintenanceState\(payload, 'realtime', true\)/,
    );
    assert.match(composableSource, /realtimeSubscriptionCount/);
    assert.match(composableSource, /unsubscribeFromRealtime/);
    assert.match(composableSource, /immediate: true/);
});

test('maintenance state has cross-tab sync and status polling recovery', () => {
    assert.match(composableSource, /@\/routes\/maintenance/);
    assert.match(composableSource, /maintenanceStatus\.url\(\)/);
    assert.match(composableSource, /BroadcastChannel/);
    assert.match(composableSource, /MAINTENANCE_SYNC_STORAGE_KEY/);
    assert.match(composableSource, /window\.localStorage\.setItem/);
    assert.match(composableSource, /window\.addEventListener\('storage'/);
    assert.match(composableSource, /handleBroadcastChannelMessage/);
    assert.match(composableSource, /startMaintenanceStatusPolling/);
    assert.match(composableSource, /stopMaintenanceStatusPolling/);
    assert.match(composableSource, /window\.fetch\(maintenanceStatus\.url\(\)/);
    assert.match(
        composableSource,
        /applyMaintenanceState\(payload, 'poll', true\)/,
    );
    assert.match(composableSource, /stopMaintenanceStatusPolling\(\)/);
});

test('maintenance overlay is mounted globally and blocks the full viewport', () => {
    assert.match(appSource, /import MaintenanceStateOverlay/);
    assert.match(appSource, /h\(MaintenanceStateOverlay\)/);
    assert.match(appSource, /data-maintenance-content-root/);
    assert.match(overlaySource, /data-test="maintenance-state-overlay"/);
    assert.match(overlaySource, /Teleport to="body"/);
    assert.match(overlaySource, /fixed inset-0/);
    assert.match(overlaySource, /min-h-[[]100dvh]/);
    assert.match(overlaySource, /pointer-events-auto/);
    assert.match(overlaySource, /role="alertdialog"/);
    assert.match(overlaySource, /aria-modal="true"/);
    assert.match(overlaySource, /overflow-hidden/);
    assert.match(overlaySource, /document\.activeElement\.blur\(\)/);
    assert.match(overlaySource, /setAttribute\('inert', ''\)/);
    assert.match(overlaySource, /setAttribute\('aria-hidden', 'true'\)/);
    assert.doesNotMatch(
        overlaySource,
        /DialogClose|@escape-key-down|@pointer-down-outside/,
    );
});

test('maintenance copy and shared types are available in both locales', () => {
    assert.match(
        globalTypesSource,
        /maintenanceState\?: MaintenanceStateSharedData \| null/,
    );
    assert.match(messagesSource, /Siamo in manutenzione/);
    assert.match(messagesSource, /Stiamo effettuando un aggiornamento/);
    assert.match(messagesSource, /We’re under maintenance/);
    assert.match(messagesSource, /We’re performing an update/);
});
