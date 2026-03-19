<script setup lang="ts">
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AccountBankOption,
    AccountOption,
    AccountTypeOption,
} from '@/types';

defineProps<{
    search: string;
    activeStatus: string;
    accountTypeId: string;
    balanceNature: string;
    bankId: string;
    banks: AccountBankOption[];
    accountTypes: AccountTypeOption[];
    balanceNatureOptions: AccountOption[];
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:activeStatus': [value: string];
    'update:accountTypeId': [value: string];
    'update:balanceNature': [value: string];
    'update:bankId': [value: string];
}>();

const activeOptions = computed(() => [
    { value: 'all', label: 'Tutti' },
    { value: 'active', label: 'Attivi' },
    { value: 'inactive', label: 'Disattivi' },
]);
</script>

<template>
    <section
        class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                </div>
                <div>
                    <p
                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                    >
                        Filtri rapidi
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Cerca per nome, banca, tipo o natura del saldo.
                    </p>
                </div>
            </div>

            <div
                class="grid gap-3 xl:grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,1fr))]"
            >
                <div class="relative">
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Ricerca
                    </Label>
                    <Search
                        class="pointer-events-none absolute top-[calc(50%+0.75rem)] left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <Input
                        :model-value="search"
                        @update:model-value="
                            emit('update:search', String($event))
                        "
                        class="h-11 rounded-2xl border-slate-200 pl-9 dark:border-slate-800"
                        placeholder="Nome, banca, iban o numero"
                    />
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Stato
                    </Label>
                    <Select
                        :model-value="activeStatus"
                        @update:model-value="
                            emit('update:activeStatus', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue placeholder="Stato" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in activeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Tipo account
                    </Label>
                    <Select
                        :model-value="accountTypeId"
                        @update:model-value="
                            emit('update:accountTypeId', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue placeholder="Tipo account" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tutti i tipi</SelectItem>
                            <SelectItem
                                v-for="option in accountTypes"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Natura saldo
                    </Label>
                    <Select
                        :model-value="balanceNature"
                        @update:model-value="
                            emit('update:balanceNature', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue placeholder="Natura saldo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tutte</SelectItem>
                            <SelectItem
                                v-for="option in balanceNatureOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Banca
                    </Label>
                    <Select
                        :model-value="bankId"
                        @update:model-value="
                            emit('update:bankId', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue placeholder="Banca" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tutte le banche</SelectItem>
                            <SelectItem
                                v-for="option in banks"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>
    </section>
</template>
