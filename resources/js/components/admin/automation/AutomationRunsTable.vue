<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, RefreshCcw } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as automationShow } from '@/routes/admin/automation/index';
import type { AutomationRunItem, PaginationMetaLink } from '@/types';

const props = defineProps<{
    runs: AutomationRunItem[];
    links: PaginationMetaLink[];
    summary: string;
    currentPage: number;
    lastPage: number;
    loading?: boolean;
}>();

const emit = defineEmits<{
    retry: [run: AutomationRunItem];
}>();

const page = usePage();
const { t, te } = useI18n();

function pipelineLabel(key: string): string {
    const translationKey = `admin.automation.pipelines.${key}`;

    return te(translationKey)
        ? t(translationKey)
        : key
              .replaceAll('_', ' ')
              .replace(/\b\w/g, (character) => character.toUpperCase());
}

function statusLabel(status: string | null): string {
    if (!status) {
        return t('admin.automation.common.notAvailable');
    }

    const translationKey = `admin.automation.statuses.${status}`;

    return te(translationKey) ? t(translationKey) : status.replaceAll('_', ' ');
}

function triggerLabel(triggerType: string | null): string {
    if (!triggerType) {
        return t('admin.automation.common.notAvailable');
    }

    const translationKey = `admin.automation.triggers.${triggerType}`;

    return te(translationKey) ? t(translationKey) : triggerType;
}

function statusTone(status: string | null): string {
    if (status === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100';
    }

    if (status === 'running' || status === 'pending') {
        return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100';
    }

    if (status === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100';
    }

    if (status === 'failed' || status === 'timed_out') {
        return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-100';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return t('admin.automation.common.notAvailable');
    }

    const locale = String(
        (page.props.locale as { current?: string } | undefined)?.current ??
            'en',
    );

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatDuration(durationMs: number | null): string {
    if (durationMs === null || durationMs === undefined) {
        return t('admin.automation.common.notAvailable');
    }

    if (durationMs < 1000) {
        return `${durationMs} ms`;
    }

    if (durationMs < 60000) {
        return `${(durationMs / 1000).toFixed(durationMs >= 10000 ? 0 : 1)} s`;
    }

    const minutes = Math.floor(durationMs / 60000);
    const seconds = Math.round((durationMs % 60000) / 1000);

    return `${minutes}m ${seconds}s`;
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/&amp;/g, '&');
}

function isPreviousLink(link: PaginationMetaLink): boolean {
    return link.label.includes('&laquo;') || link.label.includes('Previous');
}

function isNextLink(link: PaginationMetaLink): boolean {
    return link.label.includes('&raquo;') || link.label.includes('Next');
}

function isNumericLink(link: PaginationMetaLink): boolean {
    return /^\d+$/.test(paginationLabel(link.label).trim());
}

const previousLink = computed(
    () => props.links.find((link) => isPreviousLink(link)) ?? null,
);
const nextLink = computed(
    () => props.links.find((link) => isNextLink(link)) ?? null,
);
const pageLinks = computed(() =>
    props.links.filter((link) => isNumericLink(link)),
);
</script>

