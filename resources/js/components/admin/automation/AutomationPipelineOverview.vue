<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bot,
    Clock3,
    Play,
    Siren,
    Sparkles,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as automationShow } from '@/routes/admin/automation/index';
import type { AutomationPipelineStatus } from '@/types';

defineProps<{
    statuses: AutomationPipelineStatus[];
    busyPipelineKey: string | null;
}>();

const emit = defineEmits<{
    run: [pipelineKey: string];
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

function statusLabel(state: string): string {
    const translationKey = `admin.automation.statuses.${state}`;

    return te(translationKey) ? t(translationKey) : state.replaceAll('_', ' ');
}

function triggerLabel(triggerType: string | null): string {
    if (!triggerType) {
        return t('admin.automation.common.notAvailable');
    }

    const translationKey = `admin.automation.triggers.${triggerType}`;

    return te(translationKey) ? t(translationKey) : triggerType;
}

function badgeTone(state: string): string {
    if (state === 'healthy') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100';
    }

    if (state === 'running') {
        return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100';
    }

    if (state === 'warning' || state === 'stale') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100';
    }

    if (state === 'failed' || state === 'timed_out' || state === 'stuck') {
        return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-100';
    }

    if (state === 'disabled') {
        return 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return t('admin.automation.overview.neverRan');
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

function latestError(errorMessage: string | null): string {
    if (!errorMessage) {
        return t('admin.automation.overview.noError');
    }

    return errorMessage;
}
</script>

<template>
    <section class="space-y-4">
        <div
            class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <h2
                    class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                >
                    {{ t('admin.automation.overview.title') }}
                </h2>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                    {{ t('admin.automation.overview.description') }}
                </p>
            </div>
            <Badge
                class="w-fit rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] tracking-[0.16em] text-slate-700 uppercase dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
            >
                <Bot class="mr-1.5 h-3.5 w-3.5" />
                {{ statuses.length }}
            </Badge>
        </div>

        <div v-if="statuses.length > 0" class="grid gap-4 xl:grid-cols-3">
            <Card
                v-for="pipeline in statuses"
                :key="pipeline.key"
                class="rounded-[1.75rem] border-slate-200/80 bg-white/95 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/85"
            >
                <CardHeader class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <Sparkles
                                    class="h-5 w-5 text-slate-700 dark:text-slate-200"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <CardTitle class="text-base tracking-tight">
                                    {{ pipelineLabel(pipeline.key) }}
                                </CardTitle>
                                <CardDescription class="leading-6">
                                    {{ pipeline.key }}
                                </CardDescription>
                            </div>
                        </div>

                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                            :class="badgeTone(pipeline.state)"
                        >
                            {{ statusLabel(pipeline.state) }}
                        </Badge>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                            :class="
                                pipeline.critical
                                    ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100'
                                    : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                            "
                        >
                            <Siren
                                v-if="pipeline.critical"
                                class="mr-1 h-3.5 w-3.5"
                            />
                            {{
                                pipeline.critical
                                    ? t('admin.automation.overview.critical')
                                    : t('admin.automation.overview.alerting')
                            }}
                        </Badge>
                        <Badge
                            class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                            :class="
                                pipeline.enabled
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                                    : 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                            "
                        >
                            {{
                                pipeline.enabled
                                    ? t('admin.automation.overview.enabled')
                                    : t('admin.automation.overview.disabled')
                            }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5 pt-0">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('admin.automation.overview.latestRun') }}
                            </p>
                            <p
                                class="mt-2 text-sm font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    formatDateTime(
                                        pipeline.latest_run?.started_at ??
                                            pipeline.latest_run?.created_at ??
                                            null,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t('admin.automation.overview.latestTrigger')
                                }}
                            </p>
                            <p
                                class="mt-2 text-sm font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    triggerLabel(
                                        pipeline.latest_run?.trigger_type ??
                                            null,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'admin.automation.overview.latestDuration',
                                    )
                                }}
                            </p>
                            <p
                                class="mt-2 text-sm font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{
                                    formatDuration(
                                        pipeline.latest_run?.duration_ms ??
                                            null,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ t('admin.automation.overview.staleAfter') }}
                            </p>
                            <p
                                class="mt-2 flex items-center gap-2 text-sm font-medium text-slate-950 dark:text-slate-50"
                            >
                                <Clock3 class="h-4 w-4 text-slate-400" />
                                <span>
                                    {{
                                        pipeline.max_expected_interval_minutes >
                                        0
                                            ? t(
                                                  'admin.automation.overview.minutes',
                                                  {
                                                      count: pipeline.max_expected_interval_minutes,
                                                  },
                                              )
                                            : t(
                                                  'admin.automation.common.notAvailable',
                                              )
                                    }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-dashed border-slate-300/90 bg-white/70 p-4 dark:border-slate-700 dark:bg-slate-900/50"
                    >
                        <div class="flex items-start gap-3">
                            <AlertTriangle
                                class="mt-0.5 h-4 w-4 text-amber-500"
                            />
                            <div class="space-y-1">
                                <p
                                    class="text-xs font-medium tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'admin.automation.show.labels.errorMessage',
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        latestError(
                                            pipeline.latest_run
                                                ?.error_message ?? null,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <Button
                            class="rounded-xl"
                            :disabled="
                                !pipeline.enabled ||
                                busyPipelineKey === pipeline.key
                            "
                            @click="emit('run', pipeline.key)"
                        >
                            <Play class="mr-2 h-4 w-4" />
                            {{
                                busyPipelineKey === pipeline.key
                                    ? t('admin.automation.list.loading')
                                    : t('admin.automation.actions.runNow')
                            }}
                        </Button>

                        <Button
                            v-if="pipeline.latest_run"
                            variant="ghost"
                            class="h-10 rounded-xl px-3"
                            as-child
                        >
                            <Link
                                :href="
                                    automationShow({
                                        automationRun: pipeline.latest_run.uuid,
                                    })
                                "
                            >
                                {{ t('admin.automation.actions.runInfo') }}
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="rounded-[1.75rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-6 py-10 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300"
        >
            {{ t('admin.automation.overview.empty') }}
        </div>
    </section>
</template>
