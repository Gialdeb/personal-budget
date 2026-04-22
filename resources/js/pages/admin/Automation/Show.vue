<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Bot, RefreshCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as automationIndex,
    retry as retryAutomationRun,
} from '@/routes/admin/automation';
import type { AdminAutomationShowPageProps, BreadcrumbItem } from '@/types';

const props = defineProps<AdminAutomationShowPageProps>();
const page = usePage();
const { t, te } = useI18n();

type AutomationAccountResult = {
    account_name: string;
    status: string;
    technical_error: boolean;
    detail: string | null;
    cycle_end_date: string | null;
    payment_due_date: string | null;
    charged_amount: number | null;
    exception_class: string | null;
};

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);
const retryDialogOpen = ref(false);

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

function formatDate(value: string | null): string {
    if (!value) {
        return t('admin.automation.common.notAvailable');
    }

    const locale = String(
        (page.props.locale as { current?: string } | undefined)?.current ??
            'en',
    );

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
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

function formatCurrency(amount: number | null): string {
    if (amount === null || amount === undefined) {
        return t('admin.automation.common.notAvailable');
    }

    const locale = String(
        (page.props.locale as { current?: string } | undefined)?.current ??
            'en',
    );

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'EUR',
    }).format(amount);
}

function outcomeLabel(status: string): string {
    const translationKey = `admin.automation.show.accountOutcomes.${status}`;

    return te(translationKey) ? t(translationKey) : status;
}

function prettyPayload(payload: unknown): string | null {
    if (payload === null || payload === undefined) {
        return null;
    }

    if (Array.isArray(payload) && payload.length === 0) {
        return null;
    }

    if (
        typeof payload === 'object' &&
        Object.keys(payload as Record<string, unknown>).length === 0
    ) {
        return null;
    }

    return JSON.stringify(payload, null, 2);
}

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('admin.sections.overview'),
        href: adminIndex(),
    },
    {
        title: t('admin.sections.automation'),
        href: automationIndex(),
    },
    {
        title: t('admin.automation.breadcrumbRun'),
        href: automationIndex(),
    },
];

const summaryRows = computed(() => [
    {
        label: t('admin.automation.show.labels.pipeline'),
        value: pipelineLabel(props.run.automation_key),
    },
    {
        label: t('admin.automation.show.labels.status'),
        value: statusLabel(props.run.status),
    },
    {
        label: t('admin.automation.show.labels.triggerType'),
        value: triggerLabel(props.run.trigger_type),
    },
    {
        label: t('admin.automation.show.labels.startedAt'),
        value: formatDateTime(props.run.started_at),
    },
    {
        label: t('admin.automation.show.labels.finishedAt'),
        value: formatDateTime(props.run.finished_at),
    },
    {
        label: t('admin.automation.show.labels.duration'),
        value: formatDuration(props.run.duration_ms),
    },
    {
        label: t('admin.automation.show.labels.attempt'),
        value: props.run.attempt ?? t('admin.automation.common.notAvailable'),
    },
    {
        label: t('admin.automation.show.labels.host'),
        value: props.run.host || t('admin.automation.common.emptyHost'),
    },
    {
        label: t('admin.automation.show.labels.jobClass'),
        value: props.run.job_class || t('admin.automation.common.notAvailable'),
    },
    { label: t('admin.automation.show.labels.uuid'), value: props.run.uuid },
    {
        label: t('admin.automation.show.labels.batchId'),
        value: props.run.batch_id || t('admin.automation.common.notAvailable'),
    },
]);

const metrics = computed(() => [
    {
        label: t('admin.automation.show.labels.processedCount'),
        value: props.run.processed_count,
    },
    {
        label: t('admin.automation.show.labels.successCount'),
        value: props.run.success_count,
    },
    {
        label: t('admin.automation.show.labels.warningCount'),
        value: props.run.warning_count,
    },
    {
        label: t('admin.automation.show.labels.errorCount'),
        value: props.run.error_count,
    },
]);

const businessSummary = computed(() => {
    const result = (props.run.result ?? {}) as Record<string, number | unknown>;

    return [
        {
            label: t('admin.automation.show.labels.examinedCount'),
            value: Number(
                result.examined_count ?? props.run.processed_count ?? 0,
            ),
        },
        {
            label: t('admin.automation.show.labels.dueCount'),
            value: Number(result.due_count ?? 0),
        },
        {
            label: t('admin.automation.show.labels.chargedCount'),
            value: Number(result.charged_count ?? 0),
        },
        {
            label: t('admin.automation.show.labels.skippedCount'),
            value: Number(result.skipped_count ?? 0),
        },
        {
            label: t('admin.automation.show.labels.notifiedCount'),
            value: Number(result.notified_count ?? 0),
        },
    ];
});

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: t('admin.automation.flash.errorTitle'),
            message: flash.value.error,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: t('admin.automation.flash.successTitle'),
            message: flash.value.success,
        };
    }

    return null;
});

