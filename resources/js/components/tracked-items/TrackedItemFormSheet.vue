<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { store, update } from '@/routes/tracked-items';
import type { TrackedItemItem } from '@/types';

const NONE_PARENT = '__none__';

const props = defineProps<{
    open: boolean;
    trackedItem?: TrackedItemItem | null;
    suggestedParentId?: number | null;
    parentOptions: TrackedItemItem[];
    typeOptions: string[];
    categoryOptions: Array<{ value: string; label: string }>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const form = useForm({
    name: '',
    parent_id: NONE_PARENT,
    type: '',
    category_ids: [] as string[],
    is_active: true,
});
const categorySearch = ref('');

const isEditing = computed(
    () => props.trackedItem !== null && props.trackedItem !== undefined,
);

const availableParentOptions = computed(() => {
    if (!props.trackedItem) {
        return props.parentOptions;
    }

    const forbiddenIds = new Set([
        props.trackedItem.id,
        ...props.trackedItem.descendant_ids,
    ]);

    return props.parentOptions.filter((item) => !forbiddenIds.has(item.id));
});

const selectedCategoryIds = computed(() => new Set(form.category_ids));

const selectedCategoryOptions = computed(() =>
    props.categoryOptions.filter((option) => selectedCategoryIds.value.has(option.value)),
);

const filteredCategoryOptions = computed(() => {
    const query = categorySearch.value.trim().toLowerCase();

    return props.categoryOptions.filter((option) => {
        if (selectedCategoryIds.value.has(option.value)) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return option.label.toLowerCase().includes(query);
    });
});

const sheetTitle = computed(() =>
    isEditing.value
        ? 'Modifica elemento da tracciare'
        : 'Nuovo elemento da tracciare',
);

const sheetDescription = computed(() =>
    isEditing.value
        ? 'Aggiorna nome, eventuale padre e stato dell’elemento selezionato.'
        : 'Crea un elemento personale opzionale per dettagliare meglio spese, entrate e previsioni.',
);

watch(
    () => [props.open, props.trackedItem, props.suggestedParentId] as const,
    ([open, trackedItem, suggestedParentId]) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (trackedItem) {
            form.defaults({
                name: trackedItem.name,
                parent_id: trackedItem.parent_id
                    ? String(trackedItem.parent_id)
                    : NONE_PARENT,
                type: trackedItem.type ?? '',
                category_ids: trackedItem.compatible_category_ids.map((id) => String(id)),
                is_active: trackedItem.is_active,
            });
            form.reset();
            categorySearch.value = '';

            return;
        }

        form.defaults({
            name: '',
            parent_id: suggestedParentId ? String(suggestedParentId) : NONE_PARENT,
            type: '',
            category_ids: [],
            is_active: true,
        });
        form.reset();
        categorySearch.value = '';
    },
    { immediate: true },
);

function closeSheet(): void {
    emit('update:open', false);
}

function setActiveState(checked: boolean | 'indeterminate'): void {
    form.is_active = checked === true;
}

function addCategory(value: string): void {
    if (selectedCategoryIds.value.has(value)) {
        return;
    }

    form.category_ids = [...form.category_ids, value];
    form.clearErrors('category_ids');
    categorySearch.value = '';
}

function removeCategory(value: string): void {
    form.category_ids = form.category_ids.filter((id) => id !== value);
}

