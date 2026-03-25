<script setup lang="ts">
import { SlidersHorizontal } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AutomationPipelineOption } from '@/types';

defineProps<{
    pipeline: string;
    status: string;
    triggerType: string;
    pipelineOptions: AutomationPipelineOption[];
    statusOptions: AutomationPipelineOption[];
    triggerOptions: AutomationPipelineOption[];
}>();

const emit = defineEmits<{
    'update:pipeline': [value: string];
    'update:status': [value: string];
    'update:triggerType': [value: string];
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
                            {{ t('admin.automation.filters.title') }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ t('admin.automation.filters.description') }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="text-sm font-medium text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                    @click="emit('reset')"
                >
                    {{ t('admin.automation.filters.reset') }}
                </button>
            </div>

            <div class="grid gap-3 lg:grid-cols-3">
                <div>
                    <Label
                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        {{ t('admin.automation.filters.pipelineLabel') }}
                    </Label>
                    <Select
                        :model-value="pipeline"
                        @update:model-value="
                            emit('update:pipeline', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t(
                                        'admin.automation.filters.pipelinePlaceholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in pipelineOptions"
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
                        {{ t('admin.automation.filters.statusLabel') }}
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
                                    t(
                                        'admin.automation.filters.statusPlaceholder',
                                    )
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
                        {{ t('admin.automation.filters.triggerLabel') }}
                    </Label>
                    <Select
                        :model-value="triggerType"
                        @update:model-value="
                            emit('update:triggerType', String($event))
                        "
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                        >
                            <SelectValue
                                :placeholder="
                                    t(
                                        'admin.automation.filters.triggerPlaceholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in triggerOptions"
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
