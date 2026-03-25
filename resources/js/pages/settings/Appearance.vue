<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance/index';
import type { BreadcrumbItem } from '@/types';

const { t } = useI18n();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.sections.appearance'),
        href: edit(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.sections.appearance')" />

        <h1 class="sr-only">{{ t('settings.sections.appearance') }}</h1>

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-violet-500/10 via-fuchsia-500/10 to-sky-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.appearancePage.title')"
                        :description="t('settings.appearancePage.description')"
                    />
                </div>
                <div class="px-8 py-8">
                    <AppearanceTabs />
                </div>
            </section>
        </SettingsLayout>
    </AppLayout>
</template>
