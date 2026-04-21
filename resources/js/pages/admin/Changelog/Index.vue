<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CircleDot, Plus, Sparkles } from 'lucide-vue-next';
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
    create as createChangelogRelease,
    edit as editChangelogRelease,
    index as changelogIndex,
} from '@/routes/admin/changelog/index';
import type { AdminChangelogIndexPageProps, BreadcrumbItem } from '@/types';

const props = defineProps<AdminChangelogIndexPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Changelog', href: changelogIndex() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Admin changelog" />

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
                                Changelog admin
                            </Badge>
                            <Heading
                                variant="small"
                                title="Gestione release"
                                description="Crea release multilingua, mantienile in bozza o pubblicale, e prepara il payload pubblico senza toccare il frontend."
                            />
                        </div>

                        <Button class="h-11 rounded-2xl px-5" as-child>
                            <Link :href="createChangelogRelease().url">
                                <Plus class="mr-2 size-4" />
                                Nuova release
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-4">
                    <Card
                        class="rounded-[1.5rem] border-border/80 bg-card/92 shadow-none xl:col-span-1"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">
                                Ultima release
                            </CardTitle>
                            <CardDescription>
                                Base corrente per i suggerimenti di versione.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p
                                class="text-3xl font-semibold tracking-tight text-foreground"
                            >
                                {{ props.latestRelease ?? 'Nessuna release' }}
                            </p>
                            <div
                                class="space-y-2 text-sm text-muted-foreground"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>Next patch beta</span>
                                    <span class="font-medium text-foreground">{{
                                        props.versionSuggestions.patch.beta
                                    }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>Next minor beta</span>
                                    <span class="font-medium text-foreground">{{
                                        props.versionSuggestions.minor.beta
                                    }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>Next major stable</span>
                                    <span class="font-medium text-foreground">{{
                                        props.versionSuggestions.major.stable
                                    }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[1.5rem] border-border/80 bg-card/92 shadow-none xl:col-span-3"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">Release</CardTitle>
                            <CardDescription>
                                Stato, lingue disponibili e accesso rapido alla
                                modifica.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-if="props.releases.data.length === 0"
                                class="rounded-2xl border border-dashed border-border bg-muted/45 px-4 py-5 text-sm text-muted-foreground"
                            >
                                Nessuna release presente. Crea la prima release
                                dal pannello admin.
                            </div>

                            <div
                                v-for="release in props.releases.data"
                                :key="release.uuid"
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
                                                {{ release.version_label }}
                                            </p>
                                            <Badge variant="secondary">{{
                                                release.channel
                                            }}</Badge>
                                            <Badge
                                                :class="
                                                    release.is_published
                                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                        : 'border-border bg-background/80 text-muted-foreground'
                                                "
                                            >
                                                {{
                                                    release.is_published
                                                        ? 'Published'
                                                        : 'Draft'
                                                }}
                                            </Badge>
                                            <Badge
                                                v-if="release.is_pinned"
                                                class="border border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/15 dark:text-rose-300"
                                            >
                                                Pinned
                                            </Badge>
                                        </div>

                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{
                                                release.title ??
                                                'Senza titolo locale'
                                            }}
                                        </p>

                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1"
                                            >
                                                <CircleDot class="size-3.5" />
                                                Lingue:
                                            </span>
                                            <Badge
                                                v-for="locale in release.locales"
                                                :key="locale"
                                                variant="outline"
                                            >
                                                {{ locale }}
                                            </Badge>
                                            <span v-if="release.published_at">
                                                Pubblicata:
                                                {{ release.published_at }}
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
                                                editChangelogRelease({
                                                    changelogRelease:
                                                        release.uuid,
                                                }).url
                                            "
                                        >
                                            Modifica
                                            <ArrowRight class="ml-2 size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card
                    class="rounded-[1.5rem] border-border/80 bg-card/92 shadow-none"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Sparkles class="size-4 text-muted-foreground" />
                            Lingue supportate
                        </CardTitle>
                        <CardDescription>
                            Il changelog legge le lingue direttamente dalla
                            configurazione locale del progetto.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Badge
                            v-for="locale in props.supportedLocales"
                            :key="locale.code"
                            variant="outline"
                        >
                            {{ locale.label }}
                        </Badge>
                    </CardContent>
                </Card>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
