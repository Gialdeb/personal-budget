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
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] tracking-[0.2em] text-rose-700 uppercase"
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
                        class="rounded-[1.5rem] border-slate-200/80 xl:col-span-1"
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
                                class="text-3xl font-semibold tracking-tight text-slate-950"
                            >
                                {{ props.latestRelease ?? 'Nessuna release' }}
                            </p>
                            <div class="space-y-2 text-sm text-slate-600">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>Next patch beta</span>
                                    <span class="font-medium text-slate-950">{{
                                        props.versionSuggestions.patch.beta
                                    }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>Next minor beta</span>
                                    <span class="font-medium text-slate-950">{{
                                        props.versionSuggestions.minor.beta
                                    }}</span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>Next major stable</span>
                                    <span class="font-medium text-slate-950">{{
                                        props.versionSuggestions.major.stable
                                    }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[1.5rem] border-slate-200/80 xl:col-span-3"
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
                                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-600"
                            >
                                Nessuna release presente. Crea la prima release
                                dal pannello admin.
                            </div>

                            <div
                                v-for="release in props.releases.data"
                                :key="release.uuid"
                                class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4"
                            >
                                <div
                                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                                >
                                    <div class="space-y-2">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="text-lg font-semibold tracking-tight text-slate-950"
                                            >
                                                {{ release.version_label }}
                                            </p>
                                            <Badge variant="secondary">{{
                                                release.channel
                                            }}</Badge>
                                            <Badge
                                                :class="
                                                    release.is_published
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-amber-50 text-amber-700'
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
                                                class="bg-rose-50 text-rose-700"
                                            >
                                                Pinned
                                            </Badge>
                                        </div>

                                        <p class="text-sm text-slate-600">
                                            {{
                                                release.title ??
                                                'Senza titolo locale'
                                            }}
                                        </p>

                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs text-slate-500"
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

                <Card class="rounded-[1.5rem] border-slate-200/80">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Sparkles class="size-4 text-rose-500" />
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
