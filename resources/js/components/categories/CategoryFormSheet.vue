<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    categoryColorOptions,
    categoryIconOptions,
    resolveCategoryIcon,
} from '@/lib/category-appearance';
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
import { store, update } from '@/routes/categories';
import type { CategoryItem, CategoryOption } from '@/types';

const NONE_PARENT = '__none__';

const props = defineProps<{
    open: boolean;
    category?: CategoryItem | null;
    suggestedParentId?: number | null;
    parentOptions: CategoryItem[];
    directionOptions: CategoryOption[];
    groupOptions: CategoryOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const form = useForm({
    name: '',
    slug: '',
    parent_id: NONE_PARENT,
    direction_type: '',
    group_type: '',
    icon: 'wallet',
    color: '#0f766e',
    is_selectable: true,
    is_active: true,
    sort_order: 0,
});

let slugDirty = false;

const isEditing = computed(() => props.category !== null && props.category !== undefined);

const availableParentOptions = computed(() => {
    if (!props.category) {
        return props.parentOptions;
    }

    const forbiddenIds = new Set([
        props.category.id,
        ...props.category.descendant_ids,
    ]);

    return props.parentOptions.filter((item) => !forbiddenIds.has(item.id));
});

const sheetTitle = computed(() =>
    isEditing.value ? 'Modifica categoria' : 'Nuova categoria',
);

const sheetDescription = computed(() =>
    isEditing.value
        ? 'Aggiorna nome, gerarchia e comportamento della categoria selezionata.'
        : 'Crea una nuova categoria o una sotto-categoria pronta per filtri, budget e automazioni future.',
);

watch(
    () => [props.open, props.category, props.suggestedParentId] as const,
    ([open, category, suggestedParentId]) => {
        if (!open) {
            return;
        }

        slugDirty = false;
        form.clearErrors();

        if (category) {
            form.defaults({
                name: category.name,
                slug: category.slug,
                parent_id: category.parent_id ? String(category.parent_id) : NONE_PARENT,
                direction_type: category.direction_type,
                group_type: category.group_type,
                icon: category.icon ?? 'wallet',
                color: category.color ?? '#0f766e',
                is_selectable: category.is_selectable,
                is_active: category.is_active,
                sort_order: category.sort_order,
            });
            form.reset();
            slugDirty = category.slug !== slugify(category.name);

            return;
        }

        form.defaults({
            name: '',
            slug: '',
            parent_id: suggestedParentId ? String(suggestedParentId) : NONE_PARENT,
            direction_type: 'expense',
            group_type: 'expense',
            icon: 'wallet',
            color: '#0f766e',
            is_selectable: true,
            is_active: true,
            sort_order: 0,
        });
        form.reset();
    },
    { immediate: true },
);

watch(
    () => form.name,
    (value) => {
        if (!slugDirty) {
            form.slug = slugify(value);
        }
    },
);

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function closeSheet(): void {
    emit('update:open', false);
}

function setSelectableState(checked: boolean | 'indeterminate'): void {
    form.is_selectable = checked === true;
}

function setActiveState(checked: boolean | 'indeterminate'): void {
    form.is_active = checked === true;
}

function submit(): void {
    const payload = {
        ...form.data(),
        parent_id: form.parent_id === NONE_PARENT ? null : Number(form.parent_id),
        sort_order: Number(form.sort_order || 0),
    };

    if (isEditing.value && props.category) {
        form.transform(() => payload).patch(update.url(props.category.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved', 'Categoria aggiornata con successo.');
                closeSheet();
            },
        });

        return;
    }

    form.transform(() => payload).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', 'Categoria creata con successo.');
            closeSheet();
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-2xl">
            <div class="flex h-full flex-col">
                <SheetHeader
                    class="border-b border-slate-200/80 px-6 py-6 dark:border-slate-800"
                >
                    <SheetTitle>{{ sheetTitle }}</SheetTitle>
                    <SheetDescription>
                        {{ sheetDescription }}
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Nome</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="Es. Assicurazione auto"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    :model-value="form.slug"
                                    @update:model-value="
                                        (value) => {
                                            slugDirty = true;
                                            form.slug = String(value);
                                        }
                                    "
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="assicurazione-auto"
                                />
                                <InputError :message="form.errors.slug" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>Categoria padre</Label>
                                <Select
                                    :model-value="String(form.parent_id)"
                                    @update:model-value="
                                        form.parent_id = String($event)
                                    "
                                >
                                    <SelectTrigger class="h-11 rounded-2xl border-slate-200 dark:border-slate-800">
                                        <SelectValue placeholder="Nessuna categoria padre" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE_PARENT">
                                            Nessuna categoria padre
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
                                <InputError :message="form.errors.parent_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="sort_order">Ordinamento</Label>
                                <Input
                                    id="sort_order"
                                    v-model="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                />
                                <InputError :message="form.errors.sort_order" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>Direzione</Label>
                                <Select
                                    :model-value="form.direction_type"
                                    @update:model-value="
                                        form.direction_type = String($event)
                                    "
                                >
                                    <SelectTrigger class="h-11 rounded-2xl border-slate-200 dark:border-slate-800">
                                        <SelectValue placeholder="Seleziona una tipologia" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in directionOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.direction_type" />
                            </div>

                            <div class="grid gap-2">
                                <Label>Gruppo</Label>
                                <Select
                                    :model-value="form.group_type"
                                    @update:model-value="
                                        form.group_type = String($event)
                                    "
                                >
                                    <SelectTrigger class="h-11 rounded-2xl border-slate-200 dark:border-slate-800">
                                        <SelectValue placeholder="Seleziona un gruppo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in groupOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.group_type" />
                            </div>
                        </div>

                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                            <div class="grid gap-3">
                                <Label>Icona categoria</Label>
                                <div
                                    class="grid grid-cols-3 gap-2 sm:grid-cols-4"
                                >
                                    <button
                                        v-for="option in categoryIconOptions"
                                        :key="option.value"
                                        type="button"
                                        :class="[
                                            'flex flex-col items-center gap-2 rounded-2xl border p-3 text-center transition-colors',
                                            form.icon === option.value
                                                ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-950'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-900',
                                        ]"
                                        @click="form.icon = option.value"
                                    >
                                        <component
                                            :is="option.component"
                                            class="h-5 w-5"
                                        />
                                        <span class="text-[11px] leading-4">
                                            {{ option.label }}
                                        </span>
                                    </button>
                                </div>
                                <InputError :message="form.errors.icon" />
                            </div>

                            <div class="grid gap-3">
                                <Label>Colore categoria</Label>
                                <div
                                    class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                >
                                    <div class="grid grid-cols-5 gap-2">
                                        <button
                                            v-for="option in categoryColorOptions"
                                            :key="option.value"
                                            type="button"
                                            :title="option.label"
                                            :class="[
                                                'h-10 rounded-2xl border-2 transition-transform hover:scale-[1.03]',
                                                form.color === option.value
                                                    ? 'border-slate-950 dark:border-slate-100'
                                                    : 'border-white dark:border-slate-900',
                                            ]"
                                            :style="{
                                                backgroundColor: option.value,
                                            }"
                                            @click="form.color = option.value"
                                        />
                                    </div>

                                    <div class="mt-4 grid gap-2">
                                        <Label for="color">Colore personalizzato</Label>
                                        <div class="flex items-center gap-3">
                                            <input
                                                id="color"
                                                v-model="form.color"
                                                type="color"
                                                class="h-11 w-14 rounded-xl border border-slate-200 bg-transparent p-1 dark:border-slate-800"
                                            />
                                            <Input
                                                v-model="form.color"
                                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                                placeholder="#0f766e"
                                            />
                                        </div>
                                        <InputError :message="form.errors.color" />
                                    </div>

                                    <div
                                        class="mt-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white/90 p-3 dark:border-slate-800 dark:bg-slate-950/70"
                                    >
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-2xl text-white"
                                            :style="{
                                                backgroundColor: form.color,
                                            }"
                                        >
                                            <component
                                                :is="resolveCategoryIcon(form.icon)"
                                                class="h-5 w-5"
                                            />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">
                                                Anteprima categoria
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Colore e icona verranno usati nella lista e nelle viste future.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <Label class="text-sm font-medium">
                                Stato e comportamento
                            </Label>

                            <label class="flex items-start gap-3 rounded-2xl bg-white/90 p-3 dark:bg-slate-950/70">
                                <Checkbox
                                    :checked="form.is_selectable"
                                    @update:checked="setSelectableState"
                                    class="mt-0.5"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">
                                        Selezionabile
                                    </span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Se disattivato, la categoria resta visibile ma non compare come scelta operativa.
                                    </span>
                                </span>
                            </label>

                            <label class="flex items-start gap-3 rounded-2xl bg-white/90 p-3 dark:bg-slate-950/70">
                                <Checkbox
                                    :checked="form.is_active"
                                    @update:checked="setActiveState"
                                    class="mt-0.5"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">
                                        Attiva
                                    </span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Le categorie disattive restano in archivio e vengono escluse più facilmente da filtri e selezioni.
                                    </span>
                                </span>
                            </label>

                            <div
                                class="rounded-2xl border border-slate-200 bg-white/90 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300"
                            >
                                Stato attuale:
                                <span class="font-medium text-slate-950 dark:text-slate-50">
                                    {{
                                        form.is_selectable
                                            ? 'Operativa'
                                            : 'Solo contenitore'
                                    }}
                                </span>
                                ·
                                <span class="font-medium text-slate-950 dark:text-slate-50">
                                    {{
                                        form.is_active
                                            ? 'Attiva'
                                            : 'In archivio'
                                    }}
                                </span>
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
                                        : 'Crea categoria'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
