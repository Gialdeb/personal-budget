const config = {!! \Illuminate\Support\Js::from($config) !!};

const VERSION = '{{ $config['version'] }}';
const OFFLINE_URL = '{{ $config['offline_url'] }}';
const STATIC_CACHE = config.cache_names.static;
const IMAGE_CACHE = config.cache_names.images;
const CACHE_PREFIX = config.cache_prefix;
const PRECACHE_URLS = config.precache_urls;
const STATIC_ASSET_PATH_PREFIXES = config.static_asset_path_prefixes;
const STABLE_IMAGE_PATH_PREFIXES = config.stable_image_path_prefixes;
const ACTIVE_CACHE_NAMES = Object.values(config.cache_names);

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(STATIC_CACHE);

            await cache.addAll(PRECACHE_URLS);
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            if ('navigationPreload' in self.registration) {
                await self.registration.navigationPreload.enable();
            }

            const cacheNames = await caches.keys();

            await Promise.all(
                cacheNames
                    .filter(
                        (cacheName) =>
                            cacheName.startsWith(CACHE_PREFIX) &&
                            !ACTIVE_CACHE_NAMES.includes(cacheName),
                    )
                    .map((cacheName) => caches.delete(cacheName)),
            );

            await self.clients.claim();
        })(),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(event));

        return;
    }

    if (isStaticAssetRequest(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));

        return;
    }

    if (isStableImageRequest(request, url.pathname)) {
        event.respondWith(handleStableImageRequest(event));
    }
});

function isStaticAssetRequest(pathname) {
    return STATIC_ASSET_PATH_PREFIXES.some((prefix) =>
        pathname.startsWith(prefix),
    );
}

function isStableImageRequest(request, pathname) {
    if (request.destination !== 'image') {
        return false;
    }

    return STABLE_IMAGE_PATH_PREFIXES.some((prefix) =>
        pathname.startsWith(prefix),
    );
}

async function handleNavigationRequest(event) {
    try {
        const preloadResponse = await event.preloadResponse;

        if (preloadResponse) {
            return preloadResponse;
        }

        return await fetch(new Request(event.request, { cache: 'no-cache' }));
    } catch {
        const cache = await caches.open(STATIC_CACHE);
        const offlineResponse = await cache.match(OFFLINE_URL);

        if (offlineResponse) {
            return offlineResponse;
        }

        return Response.error();
    }
}

async function handleStableImageRequest(event) {
    const cache = await caches.open(IMAGE_CACHE);
    const cachedResponse = await cache.match(event.request);

    const networkResponsePromise = fetch(event.request)
        .then(async (response) => {
            if (isCacheableResponse(response)) {
                await cache.put(event.request, response.clone());
            }

            return response;
        })
        .catch(() => null);

    if (cachedResponse) {
        event.waitUntil(networkResponsePromise);

        return cachedResponse;
    }

    return (await networkResponsePromise) || Response.error();
}

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    const response = await fetch(request);

    if (isCacheableResponse(response)) {
        await cache.put(request, response.clone());
    }

    return response;
}

function isCacheableResponse(response) {
    return response.ok && response.type === 'basic';
}
