<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileSpreadsheet, FolderClock, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, TransactionsPageProps } from '@/types';

const props = defineProps<TransactionsPageProps>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Transazioni',
        href: `/transactions/${props.transactionsPage.year}/${props.transactionsPage.month}`,
    },
]);

const lastRecordedAtLabel = computed(() => {
    if (!props.transactionsPage.last_recorded_at) {
        return 'Nessuna registrazione nel periodo selezionato';
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(props.transactionsPage.last_recorded_at));
});
</script>

<template>
    <Head :title="`Transazioni ${props.transactionsPage.period_label}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <section
                class="rounded-[32px] border border-white/70 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.12),transparent_36%),linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,249,255,0.94))] p-5 shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.2),transparent_36%),linear-gradient(180deg,rgba(19,27,43,0.98),rgba(11,18,32,0.94))] md:p-6"
            >
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-3">
                        <Badge
                            variant="secondary"
                            class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                        >
                            Shell pronta per il modulo transazioni
                        </Badge>
                        <div class="space-y-2">
                            <h1 class="text-3xl font-semibold tracking-tight">
                                {{ props.transactionsPage.period_label }}
                            </h1>
                            <p class="max-w-2xl text-sm text-muted-foreground md:text-base">
                                Questa pagina mese e la navigazione laterale sono reali e navigabili.
                                CRUD, importazioni, deduplica e logica operativa arrivano nel prossimo step.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-3xl border border-slate-200/80 bg-white/85 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                Registrazioni nel mese
                            </p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight">
                                {{ props.transactionsPage.records_count }}
                            </p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/80 bg-white/85 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                Ultima registrazione
                            </p>
                            <p class="mt-2 text-sm font-semibold tracking-tight">
                                {{ lastRecordedAtLabel }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <Card class="rounded-[28px] border-white/70 bg-white/95 dark:border-white/10 dark:bg-slate-950/60">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-xl">
                            <FileSpreadsheet class="size-5 text-sky-600 dark:text-sky-300" />
                            Registro del mese
                        </CardTitle>
                        <CardDescription>
                            Area riservata alla futura lista transazioni, filtri e strumenti operativi del mese selezionato.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-[24px] border border-dashed border-slate-200 bg-slate-50/70 p-6 dark:border-white/10 dark:bg-white/5">
                            <p class="text-sm font-medium">
                                Contenuto in arrivo
                            </p>
                            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                                La struttura della pagina è pronta per ospitare tabella movimenti, raggruppamenti,
                                bulk actions, import e deduplica, ma in questa v1 restiamo volutamente leggeri.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-4">
                    <Card class="rounded-[28px] border-white/70 bg-white/95 dark:border-white/10 dark:bg-slate-950/60">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <FolderClock class="size-5 text-slate-700 dark:text-slate-100" />
                                Pronto per i prossimi step
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm text-muted-foreground">
                            <p>Layout mese + anno già stabilizzato nel routing.</p>
                            <p>Pannello laterale persistente già agganciato al contesto del gestionale.</p>
                            <p>Payload backend minimo pronto per metriche leggere e placeholder reali.</p>
                        </CardContent>
                    </Card>

                    <Card class="rounded-[28px] border-white/70 bg-white/95 dark:border-white/10 dark:bg-slate-950/60">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <Sparkles class="size-5 text-amber-500" />
                                Scope escluso da questa v1
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm text-muted-foreground">
                            <p>Nessun CRUD completo delle transazioni.</p>
                            <p>Nessuna pipeline di importazione o deduplica.</p>
                            <p>Nessuna logica business pesante oltre al contesto mese/anno e ai conteggi base.</p>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