function submit(): void {
    const payload = {
        ...form.data(),
        parent_id: form.parent_id === NONE_PARENT ? null : Number(form.parent_id),
        type: form.type.trim() || null,
        category_ids: form.category_ids.map((id) => Number(id)),
    };

    if (isEditing.value && props.trackedItem) {
        form.transform(() => payload).patch(update.url(props.trackedItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved', 'Elemento da tracciare aggiornato con successo.');
                closeSheet();
            },
        });

        return;
    }

    form.transform(() => payload).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', 'Elemento da tracciare creato con successo.');
            closeSheet();
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-xl">
            <div class="flex h-full flex-col">
                <SheetHeader class="border-b border-slate-200/80 px-6 py-6 dark:border-slate-800">
                    <SheetTitle>{{ sheetTitle }}</SheetTitle>
                    <SheetDescription>
                        {{ sheetDescription }}
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="name">Nome</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                placeholder="Es. Kia, Casa 1, Cane"
                            />
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Dai un nome chiaro all’oggetto personale che vuoi tracciare.
                            </p>
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Elemento padre opzionale</Label>
                            <Select
                                :model-value="String(form.parent_id)"
                                @update:model-value="form.parent_id = String($event)"
                            >
                                <SelectTrigger class="h-11 rounded-2xl border-slate-200 dark:border-slate-800">
                                    <SelectValue placeholder="Nessun elemento padre" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE_PARENT">
                                        Nessun elemento padre
                                    </SelectItem>
                                    <SelectItem
                                        v-for="item in availableParentOptions"
                                        :key="item.id"
                                        :value="String(item.id)"
                                    >
                                        {{ item.full_path }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Facoltativo. Serve solo se vuoi organizzare gli elementi in una piccola gerarchia.
                            </p>
                            <InputError :message="form.errors.parent_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="type">Tipo opzionale</Label>
                            <Input
                                id="type"
                                v-model="form.type"
                                list="tracked-item-type-options"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                placeholder="Es. auto, moto, casa"
                            />
                            <datalist id="tracked-item-type-options">
                                <option
                                    v-for="option in typeOptions"
                                    :key="option"
                                    :value="option"
                                />
                            </datalist>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Facoltativo. Può aiutarti a distinguere rapidamente gruppi simili.
                            </p>
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="grid gap-3 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <div class="space-y-1">
                                <Label for="category-search">Rami categoria compatibili</Label>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Associa questo elemento a uno o più rami o foglie categoria. Sarà poi suggerito anche sulle categorie figlie del ramo scelto.
                                </p>
                            </div>

                            <Input
                                id="category-search"
                                v-model="categorySearch"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                placeholder="Cerca ramo o categoria"
                            />

                            <div
                                v-if="selectedCategoryOptions.length > 0"
                                class="flex flex-wrap gap-2"
                            >
                                <button
                                    v-for="option in selectedCategoryOptions"
                                    :key="option.value"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-800 transition hover:bg-sky-200 dark:bg-sky-500/15 dark:text-sky-200 dark:hover:bg-sky-500/25"
                                    @click="removeCategory(option.value)"
                                >
                                    {{ option.label }}
                                    <span class="text-[11px] uppercase tracking-[0.16em]">Rimuovi</span>
                                </button>
                            </div>

                            <div class="max-h-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white/90 p-2 dark:border-slate-800 dark:bg-slate-950/70">
                                <button
                                    v-for="option in filteredCategoryOptions"
                                    :key="option.value"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900"
                                    @click="addCategory(option.value)"
                                >
                                    <span class="truncate">{{ option.label }}</span>
                                    <span class="text-xs uppercase tracking-[0.16em] text-slate-400">
                                        Aggiungi
                                    </span>
                                </button>
                                <p
                                    v-if="filteredCategoryOptions.length === 0"
                                    class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Nessuna categoria compatibile da aggiungere.
                                </p>
                            </div>

                            <InputError :message="form.errors.category_ids" />
                        </div>

                        <div class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <Label class="text-sm font-medium">Stato</Label>

                            <label class="flex items-start gap-3 rounded-2xl bg-white/90 p-3 dark:bg-slate-950/70">
                                <Checkbox
                                    :checked="form.is_active"
                                    @update:checked="setActiveState"
                                    class="mt-0.5"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">
                                        Attivo
                                    </span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Se disattivato resta nello storico ma non sarà proposto come scelta normale.
                                    </span>
                                </span>
                            </label>

                            <div class="rounded-2xl border border-slate-200 bg-white/90 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300">
                                Gli elementi da tracciare sono sempre facoltativi e non sostituiscono le categorie.
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-800">
                            <Button
                                type="button"
                                variant="secondary"
                                class="rounded-2xl"
                                @click="closeSheet"
                            >
                                Annulla
                            </Button>
                            <Button
                                type="submit"
                                class="rounded-2xl"
                                :disabled="form.processing"
                            >
                                {{
                                    isEditing
                                        ? 'Salva modifiche'
                                        : 'Crea elemento'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
