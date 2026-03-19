<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleCheckBig,
    FolderTree,
    Layers3,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import CategoryFilters from '@/components/categories/CategoryFilters.vue';
import CategoryFormSheet from '@/components/categories/CategoryFormSheet.vue';
import CategoryTreeList from '@/components/categories/CategoryTreeList.vue';
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
import { destroy, edit, toggleActive } from '@/routes/categories';
import type {
    BreadcrumbItem,
    CategoryItem,
    CategoryPageProps,
    CategoryTreeItem,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<CategoryPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Categorie di spesa',
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);

const search = ref('');
const activeStatus = ref('all');
const selectableStatus = ref('all');
const directionType = ref('all');
const formOpen = ref(false);
const editingCategory = ref<CategoryItem | null>(null);
const suggestedParentId = ref<number | null>(null);
const deletingCategory = ref<CategoryItem | null>(null);
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

const visibleFlatCategories = computed(() =>
    props.categories.flat.filter((item) => matchesFilters(item)),
);

const filteredTree = computed(() =>
    filterTree(props.categories.tree).sort((left, right) =>
        left.sort_order === right.sort_order
            ? left.name.localeCompare(right.name, 'it')
            : left.sort_order - right.sort_order,
    ),
);

const summaryCards = computed(() => [
    {
        label: 'Totali',
        value: props.categories.summary.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: 'Attive',
        value: props.categories.summary.active_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'Selezionabili',
        value: props.categories.summary.selectable_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: 'Con utilizzi',
        value: props.categories.summary.used_count,
        tone: 'text-amber-700 dark:text-amber-300',
    },
]);

const filteredSummary = computed(() => ({
    visible: visibleFlatCategories.value.length,
    roots: visibleFlatCategories.value.filter((item) => item.parent_id === null)
        .length,
    used: visibleFlatCategories.value.filter((item) => item.usage_count > 0)
        .length,
}));

const deleteReasons = computed(() => {
    if (!deletingCategory.value) {
        return [];
    }

    const reasons: string[] = [];

    if (deletingCategory.value.children_count > 0) {
        reasons.push(
            deletingCategory.value.children_count === 1
                ? 'Ha una categoria figlia collegata.'
                : `Ha ${deletingCategory.value.children_count} categorie figlie collegate.`,
        );
    }

    if (deletingCategory.value.usage_count > 0) {
        reasons.push(
            deletingCategory.value.usage_count === 1
                ? 'È usata in 1 elemento operativo.'
                : `È usata in ${deletingCategory.value.usage_count} elementi operativi.`,
        );
    }

    return reasons;
});

const emptyMessage = computed(() => {
    if (props.categories.flat.length === 0) {
        return 'Non hai ancora creato categorie. Inizia con la prima struttura.';
    }

    return 'Nessuna categoria corrisponde ai filtri attivi.';
});

