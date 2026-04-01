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

defineProps<{
    search: string;
    activeStatus: string;
    usageStatus: string;
}>();

const { t } = useI18n();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:activeStatus': [value: string];
    'update:usageStatus': [value: string];
}>();

const activeOptions = computed(() => [
    { value: 'all', label: t('trackedItems.filters.all') },
    { value: 'active', label: t('trackedItems.filters.active') },
    { value: 'inactive', label: t('trackedItems.filters.archived') },
]);

const usageOptions = computed(() => [
    { value: 'all', label: t('trackedItems.filters.all') },
    { value: 'used', label: t('trackedItems.filters.used') },
    { value: 'unused', label: t('trackedItems.filters.unused') },
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
                        {{ t('trackedItems.filters.title') }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ t('trackedItems.filters.description') }}
                    </p>
                </div>
            </div>

            <div class="grid gap-3 xl:grid-cols-[minmax(0,1.6fr)_repeat(2,minmax(0,1fr))]">
                <div class="relative">
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('trackedItems.filters.searchLabel') }}
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
                            t('trackedItems.filters.searchPlaceholder')
                        "
                    />
                </div>

                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('trackedItems.filters.activeLabel') }}
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
                                    t('trackedItems.filters.activePlaceholder')
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
                        {{ t('trackedItems.filters.usageLabel') }}
                    </Label>
                    <Select
                        :model-value="usageStatus"
                        @update:model-value="
                            emit('update:usageStatus', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t('trackedItems.filters.usagePlaceholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in usageOptions"
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
