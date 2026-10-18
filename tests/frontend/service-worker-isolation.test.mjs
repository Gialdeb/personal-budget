import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(
    new URL(
        '../../resources/views/pwa/service-worker.blade.php',
        import.meta.url,
    ),
    'utf8',
);
const config = {
    version: 'security-test',
    cache_names: { static: 'static', images: 'images' },
    offline_url: '/offline.html',
    precache_urls: [],
    cache_prefix: 'soamco-',
    static_asset_path_prefixes: ['/build/assets/'],
    stable_image_path_prefixes: ['/images/'],
    firebase_messaging: null,
};
const offline = readFileSync(
    new URL('../../public/offline.html', import.meta.url),
    'utf8',
);
const script = source
    .replace(/\{!! .*file_get_contents.* !!\}/g, () => JSON.stringify(offline))
    .replace(
        /^const config = .*;$/m,
        `const config = ${JSON.stringify(config)};`,
    )
    .replace(/'{{ \$config\['version'\] }}'/g, "'security-test'")
    .replace(/'{{ \$config\['offline_url'\] }}'/g, "'/offline.html'");

function worker(
    fetcher,
    { offline = false, sdkFailure = false, timeout = false } = {},
) {
    const listeners = {};
    const cacheReads = [];
    const cacheWrites = [];
    const context = {
        firebase: sdkFailure
            ? undefined
            : {
                  initializeApp() {},
                  messaging: () => ({ onBackgroundMessage() {} }),
              },
        URL,
        AbortController,
        setTimeout: timeout
            ? (callback) => {
                  queueMicrotask(callback);

                  return 1;
              }
            : setTimeout,
        clearTimeout,
        Request,
        Response,
        console,
        self: {
            navigator: { onLine: !offline },
            location: new URL('https://security.test/service-worker.js'),
            registration: {},
            importScripts() {
                if (sdkFailure) {
                    throw new Error('CDN unreachable');
                }
            },
            addEventListener: (name, callback) => {
                listeners[name] = callback;
            },
        },
        fetch: fetcher,
        caches: {
            open: async () => ({
                match: async (key) => {
                    cacheReads.push(key);

                    return new Response(
                        key === '/offline.html'
                            ? 'Neutral offline page'
                            : 'PRIVATE ADMIN DATA',
                    );
                },
                put: async (...args) => {
                    cacheWrites.push(args);
                },
            }),
        },
    };
    const workerScript = sdkFailure
        ? script.replace(
              '"firebase_messaging":null',
              '"firebase_messaging":{"apiKey":"test","projectId":"test","appId":"test","messagingSenderId":"test"}',
          )
        : script;
    runInNewContext(workerScript, context);

    return { context, listeners, cacheReads, cacheWrites };
}

for (const path of [
    '/dashboard',
    '/transactions/2026/10',
    '/settings',
    '/admin',
    '/session/identity',
]) {
    test(`private ${path} uses network without reading a cached admin response`, async () => {
        const requests = [];
        const h = worker(async (request) => {
            requests.push(request);

            return new Response('USER A DATA');
        });
        let response;
        const request = new Request(`https://security.test${path}`);
        h.listeners.fetch({
            request,
            respondWith: (promise) => {
                response = promise;
            },
        });
        assert.equal(await (await response).text(), 'USER A DATA');
        assert.equal(requests[0].cache, 'no-store');
        assert.equal(h.cacheReads.length, 0);
        assert.equal(h.cacheWrites.length, 0);
    });
}

test('offline navigation returns only neutral offline HTML and never the cached dashboard', async () => {
    const h = worker(async () => {
        throw new Error('offline');
    });
    const response = await h.context.handleNavigationRequest({
        request: new Request('https://security.test/dashboard'),
    });
    assert.match(await response.text(), /Soamco · Offline/);
    assert.deepEqual(h.cacheReads, []);
});

