<script setup lang="ts">
import {
    ArrowLeft,
    Check,
    ChevronRight,
    ChevronsUpDown,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import SearchableSelectOptionContent from '@/components/transactions/SearchableSelectOptionContent.vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type SearchableOption = {
    value: string;
    label: string;
    groupLabel?: string;
    badgeLabel?: string;
    badgeClass?: string;
    fullPath?: string;
    full_path?: string;
    icon?: string | null;
    color?: string | null;
    ancestor_uuids?: string[];
    is_selectable?: boolean;
    sort_order?: number | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: SearchableOption[];
        placeholder?: string;
        searchPlaceholder?: string;
        emptyLabel?: string;
        disabled?: boolean;
        clearable?: boolean;
        clearValue?: string;
        triggerClass?: string;
        contentClass?: string;
        teleport?: boolean;
        creatable?: boolean;
        creating?: boolean;
        createLabel?: string;
        hierarchical?: boolean;
    }>(),
    {
        placeholder: 'Seleziona',
        searchPlaceholder: 'Cerca...',
        emptyLabel: 'Nessun risultato',
        disabled: false,
        clearable: false,
        clearValue: '',
        triggerClass: '',
        contentClass: '',
        teleport: true,
        creatable: false,
        creating: false,
        createLabel: 'Crea',
        hierarchical: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'create-option': [value: string];
}>();

const root = ref<HTMLElement | null>(null);
const dropdown = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const isOpen = ref(false);
const searchQuery = ref('');
const currentParentValue = ref<string | null>(null);
const dropdownStyle = ref<Record<string, string>>({});

const selectedOption = computed(
    () =>
        props.options.find((option) => option.value === props.modelValue) ??
        null,
);

const canClear = computed(
    () =>
        props.clearable &&
        props.modelValue !== '' &&
        props.modelValue !== props.clearValue,
);

const supportsHierarchy = computed(
    () =>
        props.hierarchical &&
        props.options.some((option) => Array.isArray(option.ancestor_uuids)),
);

const optionsByValue = computed(
    () => new Map(props.options.map((option) => [option.value, option])),
);

const normalizedSearchQuery = computed(() =>
    searchQuery.value.trim().toLowerCase(),
);

const filteredOptions = computed(() => {
    const query = normalizedSearchQuery.value;

    if (query === '') {
        return props.options;
    }

    return props.options.filter((option) =>
        [option.label, option.fullPath, option.full_path, option.value].some(
            (value) =>
                typeof value === 'string' &&
                value.toLowerCase().includes(query),
        ),
    );
});

const visibleHierarchyOptions = computed(() => {
    if (!supportsHierarchy.value || normalizedSearchQuery.value !== '') {
        return [];
    }

    return props.options.filter(
        (option) => resolveParentValue(option) === currentParentValue.value,
    );
});

const groupedVisibleOptions = computed(() => {
    if (supportsHierarchy.value && normalizedSearchQuery.value === '') {
        return [{ label: null, options: visibleHierarchyOptions.value }];
    }

    const groups: Array<{ label: string | null; options: SearchableOption[] }> =
        [];

    for (const option of filteredOptions.value) {
        const currentLabel = option.groupLabel ?? null;
        const currentGroup = groups.at(-1);

        if (!currentGroup || currentGroup.label !== currentLabel) {
            groups.push({
                label: currentLabel,
                options: [option],
            });

            continue;
        }

        currentGroup.options.push(option);
    }

    return groups;
});

const visibleOptionsCount = computed(() =>
    groupedVisibleOptions.value.reduce(
        (count, group) => count + group.options.length,
        0,
    ),
);

const currentParentOption = computed(() =>
    currentParentValue.value === null
        ? null
        : (optionsByValue.value.get(currentParentValue.value) ?? null),
);

const currentHierarchyLabel = computed(() => {
    const option = currentParentOption.value;

    if (!option) {
        return null;
    }

    return option.full_path ?? option.fullPath ?? option.label;
});

const canCreateOption = computed(() => {
    const query = searchQuery.value.trim();

    if (!props.creatable || query === '') {
        return false;
    }

    return !props.options.some(
        (option) => option.label.trim().toLowerCase() === query.toLowerCase(),
    );
});

