import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { createOnboardingStore } from '../../resources/js/lib/onboarding-state.js';
import {
    findOnboardingTarget,
    onboardingPosition,
    trapOnboardingFocus,
} from '../../resources/js/lib/onboarding-tour.js';

const state = (revision = 0, tours = {}) => ({
    enabled: true,
    tours,
    quick_start_dismissed: false,
    legacy_imported: true,
    revision,
});

test('requests are serialized, stale Inertia history cannot replace newer preferences, and fresh shared state is accepted', async () => {
    let latest;
    const calls = [];
    let resolveFirst;
    const store = createOnboardingStore(
        async (body) => {
            calls.push(body.action);

            if (body.action === 'complete') {
                return new Promise((resolve) => {
                    resolveFirst = resolve;
                });
            }

            return { ...state(2), enabled: false };
        },
        (value) => {
            latest = value;
        },
    );
    store.sync('a', state());
    const first = store.update({ action: 'complete' });
    const second = store.update({ action: 'disable' });
    await Promise.resolve();
    assert.deepEqual(calls, ['complete']);
    resolveFirst(state(1, { dashboard: 'completed' }));
    assert.equal(await first, true);
    assert.equal(await second, true);
    assert.deepEqual(calls, ['complete', 'disable']);
    store.sync('a', state(0));
    assert.equal(latest.enabled, false);
    store.sync('a', state(3));
    assert.equal(latest.revision, 3);
});

test('identity changes invalidate in-flight and queued requests without leaking previous user state', async () => {
    let latest;
    let resolveOld;
    let sent = 0;
    const store = createOnboardingStore(
        () => {
            sent += 1;

            return new Promise((resolve) => {
                resolveOld = resolve;
            });
        },
        (value) => {
            latest = value;
        },
    );
    store.sync('a', state());
    const first = store.update({ action: 'complete' });
    const queued = store.update({ action: 'disable' });
    await Promise.resolve();
    store.sync('b', state());
    resolveOld({ ...state(5), enabled: false });
    assert.equal(await first, false);
    assert.equal(await queued, false);
    assert.equal(sent, 1);
    assert.equal(latest.enabled, true);
    assert.equal(latest.revision, 0);
});

test('failed persistence does not claim a completion and allows retry', async () => {
    let fail = true;
    let latest;
    const store = createOnboardingStore(
        async () => {
            if (fail) {
                throw new Error('offline');
            }

            return state(1, { dashboard: 'completed' });
        },
        (value) => {
            latest = value;
        },
    );
    store.sync('a', state());
    assert.equal(await store.update({ action: 'complete' }), false);
    assert.deepEqual(latest.tours, {});
    fail = false;
    assert.equal(await store.update({ action: 'complete' }), true);
    assert.equal(latest.tours.dashboard, 'completed');
});

test('responsive target selection ignores hidden variants and closed panels and rechecks dynamic targets', () => {
    const candidate = (width, closed = false) => ({
        getBoundingClientRect: () => ({ width, height: 40 }),
        closest: () => (closed ? {} : null),
        ownerDocument: {
            defaultView: {
                getComputedStyle: () => ({
                    visibility: 'visible',
                    display: 'block',
                }),
            },
        },
    });
    const desktop = candidate(0);
    const panel = candidate(100, true);
    const mobile = candidate(320);
    let candidates = [desktop, panel, mobile];
    const root = { querySelectorAll: () => candidates };
    assert.equal(findOnboardingTarget(root, 'chrome.navigation'), mobile);
    candidates = [];
    assert.equal(findOnboardingTarget(root, 'chrome.navigation'), null);
    candidates = [mobile];
    assert.equal(findOnboardingTarget(root, 'chrome.navigation'), mobile);
});

