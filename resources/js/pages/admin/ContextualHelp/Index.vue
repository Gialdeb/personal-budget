<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CircleHelp, Plus } from 'lucide-vue-next';
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
    create as contextualHelpCreate,
    edit as contextualHelpEdit,
    index as contextualHelpIndex,
} from '@/routes/admin/contextual-help';
import type {
    AdminContextualHelpIndexPageProps,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminContextualHelpIndexPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Guide contestuali', href: contextualHelpIndex() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Guide contestuali" />

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
                                Guide contestuali
                            </Badge>
                            <Heading
                                variant="small"
                                title="Guide contestuali"
                                description="Da qui crei e gestisci i contenuti contestuali mostrati con il pulsante ? nelle pagine dell’app, con titolo e body IT/EN."
                            />
                        </div>

                        <Button class="h-11 rounded-2xl" as-child>
                            <Link :href="contextualHelpCreate().url">
                                <Plus class="mr-2 size-4" />
                                Nuova entry
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card
                    class="rounded-[1.5rem] border-border/80 bg-card/92 shadow-none"
                >
                    <CardHeader>
                        <CardTitle class="text-base">Elenco guide</CardTitle>
                        <CardDescription>
                            Apri una entry per modificare titolo e body nelle
                            due lingue, scegliere la pagina collegata e
                            pubblicare la guida contestuale.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            class="rounded-2xl border border-border/80 bg-muted/75 px-4 py-3 text-sm text-muted-foreground"
                        >
                            Le guide contestuali si collegano a una
                            <strong class="text-foreground"
                                >page key stabile</strong
                            >, non a URL liberi. Chiavi disponibili:
                            {{
                                props.pageKeyOptions
                                    .map((option) => option.key)
                                    .join(', ')
                            }}.
                        </div>

                        <div
                            v-if="props.entries.data.length === 0"
                            class="rounded-2xl border border-dashed border-border bg-muted/45 px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            Nessuna guida contestuale configurata.
                        </div>

                        <div
                            v-for="entry in props.entries.data"
                            :key="entry.uuid"
                            class="rounded-[1.5rem] border border-border/80 bg-muted/55 p-4 transition-colors hover:bg-accent/45"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded-2xl border border-border bg-background/85 text-muted-foreground"
                                        >
                                            <CircleHelp class="size-4" />
                                        </div>
                                        <div>
                                            <p
                                                class="text-lg font-semibold tracking-tight text-foreground"
                                            >
                                                {{
                                                    entry.title ??
                                                    entry.page_key
                                                }}
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ entry.page_key }}
                                            </p>
                                        </div>
                                        <span
                                            :class="[
                                                'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                entry.is_published
                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                    : 'border-border bg-background/80 text-muted-foreground',
                                            ]"
                                        >
                                            {{
                                                entry.is_published
                                                    ? 'Pubblicata'
                                                    : 'Bozza'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground"
                                    >
                                        <span>
                                            Sort order:
                                            <strong class="text-foreground">{{
                                                entry.sort_order
                                            }}</strong>
                                        </span>
                                        <span>
                                            Lingue:
                                            <strong class="text-foreground">{{
                                                entry.locales
                                                    .join(', ')
                                                    .toUpperCase()
                                            }}</strong>
                                        </span>
                                        <span v-if="entry.knowledge_article">
                                            Articolo collegato:
                                            <strong class="text-foreground">
                                                {{
                                                    entry.knowledge_article
                                                        .title ??
                                                    entry.knowledge_article.slug
                                                }}
                                            </strong>
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    variant="outline"
                                    class="rounded-2xl"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            contextualHelpEdit({
                                                contextualHelpEntry: entry.uuid,
                                            }).url
                                        "
                                    >
                                        Apri
                                        <ArrowRight class="ml-2 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
