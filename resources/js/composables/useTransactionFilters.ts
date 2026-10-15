import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Ref } from 'vue';
import {
    TRANSACTION_FILTER_STORAGE_KEY,
    clearTransactionFilterUrl,
    normalizeTransactionFilters,
    readTransactionFilterPreference,
    resolveTransactionFilters,
    storeTransactionFilterPreference,
    transactionFilterDefaults,
    transactionFilterUrl,
    transactionFiltersAreCustomized,
} from '@/lib/transaction-filter-preferences.js';

type FilterState = typeof transactionFilterDefaults;
type FilterRefs = { [Key in keyof FilterState]: Ref<FilterState[Key]> };

export function useTransactionFilters(fields: FilterRefs) {
    const page = usePage();
    const rememberFilters = ref(false);
    let initialized = false;
    let disposed = false;
    let urlSyncQueued = false;
    let applying = false;
    let syncingUrl: string | null = null;
    let stopNavigate: (() => void) | undefined;
    const localStorage = () => {
        try {
            return window.localStorage;
        } catch {
            return null;
        }
    };
    const storageKey = `${TRANSACTION_FILTER_STORAGE_KEY}:${page.props.auth.user.uuid}`;
    const snapshot = computed(
        () =>
            Object.fromEntries(
                Object.entries(fields).map(([key, field]) => [
                    key,
                    field.value,
                ]),
            ) as FilterState,
    );
    const customized = computed(() =>
        transactionFiltersAreCustomized(snapshot.value),
    );

    function apply(filters: FilterState): void {
        applying = true;
        const normalized = normalizeTransactionFilters(filters);

        for (const key of Object.keys(fields) as (keyof FilterState)[]) {
            (fields[key] as Ref<string | boolean>).value = normalized[key];
        }

        applying = false;
    }

    function syncUrl(): void {
        if (urlSyncQueued) {
            return;
        }

        urlSyncQueued = true;
        queueMicrotask(() => {
            urlSyncQueued = false;

            if (disposed) {
                return;
            }

            const url = transactionFilterUrl(page.url, snapshot.value);

            if (
                url === page.url &&
                page.props.transactionFiltersTransient ===
                    !rememberFilters.value
            ) {
                return;
            }

            syncingUrl = url;
            router.replace({
                url,
                props: (current) => ({
                    ...current,
                    transactionFiltersTransient: !rememberFilters.value,
                }),
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    syncingUrl = null;
                },
            });
        });
    }

    function save(): void {
        if (
            !storeTransactionFilterPreference(
                localStorage(),
                snapshot.value,
                rememberFilters.value,
                storageKey,
            )
        ) {
            rememberFilters.value = false;
        }
    }

    function restoreDefaults(): void {
        rememberFilters.value = false;
        storeTransactionFilterPreference(
            localStorage(),
            transactionFilterDefaults,
            false,
            storageKey,
        );
        apply(transactionFilterDefaults);
        const url = clearTransactionFilterUrl(page.url);
        syncingUrl = url;
        router.replace({
            url,
            props: (current) => ({
                ...current,
                transactionFiltersTransient: false,
            }),
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                syncingUrl = null;
            },
        });
    }

    onMounted(() => {
        const saved = readTransactionFilterPreference(
            localStorage(),
            storageKey,
        );
        rememberFilters.value = saved !== null;
        const transient = page.props.transactionFiltersTransient === true;
        apply(
            resolveTransactionFilters(
                transient ? clearTransactionFilterUrl(page.url) : page.url,
                saved,
            ),
        );
        initialized = true;

        if (saved) {
            syncUrl();
        } else if (transient) {
            restoreDefaults();
        }

        stopNavigate = router.on('navigate', (event) => {
            if (
                syncingUrl === event.detail.page.url ||
                event.detail.page.component !== 'transactions/Show'
            ) {
                return;
            }

            apply(
                resolveTransactionFilters(
                    event.detail.page.url,
                    snapshot.value,
                ),
            );
        });
    });
    watch(
        snapshot,
        () => {
            if (!initialized || applying) {
                return;
            }

            if (rememberFilters.value) {
                save();
            }

            syncUrl();
        },
        { flush: 'sync' },
    );
    watch(
        rememberFilters,
        () => {
            if (!initialized) {
                return;
            }

            save();
            syncUrl();
        },
        { flush: 'sync' },
    );
    onBeforeUnmount(() => {
        disposed = true;
        stopNavigate?.();
    });

    const filterQuery = () =>
        Object.fromEntries(
            new URL(
                transactionFilterUrl('/transactions', snapshot.value),
                'https://transactions.local',
            ).searchParams,
        );

    return { rememberFilters, customized, restoreDefaults, filterQuery };
}
