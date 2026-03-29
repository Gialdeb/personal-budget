<script setup lang="ts">
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type SearchableOption = {
    value: string;
    label: string;
    groupLabel?: string;
    badgeLabel?: string;
    badgeClass?: string;
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
const dropdownStyle = ref<Record<string, string>>({});

const selectedOption = computed(
    () =>
        props.options.find((option) => option.value === props.modelValue) ??
        null,
);

const selectedOptionBadgeClass = computed(
    () =>
        selectedOption.value?.badgeClass ??
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200',
);

const canClear = computed(
    () =>
        props.clearable &&
        props.modelValue !== '' &&
        props.modelValue !== props.clearValue,
);

const filteredOptions = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return props.options;
    }

    return props.options.filter((option) =>
        option.label.toLowerCase().includes(query),
    );
});

const groupedFilteredOptions = computed(() => {
    const groups: Array<{ label: string | null; options: SearchableOption[] }> = [];

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

        return;
    }

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
    <div ref="root" class="relative">
        <button
            type="button"
            :disabled="disabled"
            :class="
                cn(
                    'flex h-11 w-full items-center rounded-2xl border px-3 pr-14 text-left text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                    triggerClass,
                )
            "
            @click="toggleOpen"
        >
            <span class="flex min-w-0 items-center gap-2">
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
                    {{ selectedOption?.label ?? placeholder }}
                </span>
                <span
                    v-if="selectedOption?.badgeLabel"
                    :class="
                        cn(
                            'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            selectedOptionBadgeClass,
                        )
                    "
                >
                    {{ selectedOption.badgeLabel }}
                </span>
            </span>
        </button>
        <button
            v-if="canClear"
            type="button"
            class="absolute top-1/2 right-8 -translate-y-1/2 rounded-full p-0.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-900 dark:hover:text-slate-200"
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
                            ? 'fixed z-[160] min-w-[16rem] rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-white/10 dark:bg-slate-950'
                            : 'absolute top-[calc(100%+0.5rem)] left-0 z-[220] w-full rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-white/10 dark:bg-slate-950',
                        contentClass,
                    )
                "
                @mousedown.stop
                @wheel.stop
            >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <Input
                        ref="searchInput"
                        v-model="searchQuery"
                        :placeholder="searchPlaceholder"
                        class="h-10 rounded-xl border-slate-200 pl-10 dark:border-white/10"
                    />
                </div>

                <div class="mt-2 max-h-64 overflow-y-auto overscroll-contain">
                    <template
                        v-for="group in groupedFilteredOptions"
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
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900"
                            @click="selectOption(option.value)"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="truncate">{{ option.label }}</span>
                                <span
                                    v-if="option.badgeLabel"
                                    :class="
                                        cn(
                                            'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            option.badgeClass ??
                                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200',
                                        )
                                    "
                                >
                                    {{ option.badgeLabel }}
                                </span>
                            </span>
                            <Check
                                v-if="option.value === modelValue"
                                class="ml-3 size-4 shrink-0 text-sky-600 dark:text-sky-300"
                            />
                        </button>
                    </template>

                    <p
                        v-if="groupedFilteredOptions.length === 0"
                        class="px-3 py-4 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ emptyLabel }}
                    </p>

                    <button
                        v-if="canCreateOption"
                        type="button"
                        class="mt-2 flex w-full items-center justify-between rounded-xl border border-dashed border-sky-200 px-3 py-2 text-left text-sm text-sky-700 transition-colors hover:bg-sky-50 dark:border-sky-500/20 dark:text-sky-300 dark:hover:bg-sky-500/10"
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
