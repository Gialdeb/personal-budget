<script setup lang="ts">
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AdminUserFilterValue } from '@/types';

defineProps<{
    search: string;
    role: string;
    status: string;
    plan: string;
    roleOptions: AdminUserFilterValue[];
    statusOptions: AdminUserFilterValue[];
    planOptions: AdminUserFilterValue[];
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:role': [value: string];
    'update:status': [value: string];
    'update:plan': [value: string];
    reset: [];
}>();

const { t } = useI18n();
</script>

<template>
    <section
        class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
    >
        <div class="flex flex-col gap-4">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
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
                            {{ t('admin.users.filters.title') }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ t('admin.users.filters.description') }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="text-sm font-medium text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                    @click="emit('reset')"
                >
                    {{ t('admin.users.filters.reset') }}
                </button>
            </div>

            <div
                class="grid gap-3 xl:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))]"
            >
                <div class="relative">
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('admin.users.filters.searchLabel') }}
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
                        :placeholder="
                            t('admin.users.filters.searchPlaceholder')
                        "
                    />
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('admin.users.filters.roleLabel') }}
                    </Label>
                    <Select
                        :model-value="role"
                        @update:model-value="
                            emit('update:role', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t('admin.users.filters.rolePlaceholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in roleOptions"
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
                        {{ t('admin.users.filters.statusLabel') }}
                    </Label>
                    <Select
                        :model-value="status"
                        @update:model-value="
                            emit('update:status', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t('admin.users.filters.statusPlaceholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in statusOptions"
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
                        {{ t('admin.users.filters.planLabel') }}
                    </Label>
                    <Select
                        :model-value="plan"
                        @update:model-value="
                            emit('update:plan', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t('admin.users.filters.planPlaceholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in planOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>
    </section>
</template>
