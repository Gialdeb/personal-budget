<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpenText, FileText, Plus } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import { index as knowledgeArticlesIndex } from '@/routes/admin/knowledge-articles';
import {
    create as createKnowledgeSection,
    edit as editKnowledgeSection,
    index as knowledgeSectionsIndex,
} from '@/routes/admin/knowledge-sections';
import type {
    AdminKnowledgeSectionsIndexPageProps,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminKnowledgeSectionsIndexPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Knowledge Base', href: knowledgeSectionsIndex() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Knowledge Base sections" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] tracking-[0.2em] text-amber-800 uppercase"
                            >
                                Knowledge Base
                            </Badge>
                            <Heading
                                variant="small"
                                title="Sezioni guida"
                                description="Gestisci le sezioni principali del Help Center pubblico, il loro ordinamento e lo stato di pubblicazione."
                            />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Button variant="outline" class="h-11 rounded-2xl" as-child>
                                <Link :href="knowledgeArticlesIndex().url">
                                    Articoli
                                </Link>
                            </Button>
                            <Button class="h-11 rounded-2xl px-5" as-child>
                                <Link :href="createKnowledgeSection().url">
                                    <Plus class="mr-2 size-4" />
                                    Nuova sezione
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-4">
                    <Card class="rounded-[1.5rem] border-slate-200/80 xl:col-span-1">
                        <CardHeader>
                            <CardTitle class="text-base">Totale sezioni</CardTitle>
                            <CardDescription>
                                Elenco corrente disponibile nel database.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p class="text-3xl font-semibold tracking-tight text-slate-950">
                                {{ props.sections.meta.total }}
                            </p>
                            <p class="text-sm leading-6 text-slate-600">
                                Le sezioni unpublished non compaiono nel pubblico
                                ma restano modificabili qui.
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="rounded-[1.5rem] border-slate-200/80 xl:col-span-3">
                        <CardHeader>
                            <CardTitle class="text-base">Sezioni</CardTitle>
                            <CardDescription>
                                Titoli, traduzioni disponibili, volume articoli e accesso rapido alla modifica.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-if="props.sections.data.length === 0"
                                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-600"
                            >
                                Nessuna sezione presente. Crea la prima sezione della knowledge base.
                            </div>

                            <div
                                v-for="section in props.sections.data"
                                :key="section.uuid"
                                class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-lg font-semibold tracking-tight text-slate-950">
                                                {{ section.title ?? section.slug }}
                                            </p>
                                            <Badge
                                                :class="
                                                    section.is_published
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-amber-50 text-amber-700'
                                                "
                                            >
                                                {{ section.is_published ? 'Published' : 'Draft' }}
                                            </Badge>
                                            <Badge variant="outline">
                                                Ordine {{ section.sort_order }}
                                            </Badge>
                                        </div>

                                        <p class="text-sm text-slate-600">
                                            {{ section.description ?? 'Nessuna descrizione disponibile.' }}
                                        </p>

                                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                            <span class="inline-flex items-center gap-1">
                                                <BookOpenText class="size-3.5" />
                                                {{ section.slug }}
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <FileText class="size-3.5" />
                                                {{ section.article_count }} articoli, {{ section.published_article_count }} pubblicati
                                            </span>
                                            <Badge
                                                v-for="locale in section.locales"
                                                :key="`${section.uuid}-${locale}`"
                                                variant="outline"
                                            >
                                                {{ locale }}
                                            </Badge>
                                        </div>
                                    </div>

                                    <Button variant="outline" class="rounded-2xl" as-child>
                                        <Link :href="editKnowledgeSection({ knowledgeSection: section.uuid }).url">
                                            Modifica
                                            <ArrowRight class="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="props.sections.meta.last_page > 1"
                                class="flex flex-col gap-3 border-t border-slate-200 pt-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p>
                                    Pagina {{ props.sections.meta.current_page }}
                                    di {{ props.sections.meta.last_page }}
                                </p>
                                <div class="flex gap-3">
                                    <Button
                                        variant="outline"
                                        class="rounded-2xl"
                                        :disabled="!props.sections.links.prev"
                                        as-child
                                    >
                                        <Link
                                            :href="props.sections.links.prev ?? '#'"
                                            preserve-scroll
                                        >
                                            Precedente
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="rounded-2xl"
                                        :disabled="!props.sections.links.next"
                                        as-child
                                    >
                                        <Link
                                            :href="props.sections.links.next ?? '#'"
                                            preserve-scroll
                                        >
                                            Successiva
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
