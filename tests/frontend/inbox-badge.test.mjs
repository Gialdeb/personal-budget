import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';
import * as vue from 'vue';

const read = (path) =>
    readFileSync(new URL('../../' + path, import.meta.url), 'utf8');

function worker(fetcher, badge = {}) {
    const listeners = {};
    const config = {
        cache_names: {},
        firebase_messaging: null,
        notification_inbox_url: '/notifications/preview',
    };
    const script = read('resources/views/pwa/service-worker.blade.php')
        .replace(/\{!! .*file_get_contents.* !!\}/g, () =>
            JSON.stringify('offline'),
        )
        .replace(
            /^const config = .*;$/m,
            `const config = ${JSON.stringify(config)};`,
        )
        .replace(/'{{ \$config\['version'\] }}'/g, "'test'")
        .replace(/'{{ \$config\['offline_url'\] }}'/g, "'/offline.html'");
    const sandbox = {
        URL,
        console,
        fetch: fetcher,
        self: {
            navigator: badge,
            location: new URL('https://soamco.test/service-worker.js'),
            addEventListener: (type, callback) => {
                listeners[type] = callback;
            },
        },
    };
    runInNewContext(script, sandbox);

    return { sync: () => sandbox.syncInboxBadge(), listeners };
}
const response = (count) => ({
    ok: true,
    json: async () => ({ unread_count: count }),
});

test('worker uses authenticated uncached current counts and clears zero', async () => {
    const calls = [];
    let count = 7;
    const instance = worker(
        async (url, options) => {
            assert.equal(url, 'https://soamco.test/notifications/preview');
            assert.equal(options.credentials, 'same-origin');
            assert.equal(options.cache, 'no-store');
            assert.equal(options.redirect, 'error');

            return response(count);
        },
        {
            setAppBadge: async (value) => calls.push(value),
            clearAppBadge: async () => calls.push(0),
        },
    );
    await instance.sync();
    count = 0;
    await instance.sync();
    assert.deepEqual(calls, [7, 0]);
});

test('an older push response cannot overwrite a newer foreground count', async () => {
    const resolves = [];
    const calls = [];
    const instance = worker(
        () => new Promise((resolve) => resolves.push(resolve)),
        {
            setAppBadge: async (value) => calls.push(value),
            clearAppBadge: async () => calls.push(0),
        },
    );
    const older = instance.sync();
    const newer = instance.sync();
    resolves[1](response(0));
    await newer;
    resolves[0](response(8));
    await older;
    assert.deepEqual(calls, [0]);
});

test('badge writes themselves are serialized', async () => {
    let release;
    let count = 4;
    const calls = [];
    const instance = worker(async () => response(count), {
        setAppBadge: (value) => {
            calls.push(value);

            return new Promise((resolve) => {
                release = resolve;
            });
        },
        clearAppBadge: async () => calls.push(0),
    });
    const first = instance.sync();
    await new Promise(setImmediate);
    count = 0;
    const second = instance.sync();
    await new Promise(setImmediate);
    assert.deepEqual(calls, [4]);
    release();
    await Promise.all([first, second]);
    assert.deepEqual(calls, [4, 0]);
});

test('unsupported APIs, expired sessions, invalid counts and network/API failures stay silent', async () => {
    await worker(() => {
        throw new Error('must not fetch');
    }).sync();

    for (const fetcher of [
        async () => ({ ok: false, status: 401 }),
        async () => ({ ok: true, redirected: true }),
        async () => response(-1),
        async () => response('3'),
        async () => {
            throw new Error('offline');
        },
        async () => response(2),
    ]) {
        await worker(fetcher, {
            setAppBadge: async () => {
                throw new Error('denied');
            },
            clearAppBadge: async () => {},
        }).sync();
    }
});

test('foreground opening, read responses and visibility changes request the same worker coordinator', async () => {
    const mounts = [],
        unmounts = [],
        stops = [],
        messages = [];
    const events = new Map();
    const page = vue.reactive({
        props: {
            auth: { user: { uuid: 'user' } },
            notificationInbox: {
                unread_count: 3,
                latest: [],
                index_url: '/notifications',
                preview_url: '/notifications/preview',
                mark_all_read_url: '/notifications/mark-all-read',
            },
        },
    });
    const document = {
        visibilityState: 'visible',
        addEventListener: (name, fn) => events.set(name, fn),
        removeEventListener: (name) => events.delete(name),
    };
    const exports = {};
    runInNewContext(
        ts.transpileModule(
            read('resources/js/composables/useNotificationInboxRealtime.ts'),
            {
                compilerOptions: {
                    module: ts.ModuleKind.CommonJS,
                    target: ts.ScriptTarget.ES2022,
                },
            },
        ).outputText,
        {
            exports,
            document,
            navigator: {
                setAppBadge() {},
                serviceWorker: {
                    controller: {
                        postMessage: (message) => messages.push(message.type),
                    },
                },
            },
            require: (name) => {
                if (name === '@inertiajs/vue3') {
                    return { usePage: () => page };
                }

                if (name === 'vue') {
                    return {
                        ...vue,
                        onMounted: (fn) => mounts.push(fn),
                        onBeforeUnmount: (fn) => unmounts.push(fn),
                        watch: (...args) => {
                            const stop = vue.watch(...args);
                            stops.push(stop);

                            return stop;
                        },
                    };
                }

                if (name === '@/lib/realtime/echo') {
                    return { listenOnPrivateChannel: () => () => {} };
                }

                throw new Error(name);
            },
        },
    );
    const inbox = exports.useNotificationInboxRealtime();
    mounts.forEach((fn) => fn());
    assert.equal(messages.length, 1);
    inbox.replaceNotificationPreview({ unread_count: 2, latest: [] });
    await vue.nextTick();
    inbox.replaceNotificationPreview({ unread_count: 0, latest: [] });
    await vue.nextTick();
    assert.equal(messages.length, 3);
    document.visibilityState = 'hidden';
    events.get('visibilitychange')();
    assert.equal(messages.length, 3);
    document.visibilityState = 'visible';
    events.get('visibilitychange')();
    assert.equal(messages.length, 4);
    assert.ok(messages.every((type) => type === 'SYNC_INBOX_BADGE'));
    unmounts.forEach((fn) => fn());
    stops.forEach((fn) => fn());
    assert.equal(events.size, 0);
});
