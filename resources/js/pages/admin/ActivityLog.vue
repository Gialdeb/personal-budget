<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Activity, RotateCcw, Shield } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { activityLog, index } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

type AdminActivityChange = {
    field: string;
    old: string | null;
    new: string | null;
};

type AdminActivityActor = {
    type: string | null;
    id: number | null;
    label: string;
};

type AdminActivitySubject = {
    type: string | null;
    id: number | null;
    label: string;
    type_label: string;
};

type AdminActivityItem = {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    created_at: string | null;
    created_at_human: string | null;
    subject: AdminActivitySubject;
    causer: AdminActivityActor;
    changes: AdminActivityChange[];
};

type PaginatedAdminActivities = {
    data: AdminActivityItem[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

type AdminActivityLogPageProps = {
    activities: PaginatedAdminActivities;
    filters: {
        subject_type: string | null;
        event: string | null;
        causer_id: number | null;
        date_from: string | null;
        date_to: string | null;
    };
    options: {
        subject_types: {
            value: string;
            label: string;
        }[];
        events: string[];
        causers: {
            id: number;
            label: string;
        }[];
    };
};

const props = defineProps<AdminActivityLogPageProps>();
const { t } = useI18n();

const ALL_OPTION = 'all';

const subjectType = ref(props.filters.subject_type ?? ALL_OPTION);
const event = ref(props.filters.event ?? ALL_OPTION);
const causerId = ref(
    props.filters.causer_id ? String(props.filters.causer_id) : ALL_OPTION,
);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
let filterTimeout: ReturnType<typeof setTimeout> | null = null;

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('admin.sections.overview'),
        href: index(),
    },
    {
        title: t('admin.sections.activityLog'),
        href: activityLog(),
    },
];

const listSummary = computed(() => {
    if (props.activities.meta.total === 0) {
        return t('admin.activityLog.list.emptySummary');
    }

    return t('admin.activityLog.list.summary', {
        from: props.activities.meta.from ?? 0,
        to: props.activities.meta.to ?? 0,
        total: props.activities.meta.total,
    });
});

watch(
    () => props.filters,
    (filters) => {
        subjectType.value = filters.subject_type ?? ALL_OPTION;
        event.value = filters.event ?? ALL_OPTION;
        causerId.value = filters.causer_id
            ? String(filters.causer_id)
            : ALL_OPTION;
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
    },
    { deep: true },
);

watch([subjectType, event, causerId, dateFrom, dateTo], () => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }

    filterTimeout = setTimeout(() => {
        router.get(
            activityLog.url({
                query: {
                    subject_type:
                        subjectType.value === ALL_OPTION
                            ? null
                            : subjectType.value,
                    event: event.value === ALL_OPTION ? null : event.value,
                    causer_id:
                        causerId.value === ALL_OPTION ? null : causerId.value,
                    date_from: dateFrom.value === '' ? null : dateFrom.value,
                    date_to: dateTo.value === '' ? null : dateTo.value,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }, 250);
});

onUnmounted(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }
});

function resetFilters(): void {
    subjectType.value = ALL_OPTION;
    event.value = ALL_OPTION;
    causerId.value = ALL_OPTION;
    dateFrom.value = '';
    dateTo.value = '';
}

function eventBadgeClass(activity: AdminActivityItem): string {
    if (activity.event === 'created') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (activity.event === 'deleted') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300';
    }

    if (activity.event === 'updated') {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300';
}

