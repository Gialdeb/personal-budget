<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ContextualHelpEntryForm from '@/components/admin/contextual-help/ContextualHelpEntryForm.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    index as contextualHelpIndex,
    store as contextualHelpStore,
    update as contextualHelpUpdate,
} from '@/routes/admin/contextual-help';
import type {
    AdminContextualHelpEditPageProps,
    AdminContextualHelpFormState,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminContextualHelpEditPageProps>();
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
    { title: 'Guide contestuali', href: contextualHelpIndex() },
    {
        title: props.entry?.page_key ?? 'Nuova guida contestuale',
        href: contextualHelpIndex(),
    },
];

function buildFormState(): AdminContextualHelpFormState {
    if (!props.entry) {
        return {
            page_key: '',
            knowledge_article_id: '',
            sort_order: 0,
            is_published: false,
            translations: props.supportedLocales.map((locale) => ({
                locale: locale.code,
                title: '',
                body: '<p></p>',
            })),
        };
    }

    return {
        page_key: props.entry.page_key,
        knowledge_article_id: props.entry.knowledge_article_id ?? '',
        sort_order: props.entry.sort_order,
        is_published: props.entry.is_published,
        translations: props.supportedLocales.map((locale) => {
            const translation = props.entry?.translations.find(
                (item) => item.locale === locale.code,
            );

            return {
                locale: locale.code,
                title: translation?.title ?? '',
                body: translation?.body ?? '<p></p>',
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
            title: 'Guida contestuale salvata',
            message: flash.value.success,
        };
    }

    return null;
});

function submit(): void {
    if (props.entry) {
        form.put(
            contextualHelpUpdate({
                contextualHelpEntry: props.entry.uuid,
            }).url,
        );

        return;
    }

    form.post(contextualHelpStore().url);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head
            :title="
                props.entry
                    ? 'Modifica guida contestuale'
                    : 'Nuova guida contestuale'
            "
        />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-border/80 bg-card/95 p-8 text-card-foreground shadow-[0_30px_90px_-50px_rgba(15,23,42,0.32)] backdrop-blur"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-border/80 bg-accent/70 px-3 py-1 text-[11px] tracking-[0.2em] text-accent-foreground uppercase"
                            >
                                Guide contestuali
                            </Badge>
                            <Heading
                                variant="small"
                                :title="
                                    props.entry
                                        ? 'Modifica entry'
                                        : 'Nuova entry'
                                "
                                description="Qui imposti la pagina collegata e modifichi titolo e body IT/EN della guida contestuale, con link opzionale a un articolo del Help Center."
                            />
                        </div>

                        <Button
                            variant="outline"
                            class="h-11 rounded-2xl"
                            as-child
                        >
                            <Link :href="contextualHelpIndex().url"
                                >Torna alla lista</Link
                            >
                        </Button>
                    </div>
                </div>

                <Alert v-if="feedback" :variant="feedback.variant">
                    <AlertTitle>{{ feedback.title }}</AlertTitle>
                    <AlertDescription>{{ feedback.message }}</AlertDescription>
                </Alert>

                <ContextualHelpEntryForm
                    v-model:form="form"
                    :page-key-options="props.pageKeyOptions"
                    :knowledge-articles="props.knowledgeArticles"
                    :supported-locales="props.supportedLocales"
                    :current-locale="currentLocale"
                    :submit-label="props.entry ? 'Salva entry' : 'Crea entry'"
                    :processing="form.processing"
                    @submit="submit"
                    @update:current-locale="currentLocale = $event"
                />
            </section>
        </AdminLayout>
    </AppLayout>
</template>
