<script setup lang="ts">
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type SearchableOption = {
    value: string;
    label: string;
};

const props = withDefaults(defineProps<{
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
    creatable?: boolean;
    creating?: boolean;
    createLabel?: string;
}>(), {
    placeholder: 'Seleziona',
    searchPlaceholder: 'Cerca...',
    emptyLabel: 'Nessun risultato',
    disabled: false,
    clearable: false,
    clearValue: '',
    triggerClass: '',
    contentClass: '',
    creatable: false,
    creating: false,
    createLabel: 'Crea',
});

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

const selectedOption = computed(() =>
    props.options.find((option) => option.value === props.modelValue) ?? null,
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

    updateDropdownPosition();
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
    if (!root.value) {
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
        </button>
        <button
            v-if="canClear"
            type="button"
            class="absolute top-1/2 right-8 -translate-y-1/2 rounded-full p-0.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-900 dark:hover:text-slate-200"
            @click.stop="clearSelection"
        >
            <X class="size-3.5" />
        </button>
        <ChevronsUpDown class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400" />

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdown"
                :style="dropdownStyle"
                :class="
                    cn(
                        'fixed z-[160] min-w-[16rem] rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-white/10 dark:bg-slate-950',
                        contentClass,
                    )
                "
            >
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        ref="searchInput"
                        v-model="searchQuery"
                        :placeholder="searchPlaceholder"
                        class="h-10 rounded-xl border-slate-200 pl-10 dark:border-white/10"
                    />
                </div>

                <div class="mt-2 max-h-64 overflow-y-auto">
                    <button
                        v-for="option in filteredOptions"
                        :key="option.value"
                        type="button"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900"
                        @click="selectOption(option.value)"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <Check
                            v-if="option.value === modelValue"
                            class="ml-3 size-4 shrink-0 text-sky-600 dark:text-sky-300"
                        />
                    </button>

                    <p
                        v-if="filteredOptions.length === 0"
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
                        <span class="truncate">{{ createLabel }} "{{ searchQuery.trim() }}"</span>
                        <span
                            v-if="creating"
                            class="ml-3 text-xs uppercase tracking-[0.16em]"
                        >
                            Salvataggio...
                        </span>
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
