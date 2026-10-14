/** Bind once to official Inertia events. Background reloads and prefetches stay silent. */
export function bindNavigationFeedback(router, onChange) {
    const active = new Map();
    const visible = (visit) =>
        !visit.async && !visit.prefetch && visit.showProgress !== false;
    const key = (visit) => {
        const url = new URL(String(visit.url), window.location.origin);
        url.hash = '';

        return url.href;
    };
    const publish = () => onChange([...active.values()].at(-1) ?? null);
    const unsubscribe = [
        router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                visible(visit) &&
                visit.method === 'get' &&
                visit.only.length === 0 &&
                [...active.values()].includes(key(visit))
            ) {
                return false;
            }
        }),
        router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visible(visit)) {
                active.set(visit, key(visit));
                publish();
            }
        }),
        router.on('finish', (event) => {
            if (active.delete(event.detail.visit)) {
                publish();
            }
        }),
    ];

    return () => {
        unsubscribe.forEach((remove) => remove());
        active.clear();
        publish();
    };
}
