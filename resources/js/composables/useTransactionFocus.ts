import { router, usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** Focus only after the list commit and Inertia's scroll restoration. */
export function useTransactionFocus(options: {
    transactions: () => { uuid: string }[];
    reveal: () => void;
    highlight: (uuid: string) => void;
}) {
    const page = usePage();
    const missing = ref(false);
    let frame = 0;
    let generation = 0;
    let disposed = false;
    let consumedUrl: string | null = null;
    let sessionObserver: MutationObserver | null = null;

    function consume(url: URL): void {
        consumedUrl = page.url;
        url.searchParams.delete('highlight');
        router.replace({
            url: url.pathname + url.search + url.hash,
            preserveState: true,
            preserveScroll: true,
        });
    }

    async function schedule(): Promise<void> {
        const currentGeneration = ++generation;
        cancelAnimationFrame(frame);
        const url = new URL(page.url, window.location.origin);
        const uuid = url.searchParams.get('highlight');

        if (!uuid || consumedUrl === page.url) {
            return;
        }

        if (document.documentElement.hasAttribute('data-session-unverified')) {
            return;
        }

        missing.value = false;

        if (
            !options
                .transactions()
                .some((transaction) => transaction.uuid === uuid)
        ) {
            // The monthly sheet is complete, not paginated or deferred. No blind retries.
            missing.value = true;
            consume(url);

            return;
        }

        options.reveal();
        await nextTick();

        if (disposed || currentGeneration !== generation) {
            return;
        }

        frame = requestAnimationFrame(() => {
            if (disposed || currentGeneration !== generation) {
                return;
            }

            if (
                document.documentElement.hasAttribute('data-session-unverified')
            ) {
                return;
            }

            const target = [
                ...document.querySelectorAll<HTMLElement>(
                    `[data-transaction-row="${CSS.escape(uuid)}"]`,
                ),
            ].find((row) => row.getClientRects().length > 0);

            if (!target) {
                missing.value = true;
                consume(url);

                return;
            }

            target.focus({ preventScroll: true });
            target.scrollIntoView({ behavior: 'instant', block: 'center' });
            options.highlight(uuid);
            consume(url);
        });
    }

    const removeFinish = router.on('finish', () => void schedule());
    watch([() => page.url, options.transactions], () => void schedule(), {
        flush: 'post',
    });
    onMounted(() => {
        sessionObserver = new MutationObserver(() => void schedule());
        sessionObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-session-unverified'],
        });
        void nextTick(schedule);
    });
    onBeforeUnmount(() => {
        disposed = true;
        generation++;
        cancelAnimationFrame(frame);
        removeFinish();
        sessionObserver?.disconnect();
    });

    return { focusMissing: missing };
}
