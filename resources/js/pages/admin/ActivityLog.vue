<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Shield } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { activityLog, index } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();

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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.activityLog.title')" />

        <AdminLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-slate-500/10 via-slate-400/10 to-amber-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('admin.activityLog.title')"
                        :description="t('admin.activityLog.description')"
                    />
                </div>

                <div class="px-8 py-8">
                    <div
                        class="flex min-h-72 flex-col items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-6 text-center dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-[1.5rem] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <Activity
                                class="h-7 w-7 text-slate-700 dark:text-slate-200"
                            />
                        </div>
                        <Badge
                            class="mt-5 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] tracking-[0.2em] text-slate-700 uppercase dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                        >
                            <Shield class="mr-1.5 h-3.5 w-3.5" />
                            {{ t('admin.badge') }}
                        </Badge>
                        <h1
                            class="mt-5 text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                        >
                            {{ t('admin.activityLog.empty.title') }}
                        </h1>
                        <p
                            class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('admin.activityLog.empty.description') }}
                        </p>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