watch(isOpen, async (open) => {
    if (!open) {
        searchQuery.value = '';
        currentParentValue.value = null;

        return;
    }

    currentParentValue.value = resolveInitialParentValue();

    if (props.teleport) {
        updateDropdownPosition();
    }

    await nextTick();
    searchInput.value?.focus();
});

function handleDocumentClick(event: MouseEvent): void {
    if (!root.value || !dropdown.value) {
        return;
    }

    const target = event.target as Node;

    if (!root.value.contains(target) && !dropdown.value.contains(target)) {
        isOpen.value = false;
    }
}

function updateDropdownPosition(): void {
    if (!props.teleport || !root.value) {
        return;
    }

    const rect = root.value.getBoundingClientRect();

    dropdownStyle.value = {
        top: `${rect.bottom + 8}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
}

if (typeof document !== 'undefined') {
    document.addEventListener('mousedown', handleDocumentClick);
    window.addEventListener('resize', updateDropdownPosition);
    window.addEventListener('scroll', updateDropdownPosition, true);
}

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('mousedown', handleDocumentClick);
        window.removeEventListener('resize', updateDropdownPosition);
        window.removeEventListener('scroll', updateDropdownPosition, true);
    }
});

function toggleOpen(): void {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;
}

function selectOption(value: string): void {
    emit('update:modelValue', value);
    isOpen.value = false;
}

function isSelectable(option: SearchableOption): boolean {
    return option.is_selectable !== false;
}

function resolveParentValue(option: SearchableOption): string | null {
    return option.ancestor_uuids?.at(-1) ?? null;
}

function resolveInitialParentValue(): string | null {
    if (!supportsHierarchy.value) {
        return null;
    }

    return selectedOption.value
        ? resolveParentValue(selectedOption.value)
        : null;
}

function optionHasChildren(option: SearchableOption): boolean {
    return props.options.some(
        (candidate) => resolveParentValue(candidate) === option.value,
    );
}

function openOptionChildren(option: SearchableOption): void {
    if (!optionHasChildren(option)) {
        return;
    }

    currentParentValue.value = option.value;
}

function handleOptionClick(option: SearchableOption): void {
    if (isSelectable(option)) {
        selectOption(option.value);

        return;
    }

    if (
        supportsHierarchy.value &&
        normalizedSearchQuery.value === '' &&
        optionHasChildren(option)
    ) {
        currentParentValue.value = option.value;
    }
}

function goBack(): void {
    const currentParent = currentParentOption.value;

    if (!currentParent) {
        currentParentValue.value = null;

        return;
    }

    currentParentValue.value = resolveParentValue(currentParent);
}

function clearSelection(): void {
    emit('update:modelValue', props.clearValue);
    isOpen.value = false;
}

function createOption(): void {
    const query = searchQuery.value.trim();

    if (query === '') {
        return;
    }

    emit('create-option', query);
}
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <button
            type="button"
            :disabled="disabled"
            :class="
                cn(
                    'app-touch-interactive flex min-h-11 w-full min-w-0 items-center rounded-[1.15rem] border border-slate-200/90 bg-white px-3 pr-14 text-left text-sm shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-all outline-none hover:border-slate-300 hover:bg-slate-50 focus:border-sky-400 focus:shadow-[0_0_0_3px_rgba(14,165,233,0.12)] disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-slate-950/80 dark:hover:border-white/15 dark:hover:bg-slate-900',
                    triggerClass,
                )
            "
            @click="toggleOpen"
        >
            <span
                v-if="selectedOption"
                class="flex min-w-0 flex-1 items-center"
            >
                <SearchableSelectOptionContent
                    :option="selectedOption"
                    compact
                    selected
                />
            </span>
            <span v-else class="flex min-w-0 items-center gap-2">
                <span
                    :class="
                        cn(
                            'truncate',
                            selectedOption
                                ? 'text-slate-900 dark:text-slate-100'
                                : 'text-slate-500 dark:text-slate-400',
                        )
                    "
                >
                    {{ placeholder }}
                </span>
            </span>
        </button>
        <button
            v-if="canClear"
            type="button"
            class="app-touch-interactive absolute top-1/2 right-9 -translate-y-1/2 rounded-full p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-900 dark:hover:text-slate-200"
            @click.stop="clearSelection"
        >
            <X class="size-3.5" />
        </button>
        <ChevronsUpDown
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400"
        />

        <Teleport to="body" :disabled="!teleport">
            <div
                v-if="isOpen"
                ref="dropdown"
                :style="teleport ? dropdownStyle : undefined"
                :class="
                    cn(
                        teleport
                            ? 'fixed z-[160] min-w-[16rem] rounded-[1.4rem] border border-slate-200/80 bg-white/96 p-2 shadow-[0_20px_60px_rgba(15,23,42,0.18)] backdrop-blur dark:border-white/10 dark:bg-slate-950/96'
                            : 'absolute top-[calc(100%+0.5rem)] left-0 z-[220] w-full rounded-[1.4rem] border border-slate-200/80 bg-white/96 p-2 shadow-[0_20px_60px_rgba(15,23,42,0.18)] backdrop-blur dark:border-white/10 dark:bg-slate-950/96',
                        contentClass,
                    )
                "
                @mousedown.stop
                @wheel.stop
            >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <Input
                        ref="searchInput"
                        v-model="searchQuery"
                        :placeholder="searchPlaceholder"
                        class="h-11 rounded-2xl border-slate-200/90 bg-slate-50 pl-11 text-sm shadow-none focus-visible:border-sky-400 focus-visible:ring-sky-200 dark:border-white/10 dark:bg-slate-900/80"
                    />
                </div>

                <div class="mt-2 max-h-72 overflow-y-auto overscroll-contain">
                    <div
                        v-if="supportsHierarchy && normalizedSearchQuery === ''"
                        class="mb-2 flex items-center gap-2 px-1"
                    >
                        <button
                            v-if="currentParentValue !== null"
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-full border border-slate-200/90 bg-slate-50 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="goBack"
                        >
                            <ArrowLeft class="size-3.5" />
                            Indietro
                        </button>
                        <p
                            v-if="currentHierarchyLabel"
                            class="truncate text-xs font-semibold tracking-[0.16em] text-slate-400 uppercase dark:text-slate-500"
                        >
                            {{ currentHierarchyLabel }}
                        </p>
                    </div>

                    <template
                        v-for="group in groupedVisibleOptions"
                        :key="group.label ?? '__ungrouped__'"
                    >
                        <p
                            v-if="group.label"
                            class="px-3 py-2 text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{ group.label }}
                        </p>

                        <button
                            v-for="option in group.options"
                            :key="option.value"
                            type="button"
                            :class="
                                cn(
                                    'app-touch-interactive flex w-full items-center justify-between rounded-[1.1rem] px-3 py-3 text-left transition-all',
                                    option.value === modelValue
                                        ? 'bg-sky-50 text-slate-950 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-white dark:ring-sky-500/25'
                                        : isSelectable(option)
                                          ? 'text-slate-700 hover:bg-slate-50 active:scale-[0.995] dark:text-slate-200 dark:hover:bg-slate-900'
                                          : 'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-900',
                                )
                            "
                            @click="handleOptionClick(option)"
                        >
                            <SearchableSelectOptionContent
                                :option="option"
                                :selected="option.value === modelValue"
                            />

                            <button
                                v-if="
                                    supportsHierarchy &&
                                    normalizedSearchQuery === '' &&
                                    optionHasChildren(option)
                                "
                                type="button"
                                class="app-touch-interactive ml-3 inline-flex size-8 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-200/70 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                @click.stop="openOptionChildren(option)"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                            <Check
                                v-else-if="option.value === modelValue"
                                class="ml-3 size-4 shrink-0 text-sky-600 dark:text-sky-300"
                            />
                        </button>
                    </template>

                    <p
                        v-if="visibleOptionsCount === 0"
                        class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ emptyLabel }}
                    </p>

                    <button
                        v-if="canCreateOption"
                        type="button"
                        class="app-touch-interactive mt-2 flex w-full items-center justify-between rounded-[1.1rem] border border-dashed border-sky-200 px-3 py-3 text-left text-sm text-sky-700 transition-colors hover:bg-sky-50 dark:border-sky-500/20 dark:text-sky-300 dark:hover:bg-sky-500/10"
                        :disabled="creating"
                        @click="createOption"
                    >
                        <span class="truncate"
                            >{{ createLabel }} "{{ searchQuery.trim() }}"</span
                        >
                        <span
                            v-if="creating"
                            class="ml-3 text-xs tracking-[0.16em] uppercase"
                        >
                            Salvataggio...
                        </span>
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