function formatChange(change: AdminActivityChange): string {
    if (change.old === null) {
        return `${change.field}: ${change.new ?? 'null'}`;
    }

    if (change.new === null) {
        return `${change.field}: ${change.old} -> null`;
    }

    return `${change.field}: ${change.old} -> ${change.new}`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.activityLog.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-lg border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-r from-sky-500/10 via-emerald-500/10 to-amber-500/10 px-6 py-6 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div class="space-y-3">
                                <Badge
                                    class="rounded-lg border border-slate-200 bg-white/90 px-3 py-1 text-xs uppercase dark:border-slate-700 dark:bg-slate-900/80"
                                >
                                    <Shield class="mr-1.5 h-3.5 w-3.5" />
                                    {{ t('admin.badge') }}
                                </Badge>
                                <Heading
                                    variant="small"
                                    :title="t('admin.activityLog.title')"
                                    :description="
                                        t('admin.activityLog.description')
                                    "
                                />
                            </div>

                            <Badge
                                class="rounded-lg border border-slate-200 bg-white/90 px-3 py-1 text-xs dark:border-slate-700 dark:bg-slate-900/80"
                            >
                                {{ listSummary }}
                            </Badge>
                        </div>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <div
                            class="grid gap-4 rounded-lg border border-slate-200/80 bg-slate-50/70 p-4 md:grid-cols-2 xl:grid-cols-6 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <div class="space-y-2">
                                <Label for="activity-subject-type">{{
                                    t('admin.activityLog.filters.subjectType')
                                }}</Label>
                                <select
                                    id="activity-subject-type"
                                    v-model="subjectType"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                >
                                    <option :value="ALL_OPTION">
                                        {{
                                            t(
                                                'admin.activityLog.filters.allSubjects',
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="option in props.options
                                            .subject_types"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="activity-event">{{
                                    t('admin.activityLog.filters.event')
                                }}</Label>
                                <select
                                    id="activity-event"
                                    v-model="event"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                >
                                    <option :value="ALL_OPTION">
                                        {{
                                            t(
                                                'admin.activityLog.filters.allEvents',
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="option in props.options.events"
                                        :key="option"
                                        :value="option"
                                    >
                                        {{ option }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2 xl:col-span-2">
                                <Label for="activity-causer">{{
                                    t('admin.activityLog.filters.causer')
                                }}</Label>
                                <select
                                    id="activity-causer"
                                    v-model="causerId"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                >
                                    <option :value="ALL_OPTION">
                                        {{
                                            t(
                                                'admin.activityLog.filters.allCausers',
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="option in props.options.causers"
                                        :key="option.id"
                                        :value="String(option.id)"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="activity-date-from">{{
                                    t('admin.activityLog.filters.dateFrom')
                                }}</Label>
                                <Input
                                    id="activity-date-from"
                                    v-model="dateFrom"
                                    type="date"
                                    class="rounded-lg"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="activity-date-to">{{
                                    t('admin.activityLog.filters.dateTo')
                                }}</Label>
                                <Input
                                    id="activity-date-to"
                                    v-model="dateTo"
                                    type="date"
                                    class="rounded-lg"
                                />
                            </div>

                            <div class="md:col-span-2 xl:col-span-6">
                                <Button
                                    variant="outline"
                                    class="rounded-lg"
                                    @click="resetFilters"
                                >
                                    <RotateCcw class="mr-2 h-4 w-4" />
                                    {{ t('admin.activityLog.filters.reset') }}
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="props.activities.data.length === 0"
                            class="rounded-lg border border-dashed border-slate-300/90 bg-slate-50/80 p-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
                        >
                            <Activity class="mx-auto h-8 w-8 text-slate-400" />
                            <h3
                                class="mt-3 text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('admin.activityLog.empty.title') }}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('admin.activityLog.empty.description') }}
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <article
                                v-for="activity in props.activities.data"
                                :key="activity.id"
                                class="rounded-lg border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div
                                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                                >
                                    <div class="min-w-0 space-y-2">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <Badge
                                                :class="[
                                                    'rounded-lg border px-2.5 py-1 text-xs',
                                                    eventBadgeClass(activity),
                                                ]"
                                            >
                                                {{
                                                    activity.event ??
                                                    activity.description
                                                }}
                                            </Badge>
                                            <Badge
                                                class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                            >
                                                {{
                                                    activity.subject.type_label
                                                }}
                                            </Badge>
                                            <span
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    activity.created_at_human ??
                                                    activity.created_at
                                                }}
                                            </span>
                                        </div>

                                        <h2
                                            class="text-base font-semibold break-words text-slate-950 dark:text-slate-50"
                                        >
                                            {{ activity.subject.label }}
                                        </h2>
                                        <p
                                            class="text-sm text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                t(
                                                    'admin.activityLog.item.causer',
                                                    {
                                                        causer: activity.causer
                                                            .label,
                                                    },
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        #{{ activity.subject.id }}
                                    </p>
                                </div>

                                <div
                                    v-if="activity.changes.length > 0"
                                    class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3"
                                >
                                    <code
                                        v-for="change in activity.changes"
                                        :key="`${activity.id}-${change.field}`"
                                        class="block overflow-hidden rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-800 dark:bg-slate-900 dark:text-slate-200"
                                    >
                                        {{ formatChange(change) }}
                                    </code>
                                </div>
                            </article>
                        </div>

                        <div
                            v-if="props.activities.meta.last_page > 1"
                            class="flex flex-col gap-3 border-t border-slate-200 pt-4 md:flex-row md:items-center md:justify-between dark:border-slate-800"
                        >
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    t('admin.activityLog.pagination.page', {
                                        current:
                                            props.activities.meta.current_page,
                                        last: props.activities.meta.last_page,
                                    })
                                }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    class="rounded-lg"
                                    :disabled="!props.activities.links.prev"
                                    @click="
                                        props.activities.links.prev &&
                                        router.visit(
                                            props.activities.links.prev,
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    "
                                >
                                    {{
                                        t(
                                            'admin.activityLog.pagination.previous',
                                        )
                                    }}
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-lg"
                                    :disabled="!props.activities.links.next"
                                    @click="
                                        props.activities.links.next &&
                                        router.visit(
                                            props.activities.links.next,
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    "
                                >
                                    {{ t('admin.activityLog.pagination.next') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
