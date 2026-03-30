<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
import {
    categoryColorOptions,
    categoryIconOptions,
    resolveCategoryIcon,
} from '@/lib/category-appearance';
import { store, update } from '@/routes/categories';
import type { CategoryItem, CategoryOption } from '@/types';

const NONE_PARENT = '__none__';

const props = defineProps<{
    open: boolean;
    category?: CategoryItem | null;
    suggestedParentUuid?: string | null;
    parentOptions: CategoryItem[];
    directionOptions: CategoryOption[];
    groupOptions: CategoryOption[];
    storeUrl?: string;
    buildUpdateUrl?: (uuid: string) => string;
    createSuccessMessage?: string;
    updateSuccessMessage?: string;
    showSlugField?: boolean;
    lockClassificationToParent?: boolean;
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
    direction_type: '',
    group_type: '',
    icon: 'wallet',
    color: '#0f766e',
    is_selectable: true,
    is_active: true,
    sort_order: 0,
});

let slugDirty = false;

const isEditing = computed(
    () => props.category !== null && props.category !== undefined,
);
const isSystemCategory = computed(() => props.category?.is_system === true);
const isRootSystemCategory = computed(
    () => isSystemCategory.value && props.category?.parent_uuid === null,
);
const selectedParent = computed(
    () => props.parentOptions.find((item) => item.uuid === form.parent_uuid) ?? null,
);
const inheritsParentClassification = computed(
    () => props.lockClassificationToParent && selectedParent.value !== null,
);
const currentSubtreeHeight = computed(() => props.category?.subtree_height ?? 0);
const directionOptionsLabel = computed(
    () => new Map(props.directionOptions.map((option) => [option.value, option.label])),
);
const groupOptionsLabel = computed(
    () => new Map(props.groupOptions.map((option) => [option.value, option.label])),
);
const directionPreviewLabel = computed(
    () =>
        selectedParent.value?.direction_label ||
        directionOptionsLabel.value.get(form.direction_type) ||
        '',
);
const groupPreviewLabel = computed(
    () =>
        selectedParent.value?.group_label ||
        groupOptionsLabel.value.get(form.group_type) ||
        '',
);

const availableParentOptions = computed(() => {
    const maxParentDepth = Math.max(-1, 1 - currentSubtreeHeight.value);

    if (!props.category) {
        return props.parentOptions.filter((item) => item.depth <= 1);
    }

    const forbiddenIds = new Set([
        props.category.uuid,
        ...props.category.descendant_uuids,
    ]);

    return props.parentOptions.filter((item) => {
        const category = props.category;

        if (forbiddenIds.has(item.uuid) || item.depth > maxParentDepth) {
            return false;
        }

        return !(
            category &&
            props.lockClassificationToParent &&
            category.parent_uuid !== null &&
            (item.direction_type !== category.direction_type ||
                item.group_type !== category.group_type)
        );
    });
});

const sheetTitle = computed(() =>
    isEditing.value
        ? t('categories.form.titleEdit')
        : t('categories.form.titleCreate'),
);

const sheetDescription = computed(() =>
    isEditing.value
        ? t('categories.form.descriptionEdit')
        : t('categories.form.descriptionCreate'),
);

