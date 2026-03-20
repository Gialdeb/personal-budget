<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    CheckCircle2,
    CircleCheckBig,
    FolderTree,
    Plus,
    Route,
    ShieldAlert,
    Trash2,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import TrackedItemFilters from '@/components/tracked-items/TrackedItemFilters.vue';
import TrackedItemFormSheet from '@/components/tracked-items/TrackedItemFormSheet.vue';
import TrackedItemsTreeList from '@/components/tracked-items/TrackedItemsTreeList.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { destroy, edit, toggleActive } from '@/routes/tracked-items';
import type {
    BreadcrumbItem,
    TrackedItemItem,
    TrackedItemsPageProps,
    TrackedItemTreeItem,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<TrackedItemsPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Elementi da tracciare',
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);

const search = ref('');
const activeStatus = ref('all');
const usageStatus = ref('all');
const structureStatus = ref('all');
const formOpen = ref(false);
const editingTrackedItem = ref<TrackedItemItem | null>(null);
const suggestedParentUuid = ref<string | null>(null);
const deletingTrackedItem = ref<TrackedItemItem | null>(null);
const feedback = ref<FeedbackState | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const flashSuccess = computed(() => flash.value.success ?? undefined);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

watch(
    flashSuccess,
    (message) => {
        if (message) {
            feedback.value = {
                variant: 'default',
                title: 'Operazione completata',
                message,
            };
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.delete ?? errors.toggle;

        if (message) {
            feedback.value = {
                variant: 'destructive',
                title: 'Operazione non disponibile',
                message,
            };
        }
    },
    { immediate: true, deep: true },
);

watch(feedback, (value) => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
        feedbackTimeout = null;
    }

    if (!value) {
        return;
    }

    feedbackTimeout = setTimeout(() => {
        feedback.value = null;
        feedbackTimeout = null;
    }, 4000);
});

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

const visibleFlatTrackedItems = computed(() =>
    props.trackedItems.flat.filter((item) => matchesFilters(item)),
);

const filteredTree = computed(() => filterTree(props.trackedItems.tree));

const summaryCards = computed(() => [
    {
        label: 'Totali',
        value: props.trackedItems.summary.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: 'Attivi',
        value: props.trackedItems.summary.active_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'In uso',
        value: props.trackedItems.summary.used_count,
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        label: 'Foglie',
        value: props.trackedItems.summary.leaf_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
]);

const filteredSummary = computed(() => ({
    visible: visibleFlatTrackedItems.value.length,
    roots: visibleFlatTrackedItems.value.filter((item) => item.parent_uuid === null)
        .length,
    used: visibleFlatTrackedItems.value.filter((item) => item.used).length,
}));

const deleteReasons = computed(() => {
    if (!deletingTrackedItem.value) {
        return [];
    }

    const reasons: string[] = [];

    if (deletingTrackedItem.value.children_count > 0) {
        reasons.push(
            deletingTrackedItem.value.children_count === 1
                ? 'Ha un elemento figlio collegato.'
                : `Ha ${deletingTrackedItem.value.children_count} elementi figli collegati.`,
        );
    }

    if (deletingTrackedItem.value.counts.transactions > 0) {
        reasons.push(
            deletingTrackedItem.value.counts.transactions === 1
                ? 'È usato in 1 transazione.'
                : `È usato in ${deletingTrackedItem.value.counts.transactions} transazioni.`,
        );
    }

    if (deletingTrackedItem.value.counts.budgets > 0) {
        reasons.push(
            deletingTrackedItem.value.counts.budgets === 1
                ? 'È usato in 1 budget.'
                : `È usato in ${deletingTrackedItem.value.counts.budgets} budget.`,
        );
    }

    if (deletingTrackedItem.value.counts.recurring_entries > 0) {
        reasons.push(
            deletingTrackedItem.value.counts.recurring_entries === 1
                ? 'È usato in 1 ricorrenza.'
                : `È usato in ${deletingTrackedItem.value.counts.recurring_entries} ricorrenze.`,
        );
    }

    if (deletingTrackedItem.value.counts.scheduled_entries > 0) {
        reasons.push(
            deletingTrackedItem.value.counts.scheduled_entries === 1
                ? 'È usato in 1 scadenza pianificata.'
                : `È usato in ${deletingTrackedItem.value.counts.scheduled_entries} scadenze pianificate.`,
        );
    }

    return reasons;
});

const emptyMessage = computed(() => {
    if (props.trackedItems.flat.length === 0) {
        return 'Non hai ancora creato elementi da tracciare. Puoi iniziare anche da un solo elemento semplice.';
    }

    return 'Nessun elemento corrisponde ai filtri attivi.';
});

function matchesFilters(item: TrackedItemItem): boolean {
    const query = search.value.trim().toLowerCase();

    if (
        query !== '' &&
        ![item.name, item.slug, item.type ?? '', item.full_path].some((value) =>
            value.toLowerCase().includes(query),
        )
    ) {
        return false;
    }

    if (activeStatus.value === 'active' && !item.is_active) {
        return false;
    }

    if (activeStatus.value === 'inactive' && item.is_active) {
        return false;
    }

    if (usageStatus.value === 'used' && !item.used) {
        return false;
    }

    if (usageStatus.value === 'unused' && item.used) {
        return false;
    }

    if (structureStatus.value === 'roots' && item.parent_uuid !== null) {
        return false;
    }

    return structureStatus.value !== 'leaves' || item.children_count === 0;
}

function filterTree(items: TrackedItemTreeItem[]): TrackedItemTreeItem[] {
    return items.reduce<TrackedItemTreeItem[]>((accumulator, item) => {
        const children = filterTree(item.children);
        const matches = matchesFilters(item);

        if (!matches && children.length === 0) {
            return accumulator;
        }

        accumulator.push({
            ...item,
            children,
        });

        return accumulator;
    }, []);
}

function openCreateTrackedItem(): void {
    editingTrackedItem.value = null;
    suggestedParentUuid.value = null;
    formOpen.value = true;
}

function openEditTrackedItem(item: TrackedItemItem): void {
    editingTrackedItem.value = item;
    suggestedParentUuid.value = item.parent_uuid;
    formOpen.value = true;
}

function openCreateChild(item: TrackedItemItem): void {
    editingTrackedItem.value = null;
    suggestedParentUuid.value = item.uuid;
    formOpen.value = true;
}

function handleSaved(message: string): void {
    feedback.value = {
        variant: 'default',
        title: 'Salvataggio completato',
        message,
    };
}

function toggleTrackedItem(item: TrackedItemItem): void {
    router.patch(
        toggleActive.url(item.uuid),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = {
                    variant: 'default',
                    title: 'Stato aggiornato',
                    message: item.is_active
                        ? 'L’elemento è stato disattivato.'
                        : 'L’elemento è stato attivato.',
                };
            },
            onError: (errors) => {
                feedback.value = {
                    variant: 'destructive',
                    title: 'Aggiornamento non riuscito',
                    message:
                        String(errors.toggle ?? '') ||
                        'Non è stato possibile aggiornare lo stato dell’elemento.',
                };
            },
        },
    );
}

