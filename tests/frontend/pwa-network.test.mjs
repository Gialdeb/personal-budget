import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const source = readFileSync('resources/js/lib/pwa-network.ts', 'utf8').replace(
    /^import .*;$/gm,
    '',
);
function network() {
    const events = {};
    const routerEvents = {};
    const timers = new Map();
    const intervals = new Map();
    const states = [];
    const requests = [];
    let sequence = 0;
    let cancelCount = 0;
    let responseStatus = 200;
    let hangs = false;
    const scope = {
        exports: {},
        AbortController,
        Event,
        navigator: { onLine: true },
        document: {
            hidden: false,
            querySelector: () => ({ content: '/asset-version' }),
            addEventListener(name, fn) {
                events[name] = fn;
            },
            removeEventListener(name) {
                delete events[name];
            },
        },
        window: {
            addEventListener(name, fn) {
                events[name] = fn;
            },
            removeEventListener(name) {
                delete events[name];
            },
            dispatchEvent(event) {
                events[event.type]?.(event);
            },
        },
        router: {
            on(name, fn) {
                routerEvents[name] = fn;

                return () => {
                    delete routerEvents[name];
                };
            },
            cancel() {
                cancelCount++;
            },
        },
        setTimeout(fn, ms) {
            const id = ++sequence;
            timers.set(id, { fn, ms });

            return id;
        },
        clearTimeout(id) {
            timers.delete(id);
        },
        setInterval(fn) {
            const id = ++sequence;
            intervals.set(id, fn);

            return id;
        },
        clearInterval(id) {
            intervals.delete(id);
        },
        fetch(url, options) {
            requests.push({ url, options });

            if (hangs) {
                return new Promise((resolve, reject) => {
                    options.signal.addEventListener('abort', () =>
                        reject(new Error('timeout')),
                    );
                });
            }

            return Promise.resolve({
                ok: responseStatus === 200,
                status: responseStatus,
            });
        },
    };
    vm.runInNewContext(
        ts.transpileModule(source, {
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                target: ts.ScriptTarget.ES2022,
            },
        }).outputText,
        scope,
    );
    const stop = scope.exports.startPwaNetwork((state) => states.push(state));

    return {
        scope,
        states,
        events,
        routerEvents,
        timers,
        intervals,
        requests,
        stop,
        setStatus: (status) => {
            responseStatus = status;
        },
        hang: () => {
            hangs = true;
        },
        resume: () => {
            hangs = false;
        },
        cancelCount: () => cancelCount,
    };
}
const tick = () => new Promise((resolve) => setImmediate(resolve));

test('offline and server unavailability have distinct states and recover automatically without navigation or writes', async () => {
    const h = network();
    await tick();
    h.scope.navigator.onLine = false;
    h.events.offline();
    assert.equal(h.states.at(-1), 'offline');
    assert.equal(h.cancelCount(), 0);
    h.setStatus(503);
    h.scope.navigator.onLine = true;
    h.events.online();
    await tick();
    assert.equal(h.states.at(-1), 'server');
    h.setStatus(200);
    [...h.intervals.values()][0]();
    await tick();
    assert.equal(h.states.at(-1), 'online');
    assert.equal(h.cancelCount(), 0);
    assert.ok(
        h.requests.every(
            ({ url, options }) =>
                url === '/asset-version' &&
                options.cache === 'no-store' &&
                options.credentials === 'omit' &&
                !options.method,
        ),
    );
    h.stop();
    assert.equal(h.intervals.size, 0);
    assert.equal(Object.keys(h.events).length, 0);
});

test('a stalled probe times out and recovery checks never retry a submitted form', async () => {
    const h = network();
    await tick();
    h.hang();
    h.events.online();
    [...h.timers.values()].find(({ ms }) => ms === 5000).fn();
    await tick();
    assert.equal(h.states.at(-1), 'network');
    const visit = { method: 'post' };
    h.routerEvents.start({ detail: { visit } });
    assert.equal(
        [...h.timers.values()].filter(({ ms }) => ms === 10000).length,
        0,
    );
    h.scope.navigator.onLine = false;
    h.events.offline();
    assert.equal(h.cancelCount(), 0);
    h.resume();
    h.scope.navigator.onLine = true;
    h.events.online();
    await tick();
    assert.equal(h.states.at(-1), 'online');
    assert.equal(h.cancelCount(), 0);
    h.stop();
});

test('a timed out GET navigation retains the current page and is cancelled without replay', async () => {
    const h = network();
    await tick();
    h.routerEvents.start({ detail: { visit: { method: 'get' } } });
    [...h.timers.values()].find(({ ms }) => ms === 10000).fn();
    assert.equal(h.cancelCount(), 1);
    assert.equal(h.states.at(-1), 'network');
    h.stop();
});

test('runtime network and server errors suppress the technical response modal but not unrelated errors', async () => {
    const h = network();
    await tick();
    let prevented = 0;
    h.routerEvents.exception({
        detail: { exception: new Error('Network Error') },
        preventDefault() {
            prevented++;
        },
    });
    assert.equal(h.states.at(-1), 'network');
    h.routerEvents.invalid({
        detail: { response: { status: 502 } },
        preventDefault() {
            prevented++;
        },
    });
    assert.equal(h.states.at(-1), 'server');
    h.routerEvents.exception({
        detail: { exception: new Error('Invalid implementation') },
        preventDefault() {
            prevented++;
        },
    });
    assert.equal(prevented, 2);
    h.stop();
});

test('a background GET cannot arm a timer that cancels an ongoing form submission', async () => {
    const h = network();
    await tick();
    h.routerEvents.start({ detail: { visit: { method: 'post' } } });
    h.routerEvents.start({ detail: { visit: { method: 'get', async: true } } });
    assert.equal(
        [...h.timers.values()].filter(({ ms }) => ms === 10000).length,
        0,
    );
    assert.equal(h.cancelCount(), 0);
    h.stop();
});