watch(
    () => [props.open, props.category, props.suggestedParentUuid] as const,
    ([open, category, suggestedParentUuid]) => {
        if (!open) {
            return;
        }

        slugDirty = false;
        form.clearErrors();

        if (category) {
            form.defaults({
                name: category.name,
                slug: category.slug,
                parent_uuid: category.parent_uuid ?? NONE_PARENT,
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
            parent_uuid: suggestedParentUuid ?? NONE_PARENT,
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

watch(
    selectedParent,
    (parent) => {
        if (!props.lockClassificationToParent || parent === null) {
            return;
        }

        form.direction_type = parent.direction_type;
        form.group_type = parent.group_type;
    },
    { immediate: true },
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
    if (isSystemCategory.value) {
        form.is_active = true;

        return;
    }

    form.is_active = checked === true;
}

function submit(): void {
    const payload = {
        ...form.data(),
        parent_uuid: form.parent_uuid === NONE_PARENT ? null : form.parent_uuid,
        sort_order: Number(form.sort_order || 0),
    };

    if (isEditing.value && props.category) {
        form.transform(() => payload).patch(
            props.buildUpdateUrl?.(props.category.uuid) ?? update.url(props.category.uuid),
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit(
                        'saved',
                        props.updateSuccessMessage ?? t('categories.feedback.updateSuccess'),
                    );
                    closeSheet();
                },
            },
        );

        return;
    }

    form.transform(() => payload).post(props.storeUrl ?? store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit(
                'saved',
                props.createSuccessMessage ?? t('categories.feedback.createSuccess'),
            );
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
                                <Label for="name">{{
                                    t('categories.form.labels.name')
                                }}</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    :disabled="isSystemCategory"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    :placeholder="
                                        t('categories.form.placeholders.name')
                                    "
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div
                                v-if="props.showSlugField"
                                class="grid gap-2"
                            >
                                <Label for="slug">{{
                                    t('categories.form.labels.slug')
                                }}</Label>
                                <Input
                                    id="slug"
                                    :model-value="form.slug"
                                    :disabled="isSystemCategory"
                                    @update:model-value="
                                        (value) => {
                                            slugDirty = true;
                                            form.slug = String(value);
                                        }
                                    "
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    :placeholder="
                                        t('categories.form.placeholders.slug')
                                    "
                                />
                                <InputError :message="form.errors.slug" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>{{
                                    t('categories.form.labels.parent')
                                }}</Label>
                                <Select
                                    :model-value="String(form.parent_uuid)"
                                    :disabled="isRootSystemCategory"
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
                                                    'categories.form.placeholders.noParent',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE_PARENT">
                                            {{
                                                t(
                                                    'categories.form.placeholders.noParent',
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
                                <InputError
                                    :message="form.errors.parent_uuid"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="sort_order">{{
                                    t('categories.form.labels.order')
                                }}</Label>
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
                                <Label>{{
                                    t('categories.form.labels.direction')
                                }}</Label>
                                <template
                                    v-if="
                                        inheritsParentClassification ||
                                        isRootSystemCategory
                                    "
                                >
                                    <Input
                                        :model-value="directionPreviewLabel"
                                        disabled
                                        class="h-11 rounded-2xl border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                    />
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            isRootSystemCategory
                                                ? t(
                                                      'categories.form.help.activeFoundation',
                                                  )
                                                : t(
                                                      'categories.form.help.inheritedDirection',
                                                  )
                                        }}
                                    </p>
                                </template>
                                <template v-else>
                                    <Select
                                        :model-value="form.direction_type"
                                        @update:model-value="
                                            form.direction_type = String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue
                                                :placeholder="
                                                    t(
                                                        'categories.form.placeholders.selectDirection',
                                                    )
                                                "
                                            />
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
                                </template>
                                <InputError
                                    :message="form.errors.direction_type"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('categories.form.labels.group')
                                }}</Label>
                                <template
                                    v-if="
                                        inheritsParentClassification ||
                                        isRootSystemCategory
                                    "
                                >
                                    <Input
                                        :model-value="groupPreviewLabel"
                                        disabled
                                        class="h-11 rounded-2xl border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                    />
                                    <p
                                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            isRootSystemCategory
                                                ? t(
                                                      'categories.form.help.activeFoundation',
                                                  )
                                                : t(
                                                      'categories.form.help.inheritedGroup',
                                                  )
                                        }}
                                    </p>
                                </template>
                                <template v-else>
                                    <Select
                                        :model-value="form.group_type"
                                        @update:model-value="
                                            form.group_type = String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue
                                                :placeholder="
                                                    t(
                                                        'categories.form.placeholders.selectGroup',
                                                    )
                                                "
                                            />
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
                                </template>
                                <InputError :message="form.errors.group_type" />
                            </div>
                        </div>

                        <div
                            class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
                        >
                            <div class="grid gap-3">
                                <Label>{{
                                    t('categories.form.labels.icon')
                                }}</Label>
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
                                            {{
                                                t(
                                                    `categories.form.iconOptions.${option.key}`,
                                                )
                                            }}
                                        </span>
                                    </button>
                                </div>
                                <InputError :message="form.errors.icon" />
                            </div>

                            <div class="grid gap-3">
                                <Label>{{
                                    t('categories.form.labels.color')
                                }}</Label>
                                <div
                                    class="rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                                >
                                    <div class="grid grid-cols-5 gap-2">
                                        <button
                                            v-for="option in categoryColorOptions"
                                            :key="option.value"
                                            type="button"
                                            :title="
                                                t(
                                                    `categories.form.colorOptions.${option.key}`,
                                                )
                                            "
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
                                        <Label for="color">{{
                                            t(
                                                'categories.form.labels.customColor',
                                            )
                                        }}</Label>
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
                                                :placeholder="
                                                    t(
                                                        'categories.form.placeholders.color',
                                                    )
                                                "
                                            />
                                        </div>
                                        <InputError
                                            :message="form.errors.color"
                                        />
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
                                                :is="
                                                    resolveCategoryIcon(
                                                        form.icon,
                                                    )
                                                "
                                                class="h-5 w-5"
                                            />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">
                                                {{
                                                    t(
                                                        'categories.form.labels.preview',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'categories.form.help.preview',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <Label class="text-sm font-medium">
                                {{ t('categories.form.labels.settings') }}
                            </Label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/90 p-3 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :checked="form.is_selectable"
                                    @update:checked="setSelectableState"
                                    class="mt-0.5"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">
                                        {{
                                            t(
                                                'categories.form.labels.selectable',
                                            )
                                        }}
                                    </span>
                                    <span
                                        class="block text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t('categories.form.help.selectable')
                                        }}
                                    </span>
                                </span>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-2xl bg-white/90 p-3 dark:bg-slate-950/70"
                            >
                                <Checkbox
                                    :checked="form.is_active"
                                    :disabled="isSystemCategory"
                                    @update:checked="setActiveState"
                                    class="mt-0.5"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium">
                                        {{ t('categories.form.labels.active') }}
                                    </span>
                                    <span
                                        class="block text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            isSystemCategory
                                                ? t(
                                                      'categories.form.help.activeFoundation',
                                                  )
                                                : t(
                                                      'categories.form.help.active',
                                                  )
                                        }}
                                    </span>
                                </span>
                            </label>

                            <div
                                class="rounded-2xl border border-slate-200 bg-white/90 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300"
                            >
                                {{ t('categories.form.labels.currentState') }}:
                                <span
                                    class="font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        form.is_selectable
                                            ? t(
                                                  'categories.form.state.operational',
                                              )
                                            : t(
                                                  'categories.form.state.container',
                                              )
                                    }}
                                </span>
                                ·
                                <span
                                    class="font-medium text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        form.is_active
                                            ? t('categories.form.state.active')
                                            : t(
                                                  'categories.form.state.archived',
                                              )
                                    }}
                                </span>
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
                                {{ t('categories.form.actions.cancel') }}
                            </Button>
                            <Button
                                type="submit"
                                class="rounded-2xl"
                                :disabled="form.processing"
                            >
                                {{
                                    isEditing
                                        ? t('categories.form.actions.save')
                                        : t('categories.form.actions.create')
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
