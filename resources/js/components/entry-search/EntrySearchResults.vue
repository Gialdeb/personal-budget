<script setup lang="ts">
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import EntrySearchResultMonthGroup from '@/components/entry-search/EntrySearchResultMonthGroup.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { EntrySearchMonthGroup, EntrySearchResultItem } from '@/types';

const props = defineProps<{
    groups: EntrySearchMonthGroup[];
    isLoading: boolean;
    hasSearchCriteria: boolean;
    totalResults: number;
    error: string | null;
    localeOverride?: string | null;
    class?: string;
}>();

const emit = defineEmits<{
    select: [item: EntrySearchResultItem];
    openFilters: [];
}>();
</script>

<template>
    <div class="space-y-4" :class="props.class">
        <div
            v-if="!hasSearchCriteria"
            class="rounded-[24px] border border-dashed border-slate-200/80 bg-white/70 px-4 py-8 text-center dark:border-white/10 dark:bg-slate-950/30"
        >
            <Search class="mx-auto mb-3 size-5 text-slate-400" />
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ $t('entrySearch.states.idleTitle') }}
            </p>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                {{ $t('entrySearch.states.idleDescription') }}
            </p>
        </div>

        <div v-else-if="isLoading" class="space-y-3">
            <Skeleton class="h-5 w-36 rounded-full" />
            <Skeleton class="h-24 w-full rounded-[22px]" />
            <Skeleton class="h-24 w-full rounded-[22px]" />
        </div>

        <Alert
            v-else-if="error"
            class="border-amber-200/80 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-500/10"
        >
            <AlertTitle>{{ $t('entrySearch.states.errorTitle') }}</AlertTitle>
            <AlertDescription>
                {{ $t('entrySearch.states.errorDescription') }}
            </AlertDescription>
        </Alert>

        <div
            v-else-if="groups.length === 0"
            class="rounded-[24px] border border-dashed border-slate-200/80 bg-white/70 px-4 py-8 text-center dark:border-white/10 dark:bg-slate-950/30"
        >
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ $t('entrySearch.states.emptyTitle') }}
            </p>
            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                {{ $t('entrySearch.states.emptyDescription') }}
            </p>
            <Button
                variant="outline"
                class="mt-4 rounded-full"
                @click="emit('openFilters')"
            >
                <SlidersHorizontal class="mr-2 size-4" />
                {{ $t('entrySearch.actions.adjustFilters') }}
            </Button>
        </div>

        <div v-else class="space-y-5">
            <div class="flex items-center justify-between px-1">
                <p class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400">
                    {{ $t('entrySearch.resultsLabel', { count: totalResults }) }}
                </p>
            </div>

            <EntrySearchResultMonthGroup
                v-for="group in groups"
                :key="group.month_key"
                :group="group"
                :locale-override="localeOverride"
                @select="emit('select', $event)"
            />
        </div>
    </div>
</template>