function requestDelete(item: TrackedItemItem): void {
    deletingTrackedItem.value = item;
}

function closeDeleteDialog(): void {
    deletingTrackedItem.value = null;
}

function confirmDelete(): void {
    if (!deletingTrackedItem.value) {
        return;
    }

    router.delete(destroy.url(deletingTrackedItem.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = {
                variant: 'default',
                title: 'Elemento eliminato',
                message: 'L’elemento da tracciare è stato rimosso correttamente.',
            };
            closeDeleteDialog();
        },
        onError: (errors) => {
            feedback.value = {
                variant: 'destructive',
                title: 'Eliminazione non riuscita',
                message:
                    String(errors.delete ?? '') ||
                    'Questo elemento non può essere eliminato.',
            };
            closeDeleteDialog();
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Elementi da tracciare" />

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.18),_transparent_32%),radial-gradient(circle_at_top_right,_rgba(16,185,129,0.16),_transparent_28%),linear-gradient(135deg,rgba(15,23,42,0.03),rgba(255,255,255,0))] px-6 py-6 sm:px-8 sm:py-8 dark:border-slate-800"
                >
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl space-y-4">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-sky-700 uppercase dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300"
                            >
                                <Route class="h-3.5 w-3.5" />
                                Dettaglio personale facoltativo
                            </div>

                            <div class="space-y-2">
                                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50">
                                    Elementi da tracciare
                                </h1>
                                <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300">
                                    Usa questa sezione solo se vuoi dettagliare a cosa si riferiscono alcune spese, entrate o previsioni.
                                    Le categorie restano separate e descrivono il tipo di movimento; qui descrivi l’oggetto personale a cui il movimento si riferisce.
                                </p>
                            </div>
                        </div>

                        <Button class="h-11 rounded-2xl px-5" @click="openCreateTrackedItem">
                            <Plus class="h-4 w-4" />
                            Nuovo elemento
                        </Button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ card.label }}
                            </p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight" :class="card.tone">
                                {{ card.value }}
                            </p>
                        </article>
                    </div>

                    <Alert v-if="feedback" :variant="feedback.variant" class="rounded-[1.5rem] border">
                        <CheckCircle2 v-if="feedback.variant === 'default'" class="h-4 w-4" />
                        <ShieldAlert v-else class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>
                            <p>{{ feedback.message }}</p>
                        </AlertDescription>
                    </Alert>

                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-3 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-3 opacity-0"
                    >
                        <div
                            v-if="feedback"
                            class="pointer-events-none fixed right-4 bottom-4 z-50 max-w-sm sm:right-6 sm:bottom-6"
                        >
                            <div
                                class="pointer-events-auto overflow-hidden rounded-[1.5rem] border shadow-2xl"
                                :class="
                                    feedback.variant === 'default'
                                        ? 'border-emerald-200 bg-emerald-500 text-white'
                                        : 'border-rose-200 bg-rose-600 text-white'
                                "
                            >
                                <div class="flex items-start gap-3 px-4 py-4">
                                    <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15">
                                        <CircleCheckBig v-if="feedback.variant === 'default'" class="h-5 w-5" />
                                        <ShieldAlert v-else class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            {{ feedback.title }}
                                        </p>
                                        <p class="mt-1 text-sm text-white/90">
                                            {{ feedback.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <TrackedItemFilters
                        v-model:search="search"
                        v-model:active-status="activeStatus"
                        v-model:usage-status="usageStatus"
                        v-model:structure-status="structureStatus"
                    />

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <section class="space-y-4">
                            <div
                                class="flex flex-col gap-3 rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                        Struttura gerarchica
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ filteredSummary.visible }} visibili, {{ filteredSummary.roots }} radici, {{ filteredSummary.used }} in uso.
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary" class="rounded-full">
                                        Lista ad albero
                                    </Badge>
                                    <Badge variant="secondary" class="rounded-full">
                                        Percorso completo
                                    </Badge>
                                </div>
                            </div>

                            <TrackedItemsTreeList
                                :items="filteredTree"
                                :empty-message="emptyMessage"
                                @edit="openEditTrackedItem"
                                @create-child="openCreateChild"
                                @toggle-active="toggleTrackedItem"
                                @delete="requestDelete"
                            />
                        </section>

                        <aside class="space-y-4">
                            <section
                                class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    >
                                        <FolderTree class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                            Come usarli bene
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Mantieni la struttura semplice e utile.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    <p>
                                        Puoi creare un solo elemento come <strong>Kia</strong> oppure una struttura come <strong>Veicoli &gt; Auto &gt; Kia</strong>.
                                    </p>
                                    <p>
                                        Il padre è sempre facoltativo: usalo solo se ti aiuta a ritrovare meglio gli elementi.
                                    </p>
                                    <p>
                                        Se un elemento è già in uso, la soluzione normale è disattivarlo per non perdere lo storico.
                                    </p>
                                </div>
                            </section>

                            <section
                                class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/85 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/90 text-slate-700 dark:bg-slate-950/70 dark:text-slate-200">
                                        <Boxes class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                            Separati dalle categorie
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Una dimensione in più, ma opzionale.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                                    <p>
                                        Categoria: descrive la natura del movimento, ad esempio carburante o regali.
                                    </p>
                                    <p>
                                        Elemento da tracciare: descrive l’oggetto personale, ad esempio Auto, Smart o Cane.
                                    </p>
                                    <p>
                                        Payload disponibile anche in formato flat con percorso completo per futuri selettori nei moduli operativi.
                                    </p>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </section>

            <TrackedItemFormSheet
                v-model:open="formOpen"
                :tracked-item="editingTrackedItem"
                :suggested-parent-uuid="suggestedParentUuid"
                :parent-options="trackedItems.flat"
                :type-options="options.types"
                :category-options="options.categories"
                @saved="handleSaved"
            />

            <Dialog
                :open="deletingTrackedItem !== null"
                @update:open="!$event ? closeDeleteDialog() : null"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="flex items-center gap-2">
                            <Trash2 class="h-4 w-4" />
                            Elimina elemento da tracciare
                        </DialogTitle>
                        <DialogDescription class="leading-6">
                            <template v-if="deletingTrackedItem?.is_deletable">
                                Stai per eliminare
                                <strong>{{ deletingTrackedItem?.name }}</strong>.
                                L’operazione è definitiva.
                            </template>
                            <template v-else>
                                <strong>{{ deletingTrackedItem?.name }}</strong>
                                non può essere eliminato in questo momento.
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteReasons.length > 0"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-medium">Motivi del blocco</p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="reason in deleteReasons" :key="reason">
                                {{ reason }}
                            </li>
                        </ul>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="closeDeleteDialog"
                        >
                            Chiudi
                        </Button>
                        <Button
                            v-if="deletingTrackedItem?.is_deletable"
                            type="button"
                            variant="destructive"
                            class="rounded-xl"
                            @click="confirmDelete"
                        >
                            Elimina elemento
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
