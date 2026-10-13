import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/lib/session-isolation.ts', import.meta.url),
    'utf8',
);
const js = ts.transpile(
    source.replace(/^import .*;$/gm, '').replace('export function', 'function'),
    { target: ts.ScriptTarget.ES2022 },
);

function harness(userUuid) {
    const events = {};
    const state = { hidden: true, reloads: 0, uuid: userUuid, requests: [] };
    const scope = {
        AbortController,
        navigator: { onLine: true },
        document: {
            getElementById: () => null,
            hidden: false,
            documentElement: {
                lang: 'it',
                setAttribute: () => {
                    state.hidden = true;
                },
                removeAttribute: () => {
                    state.hidden = false;
                },
            },
            addEventListener: (name, callback) => {
                events[name] = callback;
            },
        },
        window: {
            addEventListener: (name, callback) => {
                events[name] = callback;
            },
            location: {
                reload: () => {
                    state.reloads++;
                },
            },
            setTimeout: (callback) => {
                state.timeout = callback;

                return 1;
            },
            clearTimeout: () => {},
        },
        localStorage: { setItem: () => {} },
        router: {
            on: (name, callback) => {
                events[name] = callback;
            },
        },
        identity: { url: () => '/session/identity?check=nonce' },
        fetch: async (url, options) => {
            state.requests.push({ url, options });

            return {
                ok: true,
                status: 200,
                json: async () => ({ user_uuid: state.uuid }),
            };
        },
    };
    runInNewContext(
        `${js}; this.initialize = initializeSessionIsolation;`,
        scope,
    );

    return {
        state,
        events,
        scope,
        initialize: async (uuid) => {
            scope.initialize(uuid);
            await new Promise((resolve) => setImmediate(resolve));

            return !state.hidden;
        },
    };
}

test('a cached admin payload is concealed when the real session belongs to A', async () => {
    const h = harness('user-A');
    assert.equal(await h.initialize('admin'), false);
    assert.equal(h.state.hidden, true);
    assert.equal(h.state.reloads, 1);
    assert.equal(h.state.requests[0].options.cache, 'no-store');
});

test('offline and browser history conceal private data until session revalidation', async () => {
    const h = harness('user-A');
    assert.equal(await h.initialize('user-A'), true);
    h.events.offline();
    assert.equal(h.state.hidden, true);
    h.events.pagehide();
    h.state.uuid = 'user-B';
    h.events.pageshow({ persisted: true });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, true);
    assert.equal(h.state.reloads, 1);
});

test('a different account in another tab forces fresh rendering without retaining private stores', async () => {
    const h = harness('admin');
    await h.initialize('admin');
    h.state.uuid = 'user-A';
    h.events.storage({ key: 'soamco-session-change' });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, true);
    assert.equal(h.state.reloads, 1);
});

test('public pages do not check the session or install private lifecycle listeners', async () => {
    const h = harness(null);
    await h.initialize(null);
    assert.equal(h.state.requests.length, 0);
    assert.equal(h.state.hidden, false);
    assert.equal(h.events.visibilitychange, undefined);
    assert.doesNotMatch(source, /setInterval/);
});

test('visibility return and BFCache perform event driven revalidation', async () => {
    const h = harness('A');
    await h.initialize('A');
    h.scope.document.hidden = true;
    h.events.visibilitychange();
    assert.equal(h.state.hidden, true);
    h.scope.document.hidden = false;
    h.events.visibilitychange();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, false);
    assert.equal(h.state.requests.length, 2);
});

test('concurrent checks abort the earlier request and an obsolete response cannot reveal data', async () => {
    const h = harness('A');
    await h.initialize('A');
    const pending = [];
    h.scope.fetch = (url, options) =>
        new Promise((resolve) => pending.push({ resolve, options }));
    h.events.online();
    h.events.pageshow({ persisted: true });
    assert.equal(pending[0].options.signal.aborted, true);
    pending[0].resolve({
        ok: true,
        status: 200,
        json: async () => ({ user_uuid: 'A' }),
    });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, true);
    pending[1].resolve({
        ok: true,
        status: 200,
        json: async () => ({ user_uuid: 'A' }),
    });
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, false);
});

