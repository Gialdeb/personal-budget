<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { index as supportRequestsIndex } from '@/routes/admin/support-requests';
import type {
    AdminSupportRequestFilters,
    AdminSupportRequestOptions,
} from '@/types';

const props = defineProps<{
    filters: AdminSupportRequestFilters;
    options: AdminSupportRequestOptions;
}>();

const form = reactive({
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
});

const hasFilters = computed(() => form.status !== '' || form.category !== '');

function submit(): void {
    router.get(
        supportRequestsIndex().url,
        {
            status: form.status || undefined,
            category: form.category || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function reset(): void {
    form.status = '';
    form.category = '';
    submit();
}

function formatLabel(value: string): string {
    return {
        bug: 'Bug',
        feature_request: 'Feature request',
        general_support: 'General support',
        new: 'New',
        in_progress: 'In progress',
        closed: 'Closed',
    }[value] ?? value;
}
</script>

<template>
    <form
        class="grid gap-3 rounded-[1.5rem] border border-slate-200/80 bg-white/90 p-4 md:grid-cols-[1fr_1fr_auto]"
        @submit.prevent="submit"
    >
        <label class="grid gap-2">
            <span class="text-sm font-medium text-slate-700">Status</span>
            <select
                v-model="form.status"
                class="h-11 rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none ring-0 transition focus:border-slate-300"
            >
                <option value="">All statuses</option>
                <option
                    v-for="status in props.options.statuses"
                    :key="status"
                    :value="status"
                >
                    {{ formatLabel(status) }}
                </option>
            </select>
        </label>

        <label class="grid gap-2">
            <span class="text-sm font-medium text-slate-700">Category</span>
            <select
                v-model="form.category"
                class="h-11 rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none ring-0 transition focus:border-slate-300"
            >
                <option value="">All categories</option>
                <option
                    v-for="category in props.options.categories"
                    :key="category"
                    :value="category"
                >
                    {{ formatLabel(category) }}
                </option>
            </select>
        </label>

        <div class="flex items-end gap-3">
            <Button type="submit" class="h-11 rounded-2xl px-5">
                Apply
            </Button>
            <Button
                v-if="hasFilters"
                type="button"
                variant="outline"
                class="h-11 rounded-2xl"
                @click="reset"
            >
                Reset
            </Button>
        </div>
    </form>
</template>
