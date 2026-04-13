<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { resolveCategoryIcon } from '@/lib/category-appearance';
import { store, update } from '@/routes/tracked-items';
import type { TrackedItemItem } from '@/types';

const NONE_PARENT = '__none__';

type CategoryOption = {
    value: string;
    label: string;
    full_path?: string;
    icon?: string | null;
    color?: string | null;
};

const props = defineProps<{
    open: boolean;
    trackedItem?: TrackedItemItem | null;
    typeOptions: string[];
    categoryOptions: CategoryOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const { t } = useI18n();

const form = useForm({
    name: '',
    slug: '',
    parent_uuid: NONE_PARENT,
    type: '',
    category_uuids: [] as string[],
    is_active: true,
});
const categorySearch = ref('');
let slugDirty = false;

const isEditing = computed(
    () => props.trackedItem !== null && props.trackedItem !== undefined,
);

const selectedCategoryUuids = computed(() => new Set(form.category_uuids));

const selectedCategoryOptions = computed(() =>
    props.categoryOptions.filter((option) =>
        selectedCategoryUuids.value.has(option.value),
    ),
);

const filteredCategoryOptions = computed(() => {
    const query = categorySearch.value.trim().toLowerCase();

    return props.categoryOptions.filter((option) => {
        if (selectedCategoryUuids.value.has(option.value)) {
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
        ? t('trackedItems.form.titleEdit')
        : t('trackedItems.form.titleCreate'),
);

const sheetDescription = computed(() =>
    isEditing.value
        ? t('trackedItems.form.descriptionEdit')
        : t('trackedItems.form.descriptionCreate'),
);

watch(
    () => [props.open, props.trackedItem] as const,
    ([open, trackedItem]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        slugDirty = false;

        if (trackedItem) {
            form.defaults({
                name: trackedItem.name,
                slug: trackedItem.slug,
                parent_uuid: NONE_PARENT,
                type: trackedItem.type ?? '',
                category_uuids: trackedItem.compatible_category_uuids,
                is_active: trackedItem.is_active,
            });
            form.reset();
            slugDirty = trackedItem.slug !== slugify(trackedItem.name);
            categorySearch.value = '';

            return;
        }

        form.defaults({
            name: '',
            slug: '',
            parent_uuid: NONE_PARENT,
            type: '',
            category_uuids: [],
            is_active: true,
        });
        form.reset();
        categorySearch.value = '';
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
        .replace(/\p{Diacritic}/gu, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function closeSheet(): void {
    emit('update:open', false);
}

function setActiveState(checked: boolean | 'indeterminate'): void {
    form.is_active = checked === true;
}

function addCategory(value: string): void {
    if (selectedCategoryUuids.value.has(value)) {
        return;
    }

    form.category_uuids = [...form.category_uuids, value];
    form.clearErrors('category_uuids');
    categorySearch.value = '';
}

function removeCategory(value: string): void {
    form.category_uuids = form.category_uuids.filter((uuid) => uuid !== value);
}

function categoryPath(option: CategoryOption): string {
    return option.full_path ?? option.label;
}

function categoryIconStyle(option: CategoryOption): Record<string, string> | undefined {
    const color = option.color?.trim();

    if (!color) {
        return undefined;
    }

    return {
        backgroundColor: `${color}1f`,
        color,
        boxShadow: `0 0 0 1px ${color}40 inset`,
    };
}

function submit(): void {
    const payload = {
        ...form.data(),
        slug: form.slug.trim(),
        parent_uuid: form.parent_uuid === NONE_PARENT ? null : form.parent_uuid,
        type: form.type.trim() || null,
        category_uuids: form.category_uuids,
    };

    if (isEditing.value && props.trackedItem) {
        form.transform(() => payload).patch(
            update.url(props.trackedItem.uuid),
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('saved', t('trackedItems.feedback.updateSuccess'));
                    closeSheet();
                },
            },
        );

        return;
    }

    form.transform(() => payload).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', t('trackedItems.feedback.createSuccess'));
            closeSheet();
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-xl">
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
                        <div class="grid gap-2">
                            <Label for="name">{{
                                t('trackedItems.form.labels.name')
                            }}</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                :placeholder="
                                    t('trackedItems.form.placeholders.name')
                                "
                            />
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ t('trackedItems.form.help.name') }}
                            </p>
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="slug">{{
                                t('trackedItems.form.labels.slug')
                            }}</Label>
                            <Input
                                id="slug"
                                :model-value="form.slug"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                :placeholder="
                                    t('trackedItems.form.placeholders.slug')
                                "
                                @update:model-value="
                                    (value) => {
                                        slugDirty = true;
                                        form.slug = String(value);
                                    }
                                "
                            />
                            <InputError :message="form.errors.slug" />
                        </div>

                        <div
                            class="space-y-4 rounded-[1.65rem] border border-slate-200/90 bg-white p-4 shadow-[0_18px_70px_-48px_rgba(15,23,42,0.65)] dark:border-slate-800 dark:bg-slate-950/80"
                        >
                            <div class="space-y-1.5">
                                <Label for="category-search">{{
                                    t(
                                        'trackedItems.form.labels.compatibleCategories',
                                    )
                                }}</Label>
                                <p
                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'trackedItems.form.help.compatibleCategories',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    id="category-search"
                                    v-model="categorySearch"
                                    class="h-12 rounded-2xl border-slate-200 bg-slate-50/80 pl-11 shadow-none transition focus-visible:border-sky-400 focus-visible:ring-sky-200 dark:border-slate-800 dark:bg-slate-900/70"
                                    :placeholder="
                                        t(
                                            'trackedItems.form.placeholders.categorySearch',
                                        )
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-if="selectedCategoryOptions.length > 0"
                                    class="flex flex-wrap gap-2"
                                >
                                    <button
                                        v-for="option in selectedCategoryOptions"
                                        :key="option.value"
                                        type="button"
                                        class="group inline-flex max-w-full items-center gap-2 rounded-2xl border border-sky-200/80 bg-sky-50 px-2.5 py-2 text-left text-xs font-medium text-sky-900 transition hover:border-sky-300 hover:bg-sky-100 focus:ring-2 focus:ring-sky-200 focus:outline-none dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100 dark:hover:bg-sky-500/20"
                                        @click="removeCategory(option.value)"
                                    >
                                        <span
                                            :style="categoryIconStyle(option)"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-white/80 bg-white text-sky-600 shadow-sm dark:border-white/10 dark:bg-slate-950/80 dark:text-sky-200"
                                        >
                                            <component
                                                :is="resolveCategoryIcon(option.icon)"
                                                class="h-4 w-4"
                                            />
                                        </span>
                                        <span class="min-w-0">
                                            <span
                                                class="block truncate text-sm font-semibold"
                                            >
                                                {{ categoryPath(option) }}
                                            </span>
                                        </span>
                                        <span
                                            class="ml-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sky-500 transition group-hover:bg-sky-200/70 group-hover:text-sky-800 dark:text-sky-200 dark:group-hover:bg-sky-500/20"
                                            :aria-label="
                                                t(
                                                    'trackedItems.form.actions.remove',
                                                )
                                            "
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </span>
                                    </button>
                                </div>

                            </div>

                            <div
                                class="max-h-64 space-y-1.5 overflow-y-auto rounded-[1.35rem] border border-slate-200/80 bg-slate-50/70 p-2 dark:border-slate-800 dark:bg-slate-900/45"
                            >
                                <button
                                    v-for="option in filteredCategoryOptions"
                                    :key="option.value"
                                    type="button"
                                    class="group flex w-full items-center gap-3 rounded-[1.1rem] border border-transparent bg-white/80 px-3 py-3 text-left text-sm text-slate-700 transition hover:border-slate-200 hover:bg-white hover:shadow-sm focus:ring-2 focus:ring-sky-200 focus:outline-none dark:bg-slate-950/65 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-950"
                                    @click="addCategory(option.value)"
                                >
                                    <span
                                        :style="categoryIconStyle(option)"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-200/80 bg-slate-100 text-slate-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                                    >
                                        <component
                                            :is="resolveCategoryIcon(option.icon)"
                                            class="h-4.5 w-4.5"
                                        />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="mt-0.5 block truncate text-sm font-semibold text-slate-800 dark:text-slate-100"
                                        >
                                            {{ categoryPath(option) }}
                                        </span>
                                    </span>
                                    <span
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold tracking-[0.12em] text-slate-500 uppercase transition group-hover:bg-sky-100 group-hover:text-sky-700 dark:bg-slate-900 dark:text-slate-400 dark:group-hover:bg-sky-500/15 dark:group-hover:text-sky-200"
                                    >
                                        <Plus class="h-3.5 w-3.5" />
                                        {{ t('trackedItems.form.actions.add') }}
                                    </span>
                                </button>
                                <p
                                    v-if="filteredCategoryOptions.length === 0"
                                    class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'trackedItems.form.emptyCompatibleCategories',
                                        )
                                    }}
                                </p>
                            </div>

                            <InputError :message="form.errors.category_uuids" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="type">{{
                                t('trackedItems.form.labels.type')
                            }}</Label>
                            <Input
                                id="type"
                                v-model="form.type"
                                list="tracked-item-type-options"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                :placeholder="
                                    t('trackedItems.form.placeholders.type')
                                "
                            />
                            <datalist id="tracked-item-type-options">
                                <option
                                    v-for="option in typeOptions"
                                    :key="option"
                                    :value="option"
                                />
                            </datalist>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ t('trackedItems.form.help.type') }}
                            </p>
                            <InputError :message="form.errors.type" />
                        </div>

                        <div
                            class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <Label class="text-sm font-medium">{{
                                t('trackedItems.form.labels.status')
                            }}</Label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/90 p-3 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :checked="form.is_active"
                                    @update:checked="setActiveState"
                                    class="mt-0.5"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">
                                        {{
                                            t('trackedItems.form.labels.active')
                                        }}
                                    </span>
                                    <span
                                        class="block text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ t('trackedItems.form.help.active') }}
                                    </span>
                                </span>
                            </label>

                            <div
                                class="rounded-2xl border border-slate-200 bg-white/90 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300"
                            >
                                {{ t('trackedItems.form.help.statusBox') }}
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-800"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                class="rounded-2xl"
                                @click="closeSheet"
                            >
                                {{ t('trackedItems.form.actions.cancel') }}
                            </Button>
                            <Button
                                type="submit"
                                class="rounded-2xl"
                                :disabled="form.processing"
                            >
                                {{
                                    isEditing
                                        ? t('trackedItems.form.actions.save')
                                        : t('trackedItems.form.actions.create')
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
