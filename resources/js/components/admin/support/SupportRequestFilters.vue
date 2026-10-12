<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
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
const { t } = useI18n();

const form = reactive({
    user: props.filters.user ?? '',
    version: props.filters.version ?? '',
    status: props.filters.status ?? 'open',
    category: props.filters.category ?? '',
});

const hasFilters = computed(
    () =>
        form.status !== 'open' ||
        form.category !== '' ||
        form.user !== '' ||
        form.version !== '',
);

watch(
    () => props.filters,
    (filters) =>
        Object.assign(form, {
            user: filters.user ?? '',
            version: filters.version ?? '',
            status: filters.status ?? 'open',
            category: filters.category ?? '',
        }),
);

function selectCategory(category: string): void {
    form.category = category;
    form.status = 'open';
    submit();
}

function submit(): void {
    router.get(
        supportRequestsIndex().url,
        {
            user: form.user || undefined,
            version: form.version || undefined,
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
    form.user = '';
    form.version = '';
    form.status = 'open';
    form.category = '';
    submit();
}

function formatLabel(value: string): string {
    return t(
        `supportTickets.${value === 'waiting_admin' || value === 'waiting_user' || value === 'closed' ? 'admin_' : ''}${value}`,
    );
}
</script>

<template>
    <form
        class="grid gap-3 rounded-[1.5rem] border border-border/80 bg-card/92 p-4 sm:grid-cols-2 xl:grid-cols-3"
        @submit.prevent="submit"
    >
        <div
            class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-3"
            :aria-label="t('supportTickets.openTickets')"
        >
            <Button
                type="button"
                :variant="
                    form.status === 'open' && form.category === ''
                        ? 'default'
                        : 'outline'
                "
                :aria-pressed="form.status === 'open' && form.category === ''"
                @click="selectCategory('')"
                >{{ t('supportTickets.allOpen') }} ·
                {{
                    Object.values(props.options.openCounts).reduce(
                        (sum, count) => sum + count,
                        0,
                    )
                }}</Button
            >
            <Button
                v-for="category in props.options.categories"
                :key="category"
                type="button"
                :variant="
                    form.status === 'open' && form.category === category
                        ? 'default'
                        : 'outline'
                "
                :aria-pressed="
                    form.status === 'open' && form.category === category
                "
                @click="selectCategory(category)"
                >{{ t(`supportTickets.count_${category}`) }} ·
                {{ props.options.openCounts[category] }}</Button
            >
        </div>
        <label class="grid gap-2">
            <span class="text-sm font-medium text-foreground">{{
                t('admin.supportRequestsPage.filters.status')
            }}</span>
            <select
                v-model="form.status"
                class="h-11 rounded-2xl border border-border bg-background px-4 text-sm text-foreground ring-0 transition outline-none focus:border-ring"
            >
                <option value="open">{{ t('supportTickets.allOpen') }}</option>
                <option value="all">
                    {{ t('admin.supportRequestsPage.filters.allStatuses') }}
                </option>
                <option
                    v-for="status in props.options.statuses"
                    :key="status"
                    :value="status"
                >
                    {{ formatLabel(status) }}
                </option>
            </select>
        </label>

        <div class="grid min-w-0 gap-2">
            <span id="support-user-label" class="text-sm font-medium">{{
                t('supportTickets.user')
            }}</span
            ><SearchableSelect
                v-model="form.user"
                aria-labelledby="support-user-label"
                :options="props.options.users"
                :placeholder="t('supportTickets.allUsers')"
                :search-placeholder="t('supportTickets.search')"
                clearable
            />
        </div>
        <div class="grid min-w-0 gap-2">
            <span id="support-version-label" class="text-sm font-medium">{{
                t('supportTickets.filterVersion')
            }}</span
            ><SearchableSelect
                v-model="form.version"
                aria-labelledby="support-version-label"
                :options="props.options.versions"
                :placeholder="t('supportTickets.allVersions')"
                :search-placeholder="t('supportTickets.filterVersion')"
                clearable
            />
        </div>
        <div class="flex flex-wrap items-end gap-3 sm:col-span-2 xl:col-span-3">
            <Button type="submit" class="h-11 rounded-2xl px-5">
                {{ t('admin.supportRequestsPage.filters.apply') }}
            </Button>
            <Button
                v-if="hasFilters"
                type="button"
                variant="outline"
                class="h-11 rounded-2xl"
                @click="reset"
            >
                {{ t('admin.supportRequestsPage.filters.reset') }}
            </Button>
        </div>
    </form>
</template>
