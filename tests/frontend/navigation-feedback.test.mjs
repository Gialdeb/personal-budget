import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { bindNavigationFeedback } from '../../resources/js/lib/navigation-feedback.js';

function fixture() {
    globalThis.window = { location: { origin: 'https://budget.test' } };
    const handlers = new Map();
    const changes = [];
    const router = {
        on(name, callback) {
            handlers.set(name, callback);

            return () => handlers.delete(name);
        },
    };
    const stop = bindNavigationFeedback(router, (url) => changes.push(url));
    const emit = (name, visit) => handlers.get(name)?.({ detail: { visit } });
    const visit = (options = {}) => ({
        url: new URL('https://budget.test/dashboard'),
        method: 'get',
        only: [],
        async: false,
        prefetch: false,
        showProgress: true,
        ...options,
    });

    return { emit, visit, changes, handlers, stop };
}

for (const outcome of [
    'completed',
    'cancelled',
    'interrupted',
    'validation error',
    'network error',
]) {
    test(`navigation starts synchronously and finish clears ${outcome}`, () => {
        const { emit, visit, changes } = fixture();
        const request = visit();
        emit('start', request);
        assert.deepEqual(changes, ['https://budget.test/dashboard']);
        emit('finish', Object.assign(request, { [outcome]: true }));
        assert.equal(changes.at(-1), null);
    });
}

test('late finish cannot clear feedback for a replacement navigation', () => {
    const { emit, visit, changes } = fixture();
    const first = visit();
    const second = visit({ url: new URL('https://budget.test/transactions') });
    emit('start', first);
    emit('start', second);
    emit('finish', first);
    assert.equal(changes.at(-1), 'https://budget.test/transactions');
    emit('finish', second);
    assert.equal(changes.at(-1), null);
});

test('duplicate navigation is cancelled but other destinations and partial reloads remain usable', () => {
    const { emit, visit } = fixture();
    emit('start', visit());
    assert.equal(emit('before', visit()), false);
    assert.equal(
        emit('before', visit({ url: new URL('https://budget.test/reports') })),
        undefined,
    );
    assert.equal(emit('before', visit({ only: ['dashboard'] })), undefined);
    assert.equal(emit('before', visit({ method: 'post' })), undefined);
});

test('prefetches, silent visits and async reloads never alter foreground feedback', () => {
    const { emit, visit, changes } = fixture();

    for (const options of [
        { prefetch: true },
        { showProgress: false },
        { async: true },
    ]) {
        const request = visit(options);
        emit('start', request);
        emit('finish', request);
    }

    assert.deepEqual(changes, []);
});

test('teardown removes listeners and clears state', () => {
    const { emit, visit, handlers, stop, changes } = fixture();
    emit('start', visit());
    stop();
    assert.equal(handlers.size, 0);
    assert.equal(changes.at(-1), null);
});

test('global feedback is accessible, fixed, motion free and introduces no network or worker hooks', () => {
    const read = (path) =>
        readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
    const component = read('resources/js/components/NavigationProgress.vue');
    assert.match(component, /role="status"/);
    assert.match(component, /aria-live="polite"/);
    assert.match(component, /pointer-events-none fixed/);
    assert.match(component, /t\('app.common.loading'\)/);
    assert.match(component, /Teleport to="body"/);
    assert.match(component, /z-\[1000\]/);
    assert.match(component, /h-\[3px\]/);
    assert.match(component, /prefers-reduced-motion: reduce/);
    assert.match(component, /180 -/);
    assert.match(
        read('resources/js/components/MobileBottomNav.vue'),
        /data-navigation-pending/,
    );
    const app = read('resources/js/app.ts');
    assert.match(app, /initializeNavigationFeedback\(\)/);
    assert.match(app, /h\(NavigationProgress\)/);
    assert.match(app, /progress: false/);
    assert.doesNotMatch(
        read('resources/js/lib/navigation-feedback.js'),
        /fetch\(|setInterval|setTimeout|serviceWorker/,
    );
});
