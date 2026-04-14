<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import KnowledgeArticleForm from '@/components/admin/knowledge/KnowledgeArticleForm.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    destroy as destroyKnowledgeArticle,
    index as knowledgeArticlesIndex,
    store as storeKnowledgeArticle,
    update as updateKnowledgeArticle,
} from '@/routes/admin/knowledge-articles';
import type {
    AdminKnowledgeArticleEditPageProps,
    AdminKnowledgeArticleFormState,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminKnowledgeArticleEditPageProps>();
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
    { title: 'Knowledge Base', href: knowledgeArticlesIndex() },
    {
        title: props.article?.slug ?? 'Nuovo articolo',
        href: knowledgeArticlesIndex(),
    },
];

function buildFormState(): AdminKnowledgeArticleFormState {
    if (!props.article) {
        return {
            section_id: props.sections[0]?.id ?? '',
            slug: '',
            sort_order: 0,
            is_published: false,
            published_at: '',
            translations: props.supportedLocales.map((locale) => ({
                locale: locale.code,
                title: '',
                excerpt: '',
                body: '<p></p>',
            })),
        };
    }

    return {
        section_id: props.article.section_id,
        slug: props.article.slug,
        sort_order: props.article.sort_order,
        is_published: props.article.is_published,
        published_at: props.article.published_at ?? '',
        translations: props.supportedLocales.map((locale) => {
            const translation = props.article?.translations.find(
                (item) => item.locale === locale.code,
            );

            return {
                locale: locale.code,
                title: translation?.title ?? '',
                excerpt: translation?.excerpt ?? '',
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
            title: 'Articolo salvato',
            message: flash.value.success,
        };
    }

    return null;
});

function submit(): void {
    if (props.article) {
        form.put(
            updateKnowledgeArticle({ knowledgeArticle: props.article.uuid })
                .url,
        );

        return;
    }

    form.post(storeKnowledgeArticle().url);
}

function destroyArticle(): void {
    if (!props.article) {
        return;
    }

    if (!window.confirm('Eliminare questo articolo della knowledge base?')) {
        return;
    }

    router.delete(
        destroyKnowledgeArticle({ knowledgeArticle: props.article.uuid }).url,
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head
            :title="
                props.article
                    ? 'Modifica articolo knowledge'
                    : 'Nuovo articolo knowledge'
            "
        />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] tracking-[0.2em] text-amber-800 uppercase"
                            >
                                Knowledge Base
                            </Badge>
                            <Heading
                                variant="small"
                                :title="
                                    props.article
                                        ? 'Modifica articolo'
                                        : 'Nuovo articolo'
                                "
                                description="Gestisci sezione, slug, traduzioni IT/EN, body HTML semplice e stato pubblicazione."
                            />
                        </div>

                        <Button
                            variant="outline"
                            class="h-11 rounded-2xl"
                            as-child
                        >
                            <Link :href="knowledgeArticlesIndex().url"
                                >Torna alla lista</Link
                            >
                        </Button>
                    </div>
                </div>

                <Alert v-if="feedback" :variant="feedback.variant">
                    <AlertTitle>{{ feedback.title }}</AlertTitle>
                    <AlertDescription>{{ feedback.message }}</AlertDescription>
                </Alert>

                <KnowledgeArticleForm
                    v-model:form="form"
                    :sections="props.sections"
                    :supported-locales="props.supportedLocales"
                    :current-locale="currentLocale"
                    :submit-label="
                        props.article ? 'Salva articolo' : 'Crea articolo'
                    "
                    :processing="form.processing"
                    @submit="submit"
                    @update:current-locale="currentLocale = $event"
                >
                    <template #footerActions>
                        <Button
                            v-if="props.article"
                            type="button"
                            variant="outline"
                            class="h-11 rounded-2xl border-red-200 text-red-700 hover:bg-red-50 hover:text-red-800"
                            @click="destroyArticle"
                        >
                            Elimina
                        </Button>
                    </template>
                </KnowledgeArticleForm>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
