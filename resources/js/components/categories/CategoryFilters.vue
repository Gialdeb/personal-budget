<script setup lang="ts">
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';
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
import type { CategoryOption } from '@/types';

const { t } = useI18n();

defineProps<{
    search: string;
    activeStatus: string;
    selectableStatus: string;
    directionType: string;
    directionOptions: CategoryOption[];
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:activeStatus': [value: string];
    'update:selectableStatus': [value: string];
    'update:directionType': [value: string];
}>();

const activeOptions = computed(() => [
    { value: 'all', label: t('categories.filters.all') },
    { value: 'active', label: t('categories.filters.active') },
    { value: 'inactive', label: t('categories.filters.inactive') },
]);

const selectableOptions = computed(() => [
    { value: 'all', label: t('categories.filters.all') },
    { value: 'selectable', label: t('categories.filters.selectable') },
    {
        value: 'not-selectable',
        label: t('categories.filters.notSelectable'),
    },
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
                        {{ t('categories.filters.title') }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ t('categories.filters.description') }}
                    </p>
                </div>
            </div>

            <div
                class="grid gap-3 xl:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,1fr))]"
            >
                <div class="relative">
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('categories.filters.searchLabel') }}
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
                        :placeholder="t('categories.filters.searchPlaceholder')"
                    />
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('categories.filters.activeLabel') }}
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
                            <SelectValue
                                :placeholder="
                                    t('categories.filters.activePlaceholder')
                                "
                            />
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
                        {{ t('categories.filters.selectableLabel') }}
                    </Label>
                    <Select
                        :model-value="selectableStatus"
                        @update:model-value="
                            emit('update:selectableStatus', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t(
                                        'categories.filters.selectablePlaceholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in selectableOptions"
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
                        {{ t('categories.filters.directionLabel') }}
                    </Label>
                    <Select
                        :model-value="directionType"
                        @update:model-value="
                            emit('update:directionType', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t('categories.filters.directionPlaceholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {{ t('categories.filters.allDirections') }}
                            </SelectItem>
                            <SelectItem
                                v-for="option in directionOptions"
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
