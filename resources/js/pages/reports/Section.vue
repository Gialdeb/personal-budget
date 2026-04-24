<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, PiggyBank } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportsLayout from '@/layouts/reports/Layout.vue';
import { budgetPlanning, reports } from '@/routes';
import type { BreadcrumbItem, ReportSectionPageProps } from '@/types';

defineOptions({
    name: 'ReportSectionPage',
});

const props = defineProps<ReportSectionPageProps>();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('nav.reports'),
        href: reports(),
    },
    {
        title: props.activeReportSection.title,
        href: props.activeReportSection.href,
    },
];

const siblingSections = computed(() =>
    props.reportSections.filter(
        (section) => section.key !== props.activeReportSection.key,
    ),
);
</script>

<template>
    <Head :title="props.activeReportSection.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportsLayout :report-sections="props.reportSections">
            <div
                class="rounded-[32px] border border-white/70 bg-white/94 p-5 shadow-sm dark:border-white/10 dark:bg-slate-950/72"
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                >
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                {{ props.activeReportSection.title }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ props.activeReportSection.summary }}
                            </p>
                        </div>
                    </div>

                    <Card
                        class="w-full max-w-sm rounded-[24px] border-slate-200/80 bg-slate-50/90 dark:border-white/10 dark:bg-slate-900/80"
                    >
                        <CardContent class="flex items-start gap-3 p-4">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900 text-white dark:bg-white dark:text-slate-950"
                            >
                                <ArrowRight class="size-5" />
                            </div>
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('reports.section.phaseTitle') }}
                                </p>
                                <p
                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('reports.section.phaseBody') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div
                class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]"
            >
                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader>
                        <CardTitle
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ t('reports.section.placeholderTitle') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.section.placeholderDescription') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3 md:grid-cols-2">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('reports.section.nowTitle') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('reports.section.nowBody') }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('reports.section.nextTitle') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('reports.section.nextBody') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader>
                        <CardTitle
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ t('reports.section.otherSectionsTitle') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.section.otherSectionsDescription') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Link
                            v-for="section in siblingSections"
                            :key="section.key"
                            :href="section.href"
                            class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900/80 dark:hover:border-slate-700"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ section.title }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ section.status }}
                                </p>
                            </div>
                            <ArrowRight class="size-4 text-slate-400" />
                        </Link>

                        <div
                            class="rounded-2xl border border-dashed border-emerald-200/80 bg-emerald-50/80 px-4 py-4 dark:border-emerald-400/20 dark:bg-emerald-500/10"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                >
                                    <PiggyBank class="size-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-emerald-900 dark:text-emerald-100"
                                    >
                                        {{ t('reports.planning.title') }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-emerald-800 dark:text-emerald-200"
                                    >
                                        {{ t('reports.planning.distinction') }}
                                    </p>
                                    <Button
                                        as-child
                                        variant="link"
                                        class="mt-2 h-auto px-0 text-emerald-900 dark:text-emerald-100"
                                    >
                                        <Link :href="budgetPlanning()">
                                            {{
                                                t(
                                                    'app.shell.actions.openPlanning',
                                                )
                                            }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </ReportsLayout>
    </AppLayout>
</template>