test('offline aborts an ongoing check so it cannot reveal the UI later', async () => {
    const h = harness('A');
    await h.initialize('A');
    let resolve;
    h.scope.fetch = () =>
        new Promise((done) => {
            resolve = done;
        });
    h.events.online();
    h.scope.navigator.onLine = false;
    h.events.offline();
    resolve({ ok: true, status: 200, json: async () => ({ user_uuid: 'A' }) });
    await new Promise((done) => setImmediate(done));
    assert.equal(h.state.hidden, true);
});

for (const status of [401, 419, 503]) {
    test(`status ${status} reloads without revealing private content`, async () => {
        const h = harness('A');
        h.scope.fetch = async () => ({ ok: false, status });
        await h.initialize('A');
        assert.equal(h.state.hidden, true);
        assert.equal(h.state.reloads, 1);
    });
}

test('timeout aborts the request and leaves private data concealed', async () => {
    const h = harness('A');
    h.scope.fetch = (url, options) =>
        new Promise((resolve, reject) => {
            options.signal.addEventListener('abort', () =>
                reject(new Error('timeout')),
            );
        });
    await h.initialize('A');
    h.state.timeout();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, true);
    assert.equal(h.state.reloads, 0);
});

test('an online server error shows a neutral retry instead of claiming offline', async () => {
    const h = harness('A');
    const nodes = [];
    const panel = {
        hidden: true,
        replaceChildren: (...children) => {
            nodes.splice(0, nodes.length, ...children);
        },
    };
    h.scope.document.getElementById = () => panel;
    h.scope.document.createElement = () => ({ addEventListener() {} });
    h.scope.fetch = async () => ({ ok: false, status: 500 });
    await h.initialize('A');
    assert.equal(h.state.hidden, true);
    assert.equal(nodes[2].textContent, 'Riprova');
    assert.match(nodes[1].textContent, /verificare la sessione/);
    assert.equal(h.state.reloads, 0);
});

test('a network failure while online offers retry instead of declaring offline', async () => {
    const h = harness('A');
    const nodes = [];
    h.scope.document.getElementById = () => ({
        replaceChildren: (...items) => nodes.push(...items),
    });
    h.scope.document.createElement = () => ({ addEventListener() {} });
    h.scope.fetch = async () => {
        throw new Error('transport failure');
    };
    await h.initialize('A');
    assert.equal(nodes[2].textContent, 'Riprova');
    assert.equal(h.state.hidden, true);
});

test('expired session returning null identity reloads without revealing the private page', async () => {
    const h = harness(null);
    await h.initialize('A');
    assert.equal(h.state.reloads, 1);
    assert.equal(h.state.hidden, true);
});

test('login and logout publish a cross tab signal even when navigating to a public surface', async () => {
    const h = harness('A');
    let signal;
    h.scope.localStorage.setItem = (key) => {
        signal = key;
    };
    await h.initialize('A');
    h.events.navigate({
        detail: {
            page: {
                props: {
                    auth: { user: null },
                    sessionVerificationRequired: false,
                },
            },
        },
    });
    assert.equal(signal, 'soamco-session-change');
    assert.equal(h.state.reloads, 1);
});

test('a public surface remains accessible even with a signed in user and unavailable identity endpoint', async () => {
    const h = harness('A');
    h.scope.navigator.onLine = false;
    h.scope.fetch = async () => {
        throw new Error('unavailable');
    };
    h.scope.initialize('A', false);
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(h.state.hidden, false);
    assert.equal(h.state.requests.length, 0);
});

test('guest login does not depend on identity fetch even offline or during identity server errors', async () => {
    const h = harness(null);
    h.scope.navigator.onLine = false;
    let calls = 0;
    h.scope.fetch = async () => {
        calls++;

        throw new Error('server error');
    };
    await h.initialize(null);
    assert.equal(calls, 0);
    assert.equal(h.state.hidden, false);
});
