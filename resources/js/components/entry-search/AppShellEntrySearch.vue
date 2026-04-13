<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import UniversalEntrySearchBar from '@/components/entry-search/UniversalEntrySearchBar.vue';
import type {
    EntrySearchScope,
    EntrySearchSharedData,
    TransactionsNavigation,
} from '@/types';

const page = usePage();

const props = withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const componentName = computed(() => String(page.component ?? ''));
const currentPath = computed(() => {
    const url = String(page.url ?? '/');

    return url.split('?')[0] || '/';
});

const sharedEntrySearch = computed(
    () => (page.props.entrySearch as EntrySearchSharedData | null) ?? null,
);
const transactionsNavigation = computed(
    () =>
        (page.props.transactionsNavigation as TransactionsNavigation | null) ??
        null,
);

const shouldShowEntrySearch = computed(() => {
    const component = componentName.value;

    return component !== '' && !component.startsWith('admin/');
});

const defaultScope = computed<EntrySearchScope>(() => {
    if (
        componentName.value.startsWith('transactions/recurring/') ||
        currentPath.value.startsWith('/recurring-entries')
    ) {
        return 'recurring';
    }

    if (
        componentName.value.startsWith('transactions/') ||
        currentPath.value.startsWith('/transactions')
    ) {
        return 'transactions';
    }

    return 'all';
});

const now = new Date();

const activeYear = computed(
    () => transactionsNavigation.value?.context.year ?? now.getFullYear(),
);
const activeMonth = computed(
    () => transactionsNavigation.value?.context.month ?? now.getMonth() + 1,
);

const searchBarKey = computed(
    () =>
        `${componentName.value}:${defaultScope.value}:${activeYear.value}:${activeMonth.value ?? 'all'}`,
);
</script>

<template>
    <div
        v-if="shouldShowEntrySearch && sharedEntrySearch"
        class="flex shrink-0 items-center"
    >
        <UniversalEntrySearchBar
            :key="searchBarKey"
            :compact-trigger="props.compact"
            :default-scope="defaultScope"
            :active-year="activeYear"
            :active-month="activeMonth"
            :account-options="sharedEntrySearch.account_options"
            :category-options="sharedEntrySearch.category_options"
            :show-recurring-status="true"
        />
    </div>
</template>
