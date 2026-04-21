<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileText, FolderOpen, Plus } from 'lucide-vue-next';
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
import {
    create as createKnowledgeArticle,
    edit as editKnowledgeArticle,
    index as knowledgeArticlesIndex,
} from '@/routes/admin/knowledge-articles';
import { index as knowledgeSectionsIndex } from '@/routes/admin/knowledge-sections';
import type {
    AdminKnowledgeArticlesIndexPageProps,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminKnowledgeArticlesIndexPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Knowledge Base', href: knowledgeArticlesIndex() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Knowledge Base articles" />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-border/80 bg-card/95 p-8 text-card-foreground shadow-[0_30px_90px_-50px_rgba(15,23,42,0.32)] backdrop-blur"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-border/80 bg-accent/70 px-3 py-1 text-[11px] tracking-[0.2em] text-accent-foreground uppercase"
                            >
                                Knowledge Base
                            </Badge>
                            <Heading
                                variant="small"
                                title="Articoli guida"
                                description="Gestisci articoli del Help Center, relazione con la sezione, ordinamento e pubblicazione."
                            />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Button
                                variant="outline"
                                class="h-11 rounded-2xl"
                                as-child
                            >
                                <Link :href="knowledgeSectionsIndex().url">
                                    Sezioni
                                </Link>
                            </Button>
                            <Button class="h-11 rounded-2xl px-5" as-child>
                                <Link :href="createKnowledgeArticle().url">
                                    <Plus class="mr-2 size-4" />
                                    Nuovo articolo
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-4">
                    <Card
                        class="rounded-[1.5rem] border-border/80 bg-card/92 shadow-none xl:col-span-1"
                    >
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Totale articoli</CardTitle
                            >
                            <CardDescription>
                                Elementi gestiti dalla knowledge base.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p
                                class="text-3xl font-semibold tracking-tight text-foreground"
                            >
                                {{ props.articles.meta.total }}
                            </p>
                            <p class="text-sm leading-6 text-muted-foreground">
                                Gli articoli draft restano disponibili in admin
                                ma non escono sul sito pubblico.
                            </p>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[1.5rem] border-border/80 bg-card/92 shadow-none xl:col-span-3"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">Articoli</CardTitle>
                            <CardDescription>
                                Titolo, sezione collegata, lingue presenti e
                                stato pubblicazione.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-if="props.articles.data.length === 0"
                                class="rounded-2xl border border-dashed border-border bg-muted/45 px-4 py-5 text-sm text-muted-foreground"
                            >
                                Nessun articolo presente. Crea il primo articolo
                                del Help Center.
                            </div>

                            <div
                                v-for="article in props.articles.data"
                                :key="article.uuid"
                                class="rounded-[1.5rem] border border-border/80 bg-muted/55 p-4 transition-colors hover:bg-accent/45"
                            >
                                <div
                                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                                >
                                    <div class="space-y-2">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="text-lg font-semibold tracking-tight text-foreground"
                                            >
                                                {{
                                                    article.title ??
                                                    article.slug
                                                }}
                                            </p>
                                            <Badge
                                                :class="
                                                    article.is_published
                                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                        : 'border-border bg-background/80 text-muted-foreground'
                                                "
                                            >
                                                {{
                                                    article.is_published
                                                        ? 'Published'
                                                        : 'Draft'
                                                }}
                                            </Badge>
                                            <Badge variant="outline">
                                                Ordine {{ article.sort_order }}
                                            </Badge>
                                        </div>

                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                article.excerpt ??
                                                'Nessun excerpt disponibile.'
                                            }}
                                        </p>

                                        <div
                                            class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1"
                                            >
                                                <FileText class="size-3.5" />
                                                {{ article.slug }}
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-1"
                                            >
                                                <FolderOpen class="size-3.5" />
                                                {{
                                                    article.section?.title ??
                                                    article.section?.slug ??
                                                    'Sezione mancante'
                                                }}
                                            </span>
                                            <Badge
                                                v-for="locale in article.locales"
                                                :key="`${article.uuid}-${locale}`"
                                                variant="outline"
                                            >
                                                {{ locale }}
                                            </Badge>
                                        </div>
                                    </div>

                                    <Button
                                        variant="outline"
                                        class="rounded-2xl"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                editKnowledgeArticle({
                                                    knowledgeArticle:
                                                        article.uuid,
                                                }).url
                                            "
                                        >
                                            Modifica
                                            <ArrowRight class="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="props.articles.meta.last_page > 1"
                                class="flex flex-col gap-3 border-t border-border pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p>
                                    Pagina
                                    {{ props.articles.meta.current_page }} di
                                    {{ props.articles.meta.last_page }}
                                </p>
                                <div class="flex gap-3">
                                    <Button
                                        variant="outline"
                                        class="rounded-2xl"
                                        :disabled="!props.articles.links.prev"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                props.articles.links.prev ?? '#'
                                            "
                                            preserve-scroll
                                        >
                                            Precedente
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="rounded-2xl"
                                        :disabled="!props.articles.links.next"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                props.articles.links.next ?? '#'
                                            "
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