test('popover remains inside mobile visual viewport including keyboard offset and safe-area bounds', () => {
    const viewport = { left: 8, top: 40, width: 350, height: 260 };
    const point = onboardingPosition(
        { left: 340, top: 500, height: 80 },
        { width: 320, height: 220 },
        viewport,
    );
    assert.ok(point.left >= viewport.left + 12);
    assert.ok(point.top >= viewport.top + 12);
    assert.ok(point.left + 320 <= viewport.left + viewport.width - 12);
    assert.ok(point.top + 220 <= viewport.top + viewport.height - 12);
});

test('keyboard focus wraps forwards and backwards and recovers focus from the background', () => {
    let focused;
    const buttons = [0, 1].map((id) => ({
        getClientRects: () => [1],
        focus: () => {
            focused = id;
        },
    }));
    const doc = { activeElement: buttons[1] };
    const dialog = {
        querySelectorAll: () => buttons,
        ownerDocument: doc,
        contains: (el) => buttons.includes(el),
    };
    let prevented = 0;
    const event = {
        key: 'Tab',
        preventDefault: () => {
            prevented += 1;
        },
    };
    trapOnboardingFocus(event, dialog);
    assert.equal(focused, 0);
    doc.activeElement = buttons[0];
    trapOnboardingFocus({ ...event, shiftKey: true }, dialog);
    assert.equal(focused, 1);
    doc.activeElement = {};
    trapOnboardingFocus(event, dialog);
    assert.equal(focused, 0);
    assert.equal(prevented, 3);
    doc.activeElement = dialog;
    trapOnboardingFocus({ ...event, shiftKey: true }, dialog);
    assert.equal(focused, 1);
    assert.equal(prevented, 4);
});

test('supported pages register tours and the existing help remains available', () => {
    const source = (path) =>
        readFileSync(
            new URL(`../../resources/js/${path}`, import.meta.url),
            'utf8',
        );
    const definitions = source('onboarding/tours.ts');
    assert.match(definitions, /'dashboard\/Analysis': 'dashboard'/);
    assert.match(definitions, /additionalScreenForComponent/);
    assert.match(definitions, /'transactions\/Show': 'transactions'/);
    assert.match(definitions, /'transactions\/recurring\/Index': 'recurring'/);
    assert.match(definitions, /SharedExpenses: 'shared-expenses'/);
    assert.equal(
        (
            source('layouts/app/AppSidebarLayout.vue').match(
                /<OnboardingTour\s*\/>/g,
            ) ?? []
        ).length,
        1,
    );
    const help = source('components/contextual-help/ContextualHelpButton.vue');
    assert.match(help, /SheetTrigger v-else/);
    assert.match(help, /onboarding\.replay/);
    assert.match(help, /PublicRichContentRenderer/);
    assert.match(help, /knowledge_article/);
    const dashboard = source('pages/dashboard/Analysis.vue');

    for (const target of [
        'welcome',
        'period',
        'accounts',
        'timeline',
        'metrics',
    ]) {
        assert.ok(dashboard.includes(`data-onboarding="dashboard.${target}"`));
    }
});

test('a newer shared response received during a write wins over an older write response', async () => {
    let latest;
    let resolve;
    const store = createOnboardingStore(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
        (value) => {
            latest = value;
        },
    );
    store.sync('a', state());
    const pending = store.update({ action: 'complete' });
    await Promise.resolve();
    store.sync('a', { ...state(5), enabled: false });
    resolve(state(3, { dashboard: 'completed' }));
    await pending;
    assert.equal(latest.revision, 5);
    assert.equal(latest.enabled, false);
});

test('a visible dropdown trigger remains a target when its menu is closed', () => {
    const trigger = {
        getBoundingClientRect: () => ({ width: 40, height: 40 }),
        ownerDocument: {
            defaultView: {
                getComputedStyle: () => ({
                    visibility: 'visible',
                    display: 'block',
                }),
            },
        },
        closest(selector) {
            return selector.split(', ').includes('[data-state="closed"]')
                ? this
                : null;
        },
    };
    assert.equal(
        findOnboardingTarget(
            { querySelectorAll: () => [trigger] },
            'chrome.help',
        ),
        trigger,
    );
});
