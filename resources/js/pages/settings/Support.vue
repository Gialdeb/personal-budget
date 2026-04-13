<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, LifeBuoy, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as helpCenterIndex } from '@/routes/help-center';
import { index as supportIndex } from '@/routes/support';
import { store as storeSupportRequest } from '@/routes/support/requests';
import type { BreadcrumbItem, SupportPageProps } from '@/types';

const page = usePage();
const props = defineProps<SupportPageProps>();
const { t } = useI18n();

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('settings.sections.support'),
        href: supportIndex(),
    },
]);

const form = useForm({
    category: props.supportCategories[0]?.value ?? 'general_support',
    subject: '',
    message: '',
    source_url: props.supportContext.source_url ?? '',
    source_route: props.supportContext.source_route ?? '',
});

const selectedCategoryDescription = computed(
    () =>
        props.supportCategories.find(
            (category) => category.value === form.category,
        )?.description ?? '',
);

function submit(): void {
    form.post(storeSupportRequest().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.supportPage.title')" />

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    :title="t('settings.supportPage.title')"
                    :description="t('settings.supportPage.description')"
                />

                <section
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 px-6 py-6 dark:border-slate-800"
                    >
                        <div class="space-y-3">
                            <div
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300"
                            >
                                <LifeBuoy class="size-3.5" />
                                {{ t('settings.supportPage.eyebrow') }}
                            </div>
                            <div>
                                <h2
                                    class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.supportPage.heading') }}
                                </h2>
                                <p
                                    class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                                >
                                    {{ t('settings.supportPage.lead') }}
                                </p>
                                <Link
                                    :href="helpCenterIndex()"
                                    class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#b65642] transition hover:text-[#9e4838]"
                                >
                                    {{ t('settings.supportPage.helpCenterCta') }}
                                    <ArrowRight class="size-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
                    >
                        <form class="space-y-5" @submit.prevent="submit">
                            <Alert v-if="flash.success" variant="default">
                                <AlertTitle>
                                    {{ t('settings.supportPage.successTitle') }}
                                </AlertTitle>
                                <AlertDescription>
                                    {{ flash.success }}
                                </AlertDescription>
                            </Alert>

                            <div class="space-y-2">
                                <label
                                    for="support-category"
                                    class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.supportPage.fields.category') }}
                                </label>
                                <select
                                    id="support-category"
                                    v-model="form.category"
                                    class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-300 focus:ring-2 focus:ring-slate-200/70 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                >
                                    <option
                                        v-for="category in props.supportCategories"
                                        :key="category.value"
                                        :value="category.value"
                                    >
                                        {{ category.label }}
                                    </option>
                                </select>
                                <p
                                    class="text-xs leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ selectedCategoryDescription }}
                                </p>
                                <InputError :message="form.errors.category" />
                            </div>

                            <div class="space-y-2">
                                <label
                                    for="support-subject"
                                    class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.supportPage.fields.subject') }}
                                </label>
                                <input
                                    id="support-subject"
                                    v-model="form.subject"
                                    class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-300 focus:ring-2 focus:ring-slate-200/70 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                    :placeholder="
                                        t(
                                            'settings.supportPage.placeholders.subject',
                                        )
                                    "
                                />
                                <InputError :message="form.errors.subject" />
                            </div>

                            <div class="space-y-2">
                                <label
                                    for="support-message"
                                    class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.supportPage.fields.message') }}
                                </label>
                                <textarea
                                    id="support-message"
                                    v-model="form.message"
                                    rows="7"
                                    :placeholder="
                                        t(
                                            'settings.supportPage.placeholders.message',
                                        )
                                    "
                                    class="w-full rounded-[1.5rem] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700 transition outline-none focus:border-slate-300 focus:ring-2 focus:ring-slate-200/70 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                />
                                <InputError :message="form.errors.message" />
                            </div>

                            <div
                                v-if="
                                    props.supportContext.source_route ||
                                    props.supportContext.source_url
                                "
                                class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300"
                            >
                                <p
                                    class="font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.supportPage.contextCard.title') }}
                                </p>
                                <p class="mt-2">
                                    {{ t('settings.supportPage.contextCard.routeLabel') }}:
                                    {{
                                        props.supportContext.source_route ??
                                        t(
                                            'settings.supportPage.contextCard.unavailable',
                                        )
                                    }}
                                </p>
                                <p class="mt-1 break-all">
                                    {{ t('settings.supportPage.contextCard.urlLabel') }}:
                                    {{
                                        props.supportContext.source_url ??
                                        t(
                                            'settings.supportPage.contextCard.unavailable',
                                        )
                                    }}
                                </p>
                            </div>
                        </form>

                        <aside
                            class="rounded-[1.75rem] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-5 dark:border-slate-800 dark:bg-[linear-gradient(180deg,#0f172a_0%,#111827_100%)]"
                        >
                            <div class="space-y-4">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.supportPage.summaryCard.title') }}
                                </p>
                                <p
                                    class="text-sm leading-7 text-slate-600 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'settings.supportPage.summaryCard.description',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-950"
                                    :disabled="form.processing"
                                    @click="submit"
                                >
                                    <Send class="size-4" />
                                    {{
                                        form.processing
                                            ? t('settings.supportPage.sending')
                                            : t('settings.supportPage.submit')
                                    }}
                                </Button>
                                <p
                                    class="text-xs leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ t('settings.supportPage.summaryCard.helper') }}
                                </p>
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