function matchesFilters(item: CategoryItem): boolean {
    const query = search.value.trim().toLowerCase();

    if (
        query !== '' &&
        ![item.name, item.slug, item.full_path].some((value) =>
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

    if (selectableStatus.value === 'selectable' && !item.is_selectable) {
        return false;
    }

    if (
        selectableStatus.value === 'not-selectable' &&
        item.is_selectable
    ) {
        return false;
    }

    if (
        directionType.value !== 'all' &&
        item.direction_type !== directionType.value
    ) {
        return false;
    }

    return true;
}

function filterTree(items: CategoryTreeItem[]): CategoryTreeItem[] {
    return items.reduce<CategoryTreeItem[]>((accumulator, item) => {
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

function openCreateCategory(): void {
    editingCategory.value = null;
    suggestedParentId.value = null;
    formOpen.value = true;
}

function openEditCategory(item: CategoryItem): void {
    editingCategory.value = item;
    suggestedParentId.value = item.parent_id;
    formOpen.value = true;
}

function openCreateChild(item: CategoryItem): void {
    editingCategory.value = null;
    suggestedParentId.value = item.id;
    formOpen.value = true;
}

function handleSaved(message: string): void {
    feedback.value = {
        variant: 'default',
        title: 'Salvataggio completato',
        message,
    };
}

function toggleCategory(item: CategoryItem): void {
    router.patch(
        toggleActive.url(item.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = {
                    variant: 'default',
                    title: 'Stato aggiornato',
                    message: item.is_active
                        ? 'La categoria è stata disattivata.'
                        : 'La categoria è stata attivata.',
                };
            },
            onError: (errors) => {
                feedback.value = {
                    variant: 'destructive',
                    title: 'Aggiornamento non riuscito',
                    message:
                        String(errors.toggle ?? '') ||
                        'Non è stato possibile aggiornare lo stato della categoria.',
                };
            },
        },
    );
}

function requestDelete(item: CategoryItem): void {
    deletingCategory.value = item;
}

function closeDeleteDialog(): void {
    deletingCategory.value = null;
}

function confirmDelete(): void {
    if (!deletingCategory.value) {
        return;
    }

    router.delete(destroy.url(deletingCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = {
                variant: 'default',
                title: 'Categoria eliminata',
                message: 'La categoria è stata rimossa correttamente.',
            };
            closeDeleteDialog();
        },
        onError: (errors) => {
            feedback.value = {
                variant: 'destructive',
                title: 'Eliminazione non riuscita',
                message:
                    String(errors.delete ?? '') ||
                    'Questa categoria non può essere eliminata.',
            };
            closeDeleteDialog();
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Categorie di spesa" />

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(14,165,233,0.16),_transparent_28%),linear-gradient(135deg,rgba(15,23,42,0.03),rgba(255,255,255,0))] px-6 py-6 sm:px-8 sm:py-8 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl space-y-4">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <Layers3 class="h-3.5 w-3.5" />
                                Struttura categorie
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    Categorie di spesa
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300"
                                >
                                    Gestisci la gerarchia delle categorie con
                                    una struttura chiara per filtri, budget,
                                    ricorrenze e future automazioni.
                                </p>
                            </div>
                        </div>

                        <Button
                            class="h-11 rounded-2xl px-5"
                            @click="openCreateCategory"
                        >
                            <Plus class="h-4 w-4" />
                            Nuova categoria
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
                            <p
                                class="mt-2 text-2xl font-semibold tracking-tight"
                                :class="card.tone"
                            >
                                {{ card.value }}
                            </p>
                        </article>
                    </div>

                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-[1.5rem] border"
                    >
                        <CheckCircle2
                            v-if="feedback.variant === 'default'"
                            class="h-4 w-4"
                        />
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
                                    <div
                                        class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15"
                                    >
                                        <CircleCheckBig
                                            v-if="feedback.variant === 'default'"
                                            class="h-5 w-5"
                                        />
                                        <ShieldAlert
                                            v-else
                                            class="h-5 w-5"
                                        />
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

                    <CategoryFilters
                        v-model:search="search"
                        v-model:active-status="activeStatus"
                        v-model:selectable-status="selectableStatus"
                        v-model:direction-type="directionType"
                        :direction-options="options.direction_types"
                    />

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <section class="space-y-4">
                            <div
                                class="flex flex-col gap-3 rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                        Albero categorie
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ filteredSummary.visible }} visibili,
                                        {{ filteredSummary.roots }} radici,
                                        {{ filteredSummary.used }} con utilizzi.
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary" class="rounded-full">
                                        Vista gerarchica
                                    </Badge>
                                    <Badge variant="secondary" class="rounded-full">
                                        Percorso completo
                                    </Badge>
                                </div>
                            </div>

                            <CategoryTreeList
                                :items="filteredTree"
                                :empty-message="emptyMessage"
                                @edit="openEditCategory"
                                @create-child="openCreateChild"
                                @toggle-active="toggleCategory"
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
                                            Struttura consigliata
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Usa categorie padre per i gruppi e
                                            figlie per il dettaglio operativo.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    <p>
                                        Le categorie non selezionabili sono
                                        utili come contenitori.
                                    </p>
                                    <p>
                                        Il campo slug viene proposto in
                                        automatico ma puoi personalizzarlo.
                                    </p>
                                    <p>
                                        La disattivazione blocca la categoria
                                        senza perdere i collegamenti storici.
                                    </p>
                                </div>
                            </section>

                            <section
                                class="rounded-[1.75rem] border border-slate-200/80 bg-slate-50/85 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                            >
                                <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                    Dati pronti per la UI
                                </p>
                                <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Elenco flat</span>
                                        <span class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ categories.flat.length }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Nodi radice</span>
                                        <span class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ categories.summary.root_count }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span>Con utilizzi</span>
                                        <span class="font-medium text-slate-950 dark:text-slate-50">
                                            {{ categories.summary.used_count }}
                                        </span>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </section>

            <CategoryFormSheet
                v-model:open="formOpen"
                :category="editingCategory"
                :suggested-parent-id="suggestedParentId"
                :parent-options="categories.flat"
                :direction-options="options.direction_types"
                :group-options="options.group_types"
                @saved="handleSaved"
            />

            <Dialog
                :open="deletingCategory !== null"
                @update:open="!$event ? closeDeleteDialog() : null"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="flex items-center gap-2">
                            <Trash2 class="h-4 w-4" />
                            Elimina categoria
                        </DialogTitle>
                        <DialogDescription class="leading-6">
                            <template v-if="deletingCategory?.is_deletable">
                                Stai per eliminare
                                <strong>{{ deletingCategory?.name }}</strong>.
                                L’operazione è definitiva.
                            </template>
                            <template v-else>
                                <strong>{{ deletingCategory?.name }}</strong>
                                non può essere eliminata in questo momento.
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteReasons.length > 0"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-medium">Motivi del blocco</p>
                        <ul class="mt-2 space-y-1">
                            <li
                                v-for="reason in deleteReasons"
                                :key="reason"
                            >
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
                            v-if="deletingCategory?.is_deletable"
                            type="button"
                            variant="destructive"
                            class="rounded-xl"
                            @click="confirmDelete"
                        >
                            Elimina categoria
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
