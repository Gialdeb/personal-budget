<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportsLayout from '@/layouts/reports/Layout.vue';
import { reports } from '@/routes';
import { kpis as reportKpis } from '@/routes/reports';
import type { BreadcrumbItem, ReportLauncherPageProps } from '@/types';

const props = defineProps<ReportLauncherPageProps>();
const { t } = useI18n();
const isMobileViewport = useMediaQuery('(max-width: 767px)');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('nav.reports'),
        href: reports(),
    },
];

function redirectDesktopToOverview(): void {
    if (isMobileViewport.value) {
        return;
    }

    router.visit(reportKpis(), {
        replace: true,
        preserveState: false,
        preserveScroll: true,
    });
}

onMounted(() => {
    redirectDesktopToOverview();
});

watch(isMobileViewport, () => {
    redirectDesktopToOverview();
});
</script>

<template>
    <Head :title="t('nav.reports')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ReportsLayout
            v-if="isMobileViewport"
            :report-sections="props.reportSections"
        >
            <section
                class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]"
            >
                <Card
                    class="rounded-[28px] border-white/70 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                >
                    <CardHeader>
                        <CardTitle
                            class="text-base font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ t('reports.index.placeholderTitle') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.index.placeholderDescription') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3 md:grid-cols-2">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('reports.index.exampleTitle') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('reports.index.exampleBody') }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('reports.index.deliveryTitle') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('reports.index.deliveryBody') }}
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
                            {{ t('reports.roadmap.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('reports.roadmap.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ t('reports.roadmap.nextStepsTitle') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('reports.roadmap.nextStepsBody') }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-dashed border-emerald-200/80 bg-emerald-50/80 px-4 py-4 dark:border-emerald-400/20 dark:bg-emerald-500/10"
                        >
                            <p
                                class="text-sm font-semibold text-emerald-900 dark:text-emerald-100"
                            >
                                {{ t('reports.planning.title') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-emerald-800 dark:text-emerald-200"
                            >
                                {{ t('reports.planning.distinction') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </ReportsLayout>
    </AppLayout>
</template>