const accountResults = computed<AutomationAccountResult[]>(() => {
    const result = props.run.result as
        | { account_results?: AutomationAccountResult[] }
        | null
        | undefined;
    const accountResultsPayload = result?.account_results;

    return Array.isArray(accountResultsPayload) ? accountResultsPayload : [];
});

const accountErrorResults = computed(() =>
    accountResults.value.filter((entry) => entry.technical_error),
);

const hasTechnicalErrors = computed(
    () =>
        Boolean(props.run.error_message || props.run.exception_class) ||
        accountErrorResults.value.length > 0,
);

const formattedContext = computed(() => prettyPayload(props.run.context));
const formattedResult = computed(() => prettyPayload(props.run.result));
const backupArtifact = computed(() => props.run.backup_artifact);
const missingBackupArtifact = computed(
    () => backupArtifact.value && !backupArtifact.value.is_available,
);

const retryDialogDescription = computed(() =>
    t('admin.automation.dialogs.retryDescription', {
        run: props.run.uuid,
        pipeline: pipelineLabel(props.run.automation_key),
    }),
);

function submitRetry(): void {
    router.post(
        retryAutomationRun.url({ automationRun: props.run.uuid }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retryDialogOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head
            :title="`${t('admin.automation.show.title')} · ${pipelineLabel(props.run.automation_key)}`"
        />

        <AdminLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-slate-500/10 via-sky-500/10 to-emerald-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] tracking-[0.2em] text-slate-700 uppercase dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                            >
                                <Bot class="mr-1.5 h-3.5 w-3.5" />
                                {{ pipelineLabel(props.run.automation_key) }}
                            </Badge>
                            <Heading
                                variant="small"
                                :title="t('admin.automation.show.title')"
                                :description="
                                    t('admin.automation.show.description')
                                "
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                :class="statusTone(props.run.status)"
                            >
                                {{ statusLabel(props.run.status) }}
                            </Badge>
                            <Button
                                variant="outline"
                                class="rounded-xl"
                                as-child
                            >
                                <Link :href="automationIndex()">
                                    {{
                                        t(
                                            'admin.automation.actions.backToAutomations',
                                        )
                                    }}
                                </Link>
                            </Button>
                            <Button
                                v-if="props.run.is_retryable"
                                class="rounded-xl"
                                @click="retryDialogOpen = true"
                            >
                                <RefreshCcw class="mr-2 h-4 w-4" />
                                {{ t('admin.automation.actions.retry') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6 md:px-8 md:py-8">
                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-[1.5rem]"
                    >
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>{{
                            feedback.message
                        }}</AlertDescription>
                    </Alert>

                    <Alert
                        v-if="missingBackupArtifact"
                        variant="destructive"
                        class="rounded-[1.5rem]"
                    >
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>{{
                            t(
                                'admin.automation.show.backupArtifactUnavailable.title',
                            )
                        }}</AlertTitle>
                        <AlertDescription>
                            {{
                                t(
                                    'admin.automation.show.backupArtifactUnavailable.description',
                                    {
                                        path:
                                            backupArtifact?.path ??
                                            t(
                                                'admin.automation.common.notAvailable',
                                            ),
                                        disk:
                                            backupArtifact?.disk ??
                                            t(
                                                'admin.automation.common.notAvailable',
                                            ),
                                    },
                                )
                            }}
                        </AlertDescription>
                    </Alert>

                    <Card
                        class="rounded-[1.75rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle>{{
                                t('admin.automation.show.sections.summary')
                            }}</CardTitle>
                            <CardDescription>{{
                                pipelineLabel(props.run.automation_key)
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <div
                                v-for="row in summaryRows"
                                :key="row.label"
                                class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                            >
                                <p
                                    class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{ row.label }}
                                </p>
                                <p
                                    class="mt-2 text-sm font-medium break-all text-slate-950 dark:text-slate-50"
                                >
                                    {{ row.value }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <section class="space-y-4">
                        <div>
                            <h2
                                class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t('admin.automation.show.sections.metrics')
                                }}
                            </h2>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <Card
                                v-for="metric in metrics"
                                :key="metric.label"
                                class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                            >
                                <CardHeader class="pb-3">
                                    <CardDescription>{{
                                        metric.label
                                    }}</CardDescription>
                                    <CardTitle class="text-3xl">{{
                                        metric.value
                                    }}</CardTitle>
                                </CardHeader>
                            </Card>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div>
                            <h2
                                class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    t(
                                        'admin.automation.show.sections.businessSummary',
                                    )
                                }}
                            </h2>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <Card
                                v-for="metric in businessSummary"
                                :key="metric.label"
                                class="rounded-[1.5rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                            >
                                <CardHeader class="pb-3">
                                    <CardDescription>{{
                                        metric.label
                                    }}</CardDescription>
                                    <CardTitle class="text-3xl">{{
                                        metric.value
                                    }}</CardTitle>
                                </CardHeader>
                            </Card>
                        </div>
                    </section>

                    <Card
                        class="rounded-[1.75rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle>{{
                                t(
                                    'admin.automation.show.sections.accountResults',
                                )
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="accountResults.length > 0"
                                class="space-y-4"
                            >
                                <div
                                    v-for="entry in accountResults"
                                    :key="`${entry.account_name}-${entry.status}-${entry.payment_due_date ?? 'na'}`"
                                    class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                >
                                    <div
                                        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                                    >
                                        <div class="space-y-1">
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ entry.account_name }}
                                            </p>
                                            <p
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                {{ outcomeLabel(entry.status) }}
                                            </p>
                                        </div>

                                        <Badge
                                            class="rounded-full border px-3 py-1 text-[11px] uppercase"
                                            :class="
                                                entry.technical_error
                                                    ? 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-100'
                                                    : entry.status === 'charged'
                                                      ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                                                      : 'border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200'
                                            "
                                        >
                                            {{ outcomeLabel(entry.status) }}
                                        </Badge>
                                    </div>

                                    <div
                                        class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                    >
                                        <div>
                                            <p
                                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.automation.show.labels.paymentDueDate',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    formatDate(
                                                        entry.payment_due_date,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.automation.show.labels.cycleEndDate',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    formatDate(
                                                        entry.cycle_end_date,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.automation.show.labels.chargedAmount',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    formatCurrency(
                                                        entry.charged_amount,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'admin.automation.show.labels.detail',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm leading-6 text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    entry.detail ||
                                                    t(
                                                        'admin.automation.common.notAvailable',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t('admin.automation.show.noAccountResults')
                                }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[1.75rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                    >
                        <CardHeader>
                            <CardTitle>{{
                                t('admin.automation.show.sections.errorDetails')
                            }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="hasTechnicalErrors"
                                class="grid gap-4 md:grid-cols-2"
                            >
                                <div
                                    class="rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10"
                                >
                                    <p
                                        class="text-xs font-medium tracking-[0.16em] text-rose-700 uppercase dark:text-rose-200"
                                    >
                                        {{
                                            t(
                                                'admin.automation.show.labels.errorMessage',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-rose-900 dark:text-rose-100"
                                    >
                                        {{
                                            props.run.error_message ||
                                            t(
                                                'admin.automation.common.notAvailable',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 dark:border-rose-500/20 dark:bg-rose-500/10"
                                >
                                    <p
                                        class="text-xs font-medium tracking-[0.16em] text-rose-700 uppercase dark:text-rose-200"
                                    >
                                        {{
                                            t(
                                                'admin.automation.show.labels.exceptionClass',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 font-mono text-sm break-all text-rose-900 dark:text-rose-100"
                                    >
                                        {{
                                            props.run.exception_class ||
                                            t(
                                                'admin.automation.common.notAvailable',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="
                                        !props.run.error_message &&
                                        !props.run.exception_class &&
                                        accountErrorResults.length > 0
                                    "
                                    class="rounded-2xl border border-rose-200/80 bg-rose-50/70 p-4 md:col-span-2 dark:border-rose-500/20 dark:bg-rose-500/10"
                                >
                                    <p
                                        class="text-xs font-medium tracking-[0.16em] text-rose-700 uppercase dark:text-rose-200"
                                    >
                                        {{
                                            t(
                                                'admin.automation.show.labels.detail',
                                            )
                                        }}
                                    </p>
                                    <ul
                                        class="mt-2 space-y-2 text-sm leading-6 text-rose-900 dark:text-rose-100"
                                    >
                                        <li
                                            v-for="entry in accountErrorResults"
                                            :key="`${entry.account_name}-${entry.exception_class ?? entry.detail}`"
                                        >
                                            {{
                                                `${entry.account_name}: ${entry.detail || outcomeLabel(entry.status)}`
                                            }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <p
                                v-else
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ t('admin.automation.show.noError') }}
                            </p>
                        </CardContent>
                    </Card>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <Card
                            class="rounded-[1.75rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <CardHeader>
                                <CardTitle>{{
                                    t('admin.automation.show.sections.context')
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre
                                    v-if="formattedContext"
                                    class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-slate-950 px-4 py-4 text-xs leading-6 text-slate-100 dark:border-slate-800"
                                ><code>{{ formattedContext }}</code></pre>
                                <p
                                    v-else
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('admin.automation.show.emptyPayload')
                                    }}
                                </p>
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[1.75rem] border-slate-200/80 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <CardHeader>
                                <CardTitle>{{
                                    t('admin.automation.show.sections.result')
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre
                                    v-if="formattedResult"
                                    class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-slate-950 px-4 py-4 text-xs leading-6 text-slate-100 dark:border-slate-800"
                                ><code>{{ formattedResult }}</code></pre>
                                <p
                                    v-else
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('admin.automation.show.emptyPayload')
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <Dialog v-model:open="retryDialogOpen">
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{
                            t('admin.automation.dialogs.retryTitle')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{ retryDialogDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            class="rounded-xl"
                            @click="retryDialogOpen = false"
                        >
                            {{ t('admin.automation.actions.close') }}
                        </Button>
                        <Button class="rounded-xl" @click="submitRetry">
                            {{ t('admin.automation.actions.confirmRetry') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    </AppLayout>
</template>