<template>
    <section
        class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/95 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
    >
        <div
            class="flex flex-col gap-2 border-b border-slate-200/70 px-6 py-5 dark:border-slate-800"
        >
            <h2
                class="text-base font-semibold tracking-tight text-slate-950 dark:text-slate-50"
            >
                {{ summary }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{
                    loading
                        ? t('admin.automation.list.loading')
                        : t('admin.automation.list.description')
                }}
            </p>
        </div>

        <div v-if="runs.length === 0" class="px-6 py-10">
            <div
                class="rounded-[1.5rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60"
            >
                <AlertTriangle class="mx-auto h-8 w-8 text-slate-400" />
                <h3
                    class="mt-4 text-base font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{ t('admin.automation.empty.title') }}
                </h3>
                <p
                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                >
                    {{ t('admin.automation.empty.description') }}
                </p>
            </div>
        </div>

        <div v-else class="space-y-4 px-4 py-4 sm:px-6 sm:py-6">
            <div class="grid gap-3 lg:hidden">
                <article
                    v-for="run in runs"
                    :key="run.uuid"
                    class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ pipelineLabel(run.automation_key) }}
                            </p>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ triggerLabel(run.trigger_type) }}
                            </p>
                        </div>
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                            :class="statusTone(run.status)"
                        >
                            {{ statusLabel(run.status) }}
                        </Badge>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('admin.automation.table.startedAt') }}
                            </p>
                            <p class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ formatDateTime(run.started_at) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('admin.automation.table.duration') }}
                            </p>
                            <p class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ formatDuration(run.duration_ms) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('admin.automation.table.processedCount') }}
                            </p>
                            <p class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ run.processed_count }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('admin.automation.table.errorCount') }}
                            </p>
                            <p class="mt-1 text-slate-950 dark:text-slate-50">
                                {{ run.error_count }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="rounded-xl"
                            as-child
                        >
                            <Link
                                :href="
                                    automationShow({ automationRun: run.uuid })
                                "
                            >
                                {{ t('admin.automation.actions.runInfo') }}
                            </Link>
                        </Button>
                        <Button
                            v-if="run.is_retryable"
                            size="sm"
                            class="rounded-xl"
                            @click="emit('retry', run)"
                        >
                            <RefreshCcw class="mr-2 h-4 w-4" />
                            {{ t('admin.automation.actions.retry') }}
                        </Button>
                    </div>
                </article>
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{
                                t('admin.automation.table.pipeline')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.status')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.triggerType')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.startedAt')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.finishedAt')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.duration')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.processedCount')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.successCount')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.warningCount')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.errorCount')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.host')
                            }}</TableHead>
                            <TableHead>{{
                                t('admin.automation.table.attempt')
                            }}</TableHead>
                            <TableHead class="text-right">{{
                                t('admin.automation.table.actions')
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="run in runs" :key="run.uuid">
                            <TableCell class="min-w-56">
                                <div class="space-y-1">
                                    <p
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{ pipelineLabel(run.automation_key) }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ run.uuid }}
                                    </p>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                    :class="statusTone(run.status)"
                                >
                                    {{ statusLabel(run.status) }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{
                                triggerLabel(run.trigger_type)
                            }}</TableCell>
                            <TableCell>{{
                                formatDateTime(run.started_at)
                            }}</TableCell>
                            <TableCell>{{
                                formatDateTime(run.finished_at)
                            }}</TableCell>
                            <TableCell>{{
                                formatDuration(run.duration_ms)
                            }}</TableCell>
                            <TableCell>{{ run.processed_count }}</TableCell>
                            <TableCell>{{ run.success_count }}</TableCell>
                            <TableCell>{{ run.warning_count }}</TableCell>
                            <TableCell>{{ run.error_count }}</TableCell>
                            <TableCell>{{
                                run.host ||
                                t('admin.automation.common.emptyHost')
                            }}</TableCell>
                            <TableCell>{{
                                run.attempt ??
                                t('admin.automation.common.notAvailable')
                            }}</TableCell>
                            <TableCell class="min-w-52">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="rounded-xl"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                automationShow({
                                                    automationRun: run.uuid,
                                                })
                                            "
                                        >
                                            {{
                                                t(
                                                    'admin.automation.actions.runInfo',
                                                )
                                            }}
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="run.is_retryable"
                                        size="sm"
                                        class="rounded-xl"
                                        @click="emit('retry', run)"
                                    >
                                        {{
                                            t('admin.automation.actions.retry')
                                        }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-slate-200/70 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{
                        t('admin.automation.pagination.page', {
                            current: currentPage,
                            last: lastPage,
                        })
                    }}
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-xl"
                        :disabled="!previousLink?.url"
                        as-child
                    >
                        <Link :href="previousLink?.url ?? '#'">
                            {{ t('admin.automation.pagination.previous') }}
                        </Link>
                    </Button>

                    <Button
                        v-for="link in pageLinks"
                        :key="`${link.label}-${link.url}`"
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        class="rounded-xl px-3"
                        as-child
                    >
                        <Link :href="link.url ?? '#'">
                            {{ paginationLabel(link.label) }}
                        </Link>
                    </Button>

                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-xl"
                        :disabled="!nextLink?.url"
                        as-child
                    >
                        <Link :href="nextLink?.url ?? '#'">
                            {{ t('admin.automation.pagination.next') }}
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
