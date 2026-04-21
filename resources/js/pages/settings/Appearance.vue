<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import ThemePreferenceControl from '@/components/ThemePreferenceControl.vue';
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
                class="overflow-hidden rounded-[2rem] border border-border/80 bg-card/95 text-card-foreground shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur"
            >
                <div
                    class="border-b border-border/70 bg-linear-to-r from-accent/80 via-muted/55 to-secondary/70 px-8 py-7"
                >
                    <Heading
                        variant="small"
                        :title="t('settings.appearancePage.title')"
                        :description="t('settings.appearancePage.description')"
                    />
                </div>
                <div class="px-8 py-8">
                    <ThemePreferenceControl variant="inline" />
                </div>
            </section>
        </SettingsLayout>
    </AppLayout>
</template>
