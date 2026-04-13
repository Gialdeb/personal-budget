<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import KnowledgeSectionForm from '@/components/admin/knowledge/KnowledgeSectionForm.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    destroy as destroyKnowledgeSection,
    index as knowledgeSectionsIndex,
    store as storeKnowledgeSection,
    update as updateKnowledgeSection,
} from '@/routes/admin/knowledge-sections';
import type {
    AdminKnowledgeSectionEditPageProps,
    AdminKnowledgeSectionFormState,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminKnowledgeSectionEditPageProps>();
const page = usePage();

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Knowledge Base', href: knowledgeSectionsIndex() },
    {
        title: props.section?.slug ?? 'Nuova sezione',
        href: knowledgeSectionsIndex(),
    },
];

function buildFormState(): AdminKnowledgeSectionFormState {
    if (!props.section) {
        return {
            slug: '',
            sort_order: 0,
            is_published: false,
            translations: props.supportedLocales.map((locale) => ({
                locale: locale.code,
                title: '',
                description: '',
            })),
        };
    }

    return {
        slug: props.section.slug,
        sort_order: props.section.sort_order,
        is_published: props.section.is_published,
        translations: props.supportedLocales.map((locale) => {
            const translation = props.section?.translations.find(
                (item) => item.locale === locale.code,
            );

            return {
                locale: locale.code,
                title: translation?.title ?? '',
                description: translation?.description ?? '',
            };
        }),
    };
}

const form = useForm(buildFormState());
const currentLocale = ref(props.supportedLocales[0]?.code ?? 'it');

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: 'Operazione non riuscita',
            message: flash.value.error,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: 'Sezione salvata',
            message: flash.value.success,
        };
    }

    return null;
});

function submit(): void {
    if (props.section) {
        form.put(
            updateKnowledgeSection({ knowledgeSection: props.section.uuid }).url,
        );

        return;
    }

    form.post(storeKnowledgeSection().url);
}

function destroySection(): void {
    if (!props.section) {
        return;
    }

    if (
        !window.confirm(
            'Eliminare questa sezione? Gli articoli collegati verranno rimossi.',
        )
    ) {
        return;
    }

    router.delete(
        destroyKnowledgeSection({ knowledgeSection: props.section.uuid }).url,
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="props.section ? 'Modifica sezione knowledge' : 'Nuova sezione knowledge'" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] tracking-[0.2em] text-amber-800 uppercase"
                            >
                                Knowledge Base
                            </Badge>
                            <Heading
                                variant="small"
                                :title="props.section ? 'Modifica sezione' : 'Nuova sezione'"
                                description="Compila slug, traduzioni e stato pubblicazione della sezione del Help Center."
                            />
                        </div>

                        <Button variant="outline" class="h-11 rounded-2xl" as-child>
                            <Link :href="knowledgeSectionsIndex().url">Torna alla lista</Link>
                        </Button>
                    </div>
                </div>

                <Alert v-if="feedback" :variant="feedback.variant">
                    <AlertTitle>{{ feedback.title }}</AlertTitle>
                    <AlertDescription>{{ feedback.message }}</AlertDescription>
                </Alert>

                <KnowledgeSectionForm
                    v-model:form="form"
                    :supported-locales="props.supportedLocales"
                    :current-locale="currentLocale"
                    :submit-label="props.section ? 'Salva sezione' : 'Crea sezione'"
                    :processing="form.processing"
                    @submit="submit"
                    @update:current-locale="currentLocale = $event"
                >
                    <template #footerActions>
                        <Button
                            v-if="props.section"
                            type="button"
                            variant="outline"
                            class="h-11 rounded-2xl border-red-200 text-red-700 hover:bg-red-50 hover:text-red-800"
                            @click="destroySection"
                        >
                            Elimina
                        </Button>
                    </template>
                </KnowledgeSectionForm>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
