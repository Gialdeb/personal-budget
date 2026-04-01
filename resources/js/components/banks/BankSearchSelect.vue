<script setup lang="ts">
import { Check, ChevronDown, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type BankSearchOption = {
    value: string;
    name: string;
    slug: string;
    country_code: string | null;
    logo_url?: string | null;
    subtitle?: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: BankSearchOption[];
        placeholder?: string;
        emptyLabel?: string;
        searchPlaceholder?: string;
        disabled?: boolean;
        includeEmptyOption?: boolean;
        emptyOptionValue?: string;
        emptyOptionLabel?: string;
    }>(),
    {
        placeholder: 'Seleziona una banca',
        emptyLabel: 'Nessun risultato',
        searchPlaceholder: 'Cerca per banca o paese',
        disabled: false,
        includeEmptyOption: false,
        emptyOptionValue: '',
        emptyOptionLabel: 'Nessuna banca',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const query = ref('');

const normalizedOptions = computed(() => {
    const options = props.options.map((option) => ({
        ...option,
        search_blob: [option.name, option.slug, option.country_code, option.subtitle]
            .filter(Boolean)
            .join(' ')
            .toLowerCase(),
    }));

    if (! props.includeEmptyOption) {
        return options;
    }

    return [
        {
            value: props.emptyOptionValue,
            name: props.emptyOptionLabel,
            slug: '',
            country_code: null,
            subtitle: null,
            logo_url: null,
            search_blob: props.emptyOptionLabel.toLowerCase(),
        },
        ...options,
    ];
});

const selectedOption = computed(() => {
    return normalizedOptions.value.find((option) => option.value === props.modelValue) ?? null;
});

const filteredOptions = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (term === '') {
        return normalizedOptions.value;
    }

    return normalizedOptions.value.filter((option) => option.search_blob.includes(term));
});

const groupedOptions = computed(() => {
    return filteredOptions.value.reduce<Record<string, typeof filteredOptions.value>>((groups, option) => {
        const key = option.country_code ?? 'ALTRO';

        if (! groups[key]) {
            groups[key] = [];
        }

        groups[key].push(option);

        return groups;
    }, {});
});

watch(open, (value) => {
    if (! value) {
        query.value = '';
    }
});

function selectOption(value: string): void {
    emit('update:modelValue', value);
    open.value = false;
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}
</script>

<template>
    <div class="relative">
        <Button
            type="button"
            variant="outline"
            class="h-11 w-full justify-between rounded-2xl border-slate-200 px-3 font-normal dark:border-slate-800"
            :disabled="disabled"
            @click="open = !open"
        >
            <span
                v-if="selectedOption"
                class="flex min-w-0 items-center gap-3 text-left"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200"
                >
                    <img
                        v-if="selectedOption.logo_url"
                        :src="selectedOption.logo_url"
                        :alt="selectedOption.name"
                        class="h-full w-full object-cover"
                    />
                    <span v-else>{{ initials(selectedOption.name) }}</span>
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm text-slate-900 dark:text-slate-100">
                        {{ selectedOption.name }}
                    </span>
                    <span
                        v-if="selectedOption.country_code || selectedOption.subtitle"
                        class="block truncate text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{
                            [selectedOption.country_code, selectedOption.subtitle]
                                .filter(Boolean)
                                .join(' • ')
                        }}
                    </span>
                </span>
            </span>
            <span v-else class="truncate text-sm text-slate-500 dark:text-slate-400">
                {{ placeholder }}
            </span>
            <ChevronDown class="ml-3 h-4 w-4 shrink-0 text-slate-400" />
        </Button>

        <div
            v-if="open"
            class="absolute z-40 mt-2 w-full overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="border-b border-slate-200 p-3 dark:border-slate-800">
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="query"
                        class="h-10 rounded-2xl border-slate-200 pl-9 dark:border-slate-800"
                        :placeholder="searchPlaceholder"
                    />
                </div>
            </div>

            <div class="max-h-80 overflow-y-auto p-2">
                <div v-if="filteredOptions.length === 0" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    {{ emptyLabel }}
                </div>

                <div
                    v-for="(options, group) in groupedOptions"
                    :key="group"
                    class="pb-2 last:pb-0"
                >
                    <div class="px-3 py-2 text-[11px] font-semibold tracking-[0.18em] text-slate-400 uppercase">
                        {{ group }}
                    </div>

                    <button
                        v-for="option in options"
                        :key="option.value"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-900"
                        @click="selectOption(option.value)"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <img
                                v-if="option.logo_url"
                                :src="option.logo_url"
                                :alt="option.name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>{{ initials(option.name) }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ option.name }}
                            </span>
                            <span
                                v-if="option.country_code || option.subtitle"
                                class="block truncate text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ [option.country_code, option.subtitle].filter(Boolean).join(' • ') }}
                            </span>
                        </span>
                        <Check
                            v-if="modelValue === option.value"
                            class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                        />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
