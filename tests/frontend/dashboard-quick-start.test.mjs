import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildDashboardQuickStartStorageKey,
    persistDashboardQuickStartDismissed,
    readDashboardQuickStartDismissed,
    DASHBOARD_QUICK_START_STORAGE_PREFIX,
} from '../../resources/js/lib/dashboard-quick-start.js';

test('dashboard quick start dismiss key is isolated per user', () => {
    assert.equal(
        buildDashboardQuickStartStorageKey('user-a'),
        `${DASHBOARD_QUICK_START_STORAGE_PREFIX}user-a`,
    );
    assert.equal(
        buildDashboardQuickStartStorageKey('user-b'),
        `${DASHBOARD_QUICK_START_STORAGE_PREFIX}user-b`,
    );
    assert.notEqual(
        buildDashboardQuickStartStorageKey('user-a'),
        buildDashboardQuickStartStorageKey('user-b'),
    );
});

test('dashboard quick start dismiss persists only for the same user', () => {
    const storage = new Map();

    global.window = {
        localStorage: {
            getItem(key) {
                return storage.has(key) ? storage.get(key) : null;
            },
            setItem(key, value) {
                storage.set(key, value);
            },
        },
    };

    persistDashboardQuickStartDismissed('user-a', true);

    assert.equal(readDashboardQuickStartDismissed('user-a'), true);
    assert.equal(readDashboardQuickStartDismissed('user-b'), false);

    delete global.window;
});
