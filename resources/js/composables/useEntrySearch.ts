import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    buildEntrySearchQuery,
    countActiveEntrySearchFilters,
    defaultEntrySearchState,
    hasEntrySearchCriteria,
    parseEntrySearchState,
    replaceEntrySearchQueryInUrl,
    sanitizeEntrySearchStateForScope,
} from '@/lib/entry-search.js';
import type {
    EntrySearchMonthGroup,
    EntrySearchResponse,
    EntrySearchScope,
    EntrySearchState,
} from '@/types';

type UseEntrySearchOptions = {
    defaultScope?: EntrySearchScope;
    activeYear: number;
    activeMonth: number | null;
    buildSearchUrl: (query: Record<string, string>) => string;
};

export function useEntrySearch(options: UseEntrySearchOptions) {
    const defaultScope = options.defaultScope ?? 'all';
    const state = reactive<EntrySearchState>(
        typeof window === 'undefined'
            ? defaultEntrySearchState(defaultScope)
            : parseEntrySearchState(window.location.search, defaultScope),
    );
    const groups = ref<EntrySearchMonthGroup[]>([]);
    const totalResults = ref(0);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const isMobileOpen = ref(false);
    const isFiltersOpen = ref(false);
    const inputRef = ref<{ focus: () => void } | null>(null);
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let abortController: AbortController | null = null;
    let syncingFromHistory = false;

    const hasSearchCriteria = computed(() => hasEntrySearchCriteria(state));
    const activeFiltersCount = computed(() => countActiveEntrySearchFilters(state));

    async function performSearch(): Promise<void> {
        if (!hasSearchCriteria.value) {
            groups.value = [];
            totalResults.value = 0;
            error.value = null;
            isLoading.value = false;

            return;
        }

        abortController?.abort();
        abortController = new AbortController();
        isLoading.value = true;
        error.value = null;

        const query: Record<string, string> = {
            ...buildEntrySearchQuery(state, defaultScope),
            current_year: String(options.activeYear),
        };

        if (options.activeMonth !== null) {
            query.current_month = String(options.activeMonth);
        }

        try {
            const response = await fetch(options.buildSearchUrl(query), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: abortController.signal,
            });

            if (!response.ok) {
                groups.value = [];
                totalResults.value = 0;
                error.value = 'request_failed';

                return;
            }

            const payload = (await response.json()) as EntrySearchResponse;
            groups.value = payload.groups;
            totalResults.value = payload.total_results;
        } catch (requestError) {
            if (requestError instanceof DOMException && requestError.name === 'AbortError') {
                return;
            }

            groups.value = [];
            totalResults.value = 0;
            error.value = 'request_failed';
        } finally {
            isLoading.value = false;
        }
    }

    function scheduleSearch(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = window.setTimeout(() => {
            void performSearch();
        }, 240);
    }

    function syncPageQueryString(): void {
        if (typeof window === 'undefined') {
            return;
        }

        const nextUrl = replaceEntrySearchQueryInUrl(state, defaultScope);

        window.history.replaceState(window.history.state, '', nextUrl);
    }

    function handlePopState(): void {
        syncingFromHistory = true;
        Object.assign(state, parseEntrySearchState(window.location.search, defaultScope));
        void performSearch().finally(() => {
            syncingFromHistory = false;
        });
    }

    function openMobileSearch(): void {
        isMobileOpen.value = true;
    }

    function closeMobileSearch(): void {
        isMobileOpen.value = false;
    }

    function openFilters(): void {
        isFiltersOpen.value = true;
    }

    function closeFilters(): void {
        isFiltersOpen.value = false;
    }

    function resetAdvancedFilters(): void {
        state.accountUuid = null;
        state.categoryUuid = null;
        state.direction = null;
        state.amountMin = '';
        state.amountMax = '';
        state.withNotes = false;
        state.withReference = false;
        state.recurringStatus = null;
    }

    function resetSearch(): void {
        const defaults = defaultEntrySearchState(defaultScope);

        Object.assign(state, defaults);
    }

    function selectResult(targetUrl: string): void {
        closeMobileSearch();
        router.visit(targetUrl, {
            preserveScroll: false,
            preserveState: false,
        });
    }

    watch(
        state,
        () => {
            if (syncingFromHistory) {
                return;
            }

            syncPageQueryString();
            scheduleSearch();
        },
        { deep: true },
    );

    watch(
        () => state.scope,
        () => {
            Object.assign(state, sanitizeEntrySearchStateForScope(state));
        },
    );

    watch(isMobileOpen, (isOpen) => {
        if (!isOpen) {
            return;
        }

        nextTick(() => {
            inputRef.value?.focus();
        });
    });

    onMounted(() => {
        window.addEventListener('popstate', handlePopState);

        if (hasSearchCriteria.value) {
            void performSearch();
        }
    });

    onBeforeUnmount(() => {
        window.removeEventListener('popstate', handlePopState);

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        abortController?.abort();
    });

    return {
        state,
        groups,
        totalResults,
        isLoading,
        error,
        isMobileOpen,
        isFiltersOpen,
        inputRef,
        hasSearchCriteria,
        activeFiltersCount,
        openMobileSearch,
        closeMobileSearch,
        openFilters,
        closeFilters,
        resetAdvancedFilters,
        resetSearch,
        selectResult,
    };
}
