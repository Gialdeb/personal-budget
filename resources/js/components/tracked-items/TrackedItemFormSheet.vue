<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
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
    parentOptions: TrackedItemItem[];
    typeOptions: string[];
    categoryOptions: Array<{ value: string; label: string }>;
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

const availableParentOptions = computed(() => {
    if (!props.trackedItem) {
        return props.parentOptions;
    }

    const forbiddenIds = new Set([
        props.trackedItem.uuid,
        ...props.trackedItem.descendant_uuids,
    ]);

    return props.parentOptions.filter((item) => !forbiddenIds.has(item.uuid));
});

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
                parent_uuid: trackedItem.parent_uuid ?? NONE_PARENT,
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
                            class="grid gap-3 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div class="space-y-1">
                                <Label for="category-search">{{
                                    t(
                                        'trackedItems.form.labels.compatibleCategories',
                                    )
                                }}</Label>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'trackedItems.form.help.compatibleCategories',
                                        )
                                    }}
                                </p>
                            </div>

                            <Input
                                id="category-search"
                                v-model="categorySearch"
                                class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                :placeholder="
                                    t(
                                        'trackedItems.form.placeholders.categorySearch',
                                    )
                                "
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
                                    <span
                                        class="text-[11px] tracking-[0.16em] uppercase"
                                        >{{
                                            t(
                                                'trackedItems.form.actions.remove',
                                            )
                                        }}</span
                                    >
                                </button>
                            </div>

                            <div
                                class="max-h-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white/90 p-2 dark:border-slate-800 dark:bg-slate-950/70"
                            >
                                <button
                                    v-for="option in filteredCategoryOptions"
                                    :key="option.value"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900"
                                    @click="addCategory(option.value)"
                                >
                                    <span class="truncate">{{
                                        option.label
                                    }}</span>
                                    <span
                                        class="text-xs tracking-[0.16em] text-slate-400 uppercase"
                                    >
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
                            <Label>{{
                                t('trackedItems.form.labels.parent')
                            }}</Label>
                            <Select
                                :model-value="String(form.parent_uuid)"
                                @update:model-value="
                                    form.parent_uuid = String($event)
                                "
                            >
                                <SelectTrigger
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t(
                                                'trackedItems.form.placeholders.noParent',
                                            )
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE_PARENT">
                                        {{
                                            t(
                                                'trackedItems.form.placeholders.noParent',
                                            )
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="item in availableParentOptions"
                                        :key="item.uuid"
                                        :value="item.uuid"
                                    >
                                        {{ item.full_path }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ t('trackedItems.form.help.parent') }}
                            </p>
                            <InputError :message="form.errors.parent_uuid" />
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
