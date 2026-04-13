<script setup lang="ts">
import { Search, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import EntrySearchFiltersSheet from '@/components/entry-search/EntrySearchFiltersSheet.vue';
import EntrySearchResults from '@/components/entry-search/EntrySearchResults.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useEntrySearch } from '@/composables/useEntrySearch';
import { cn } from '@/lib/utils';
import { index as entrySearchIndex } from '@/routes/entry-search';
import type { EntrySearchFilterOption, EntrySearchScope } from '@/types';

const props = withDefaults(
    defineProps<{
        defaultScope?: EntrySearchScope;
        activeYear: number;
        activeMonth: number | null;
        accountOptions?: EntrySearchFilterOption[];
        categoryOptions?: EntrySearchFilterOption[];
        showRecurringStatus?: boolean;
        compactTrigger?: boolean;
    }>(),
    {
        defaultScope: 'all',
        accountOptions: () => [],
        categoryOptions: () => [],
        showRecurringStatus: false,
        compactTrigger: false,
    },
);

const { locale, t } = useI18n();

const {
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
} = useEntrySearch({
    defaultScope: props.defaultScope,
    activeYear: props.activeYear,
    activeMonth: props.activeMonth,
    buildSearchUrl: (query) => entrySearchIndex({ query }).url,
});

const scopeOptions = computed(() => [
    { value: 'all' as const, label: t('entrySearch.scopeOptions.all') },
    {
        value: 'transactions' as const,
        label: t('entrySearch.scopeOptions.transactions'),
    },
    {
        value: 'recurring' as const,
        label: t('entrySearch.scopeOptions.recurring'),
    },
]);

const periodLabel = computed(() =>
    state.acrossMonths
        ? t('entrySearch.periodOptions.allMonths')
        : t('entrySearch.periodOptions.currentMonth'),
);

const triggerLabel = computed(() => {
    if (state.q.trim() !== '') {
        return state.q.trim();
    }

    return t('entrySearch.triggerLabel');
});

const shouldShowRecurringStatus = computed(
    () => props.showRecurringStatus && state.scope === 'recurring',
);

function toggleFilters(): void {
    if (isFiltersOpen.value) {
        closeFilters();

        return;
    }

    openFilters();
}

function applyFilters(): void {
    closeFilters();
}
</script>

<template>
    <div class="shrink-0">
        <Button
            variant="outline"
            :class="
                cn(
                    'inline-flex shrink-0 border-slate-200/80 bg-white/90 shadow-sm transition hover:bg-white dark:border-white/10 dark:bg-slate-950/80 dark:hover:bg-slate-900',
                    props.compactTrigger
                        ? 'relative h-10 w-10 rounded-full px-0'
                        : 'h-10 min-w-[11rem] max-w-[11rem] items-center justify-between rounded-full px-3.5 text-left lg:min-w-[12rem] lg:max-w-[12rem]',
                )
            "
            @click="openMobileSearch"
        >
            <span
                :class="
                    cn(
                        'flex min-w-0 items-center gap-3',
                        props.compactTrigger ? 'justify-center' : '',
                    )
                "
            >
                <Search class="size-4 shrink-0 text-slate-500 dark:text-slate-300" />
                <span
                    v-if="!props.compactTrigger"
                    class="truncate text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    {{ triggerLabel }}
                </span>
            </span>

            <span v-if="!props.compactTrigger" class="ml-3 flex shrink-0 items-center gap-2">
                <span
                    v-if="activeFiltersCount > 0"
                    class="inline-flex size-5 items-center justify-center rounded-full bg-slate-950 text-[11px] font-semibold text-white dark:bg-white dark:text-slate-950"
                >
                    {{ activeFiltersCount }}
                </span>
                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    {{ periodLabel }}
                </span>
            </span>

            <span
                v-else-if="activeFiltersCount > 0"
                class="absolute -right-1 -top-1 inline-flex size-5 items-center justify-center rounded-full bg-slate-950 text-[11px] font-semibold text-white dark:bg-white dark:text-slate-950"
            >
                {{ activeFiltersCount }}
            </span>
        </Button>

        <Sheet :open="isMobileOpen" @update:open="isMobileOpen = $event">
            <SheetContent
                side="bottom"
                class="[&>button]:hidden inset-0 h-[100dvh] max-h-[100dvh] w-full max-w-none rounded-none border-0 px-0 pb-0 pt-0"
            >
                <div class="flex h-full flex-col bg-[radial-gradient(circle_at_top,rgba(14,165,233,0.08),transparent_34%),linear-gradient(180deg,rgba(255,255,255,0.98),rgba(248,250,252,0.96))] dark:bg-[radial-gradient(circle_at_top,rgba(14,165,233,0.12),transparent_34%),linear-gradient(180deg,rgba(2,6,23,0.98),rgba(15,23,42,0.98))]">
                    <SheetHeader class="border-b border-slate-200/70 px-4 py-4 text-left md:px-6 md:py-5 dark:border-white/10">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <SheetTitle class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                    {{ t('entrySearch.surfaceTitle') }}
                                </SheetTitle>
                                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                    {{ t('entrySearch.surfaceDescription') }}
                                </p>
                            </div>

                            <Button
                                variant="ghost"
                                size="icon"
                                class="rounded-full"
                                :aria-label="t('entrySearch.actions.close')"
                                @click="closeMobileSearch"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                    </SheetHeader>

                    <div class="border-b border-slate-200/70 px-4 py-4 md:px-6 dark:border-white/10">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                ref="inputRef"
                                v-model="state.q"
                                :placeholder="t('entrySearch.placeholder')"
                                class="h-12 rounded-full border-slate-200/80 bg-white/85 pl-11 pr-4 text-sm shadow-none dark:border-white/10 dark:bg-slate-900/70"
                            />
                        </div>

                        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                            <Button
                                v-for="option in scopeOptions"
                                :key="option.value"
                                variant="ghost"
                                class="h-10 rounded-full border px-4 text-sm"
                                :class="
                                    state.scope === option.value
                                        ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950'
                                        : 'border-slate-200/80 bg-white/80 text-slate-700 dark:border-white/10 dark:bg-slate-900/60 dark:text-slate-200'
                                "
                                @click="state.scope = option.value"
                            >
                                {{ option.label }}
                            </Button>
                        </div>

                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                            <Button
                                variant="ghost"
                                class="h-10 rounded-full border px-4 text-sm"
                                :class="
                                    !state.acrossMonths
                                        ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950'
                                        : 'border-slate-200/80 bg-white/80 text-slate-700 dark:border-white/10 dark:bg-slate-900/60 dark:text-slate-200'
                                "
                                @click="state.acrossMonths = false"
                            >
                                {{ t('entrySearch.periodOptions.currentMonth') }}
                            </Button>
                            <Button
                                variant="ghost"
                                class="h-10 rounded-full border px-4 text-sm"
                                :class="
                                    state.acrossMonths
                                        ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950'
                                        : 'border-slate-200/80 bg-white/80 text-slate-700 dark:border-white/10 dark:bg-slate-900/60 dark:text-slate-200'
                                "
                                @click="state.acrossMonths = true"
                            >
                                {{ t('entrySearch.periodOptions.allMonths') }}
                            </Button>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                class="h-10 rounded-full border-slate-200/80 px-4 dark:border-white/10"
                                @click="toggleFilters"
                            >
                                <SlidersHorizontal class="mr-2 size-4" />
                                {{
                                    isFiltersOpen
                                        ? t('entrySearch.actions.closeFilters')
                                        : t('entrySearch.actions.filters')
                                }}
                                <span
                                    v-if="activeFiltersCount > 0"
                                    class="ml-2 inline-flex size-5 items-center justify-center rounded-full bg-slate-950 text-[11px] font-semibold text-white dark:bg-white dark:text-slate-950"
                                >
                                    {{ activeFiltersCount }}
                                </span>
                            </Button>

                            <Button
                                v-if="hasSearchCriteria"
                                variant="ghost"
                                class="h-10 rounded-full px-4 text-sm text-slate-500 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"
                                @click="resetSearch"
                            >
                                {{ t('entrySearch.actions.reset') }}
                            </Button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 md:px-6 md:py-5">
                        <EntrySearchFiltersSheet
                            :open="isFiltersOpen"
                            :model-value="state"
                            :account-options="accountOptions"
                            :category-options="categoryOptions"
                            :show-recurring-status="shouldShowRecurringStatus"
                            @update:model-value="Object.assign(state, $event)"
                            @reset="resetAdvancedFilters"
                            @apply="applyFilters"
                            @close="closeFilters"
                        />

                        <EntrySearchResults
                            :class="isFiltersOpen ? 'mt-5' : ''"
                            :groups="groups"
                            :is-loading="isLoading"
                            :has-search-criteria="hasSearchCriteria"
                            :total-results="totalResults"
                            :error="error"
                            :locale-override="locale"
                            @open-filters="openFilters"
                            @select="selectResult($event.target_url)"
                        />
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