test('maintenance keeps its 503 response instead of falling back to cached account data', async () => {
    const h = worker(async () => new Response('Maintenance', { status: 503 }));
    const response = await h.context.handleNavigationRequest({
        request: new Request('https://security.test/dashboard'),
    });
    assert.equal(response.status, 503);
    assert.match(await response.text(), /data-network-state="server"/);
    assert.equal(h.cacheReads.length, 0);
});

test('a private HTML response cannot be reused as a cached static asset', async () => {
    const h = worker(
        async () =>
            new Response('public JS', {
                headers: { 'Content-Type': 'application/javascript' },
            }),
    );
    const response = await h.context.cacheFirst(
        new Request('https://security.test/build/assets/app.js'),
        'static',
    );
    assert.equal(await response.text(), 'public JS');
    assert.equal(h.cacheWrites.length, 0);
    assert.equal(
        h.context.isCacheableResponse({
            ok: true,
            type: 'basic',
            redirected: false,
            headers: new Headers({
                'Content-Type': 'text/html',
                'Cache-Control': 'private',
            }),
        }),
        false,
    );
});

test('public static assets still populate their cache', async () => {
    const asset = {
        ok: true,
        type: 'basic',
        redirected: false,
        headers: new Headers({
            'Content-Type': 'application/javascript',
            'Cache-Control': 'public, max-age=31536000',
        }),
        clone() {
            return this;
        },
    };
    const h = worker(async () => asset);
    await h.context.cacheFirst(
        new Request('https://security.test/build/assets/app.js'),
        'static',
    );
    assert.equal(h.cacheWrites.length, 1);
});

test('one unavailable precache asset does not abort worker installation', async () => {
    const h = worker(async () => {
        throw new Error('unavailable');
    });
    runInNewContext(
        "PRECACHE_URLS.push('/build/assets/missing.js');",
        h.context,
    );
    let activated = false;
    h.context.self.skipWaiting = () => {
        activated = true;
    };
    let installation;
    h.listeners.install({
        waitUntil: (promise) => {
            installation = promise;
        },
    });
    await installation;
    assert.equal(activated, true);
    assert.equal(h.cacheWrites.length, 0);
});

test('unavailable optional Firebase SDK cannot prevent offline navigation', async () => {
    const h = worker(
        async () => {
            throw new Error('offline');
        },
        { sdkFailure: true },
    );
    assert.equal(typeof h.listeners.fetch, 'function');
    const response = await h.context.handleNavigationRequest({
        request: new Request('https://security.test/dashboard'),
    });
    assert.match(await response.text(), /data-network-state="network"/);
    assert.deepEqual(h.cacheWrites, []);
});

test('known offline navigation is immediate and never attempts the network', async () => {
    const h = worker(
        () => {
            assert.fail('Offline startup must not wait for a network request');
        },
        { offline: true, sdkFailure: true },
    );
    const response = await h.context.handleNavigationRequest({
        request: new Request('https://security.test/dashboard'),
    });
    assert.match(await response.text(), /data-network-state="offline"/);
    assert.equal(response.headers.get('Cache-Control'), 'no-store');
});

test('a stalled navigation is aborted and shows a neutral network fallback', async () => {
    let signal;
    const h = worker(
        (request) => {
            signal = request.signal;

            return new Promise(() => {});
        },
        { timeout: true },
    );
    const response = await h.context.handleNavigationRequest({
        request: new Request('https://security.test/dashboard'),
    });
    assert.equal(signal.aborted, true);
    assert.match(await response.text(), /data-network-state="network"/);
    assert.deepEqual(h.cacheReads, []);
});

test('server failures retain a different explanation from offline connectivity', async () => {
    const h = worker(async () => new Response('', { status: 502 }));
    const response = await h.context.handleNavigationRequest({
        request: new Request('https://security.test/dashboard'),
    });
    assert.match(await response.text(), /data-network-state="server"/);
    assert.deepEqual(h.cacheWrites, []);
    assert.equal(h.context.hasValidFirebaseConfig({}), false);
});
