import { router } from '@inertiajs/vue3';

export type NetworkState = 'online' | 'offline' | 'network' | 'server';

/** Only retries a read-only availability check, never a form submission. */
export function startPwaNetwork(
    change: (state: NetworkState) => void,
): () => void {
    let state: NetworkState = navigator.onLine ? 'online' : 'offline';
    let checking = false;
    let stopped = false;
    let pending: object | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let probe: AbortController | null = null;
    const update = (value: NetworkState) => {
        state = value;
        change(value);
    };
    const check = async () => {
        if (checking || stopped) {
            return;
        }

        if (!navigator.onLine) {
            update('offline');

            return;
        }

        const endpoint = document.querySelector<HTMLMetaElement>(
            'meta[name="soamco-asset-version-endpoint"]',
        )?.content;

        if (!endpoint) {
            return;
        }

        checking = true;
        probe = new AbortController();
        const timeout = setTimeout(() => probe?.abort(), 5000);

        try {
            const response = await fetch(endpoint, {
                cache: 'no-store',
                credentials: 'omit',
                signal: probe.signal,
            });

            if (!stopped) {
                update(
                    response.ok
                        ? 'online'
                        : response.status >= 500
                          ? 'server'
                          : 'network',
                );

                if (response.ok) {
                    window.dispatchEvent(new Event('soamco:network-restored'));
                }
            }
        } catch {
            if (!stopped) {
                update(navigator.onLine ? 'network' : 'offline');
            }
        } finally {
            clearTimeout(timeout);
            checking = false;
        }
    };
    const offline = () => update('offline');
    const online = () => {
        void check();
    };
    const visible = () => {
        if (!document.hidden && state !== 'online') {
            void check();
        }
    };
    const networkError = () => update(navigator.onLine ? 'network' : 'offline');
    const serverError = () => update('server');
    window.addEventListener('offline', offline);
    window.addEventListener('online', online);
    window.addEventListener('soamco:network-error', networkError);
    window.addEventListener('soamco:server-error', serverError);
    window.addEventListener('soamco:retry-network', online);
    document.addEventListener('visibilitychange', visible);
    const stops = [
        router.on('start', ({ detail: { visit } }) => {
            if (visit.async || visit.prefetch) {
                return;
            }

            clearTimeout(timer);
            pending = visit;

            if (visit.method === 'get') {
                timer = setTimeout(() => {
                    if (pending !== visit) {
                        return;
                    }

                    pending = null;
                    router.cancel();
                    networkError();
                }, 10000);
            }
        }),
        router.on('finish', ({ detail: { visit } }) => {
            if (pending === visit) {
                pending = null;
                clearTimeout(timer);
            }
        }),
        router.on('exception', (event) => {
            if (
                !navigator.onLine ||
                /network|fetch|timeout|chunk|dynamically imported/i.test(
                    event.detail.exception.message,
                )
            ) {
                event.preventDefault();
                networkError();
            }
        }),
        router.on('invalid', (event) => {
            if (event.detail.response.status >= 500) {
                event.preventDefault();
                serverError();
            }
        }),
    ];
    const interval = setInterval(() => {
        if (!document.hidden && state !== 'online') {
            void check();
        }
    }, 15000);
    update(state);
    void check();

    return () => {
        stopped = true;
        probe?.abort();
        clearTimeout(timer);
        clearInterval(interval);
        stops.forEach((stop) => stop());
        window.removeEventListener('offline', offline);
        window.removeEventListener('online', online);
        window.removeEventListener('soamco:network-error', networkError);
        window.removeEventListener('soamco:server-error', serverError);
        window.removeEventListener('soamco:retry-network', online);
        document.removeEventListener('visibilitychange', visible);
    };
}
