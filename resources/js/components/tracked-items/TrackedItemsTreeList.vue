<script setup lang="ts">
import { BadgeCheck, CircleOff, Pencil, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TrackedItemItem } from '@/types';

defineOptions({
    name: 'TrackedItemsTreeList',
});

defineProps<{
    items: TrackedItemItem[];
    emptyMessage?: string;
    categoryLabelsByUuid: Record<string, string>;
}>();

const { t } = useI18n();

const emit = defineEmits<{
    edit: [item: TrackedItemItem];
    toggleActive: [item: TrackedItemItem];
    delete: [item: TrackedItemItem];
}>();
</script>

<template>
    <div v-if="items.length" class="space-y-3">
        <article
            v-for="item in items"
            :key="item.uuid"
            class="space-y-3 rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0 space-y-3">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            {{ item.name.slice(0, 1).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p
                                class="truncate text-base font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ item.name }}
                            </p>
                            <p
                                class="truncate text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ item.full_path }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-if="item.type"
                            variant="secondary"
                            class="rounded-full"
                        >
                            {{ item.type }}
                        </Badge>
                        <Badge
                            class="rounded-full"
                            :class="
                                item.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            "
                        >
                            {{
                                item.is_active
                                    ? t('trackedItems.tree.status.active')
                                    : t('trackedItems.tree.status.archived')
                            }}
                        </Badge>
                        <Badge
                            class="rounded-full"
                            :class="
                                item.used
                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                    : 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300'
                            "
                        >
                            {{
                                item.used
                                    ? t('trackedItems.tree.status.used', {
                                          count: item.usage_count,
                                      })
                                    : t('trackedItems.tree.status.unused')
                            }}
                        </Badge>
                    </div>

                    <div class="space-y-2">
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ t('trackedItems.tree.labels.categories') }}
                        </p>
                        <div
                            v-if="item.compatible_category_uuids.length > 0"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="categoryUuid in item.compatible_category_uuids"
                                :key="categoryUuid"
                                variant="secondary"
                                class="rounded-full"
                            >
                                {{
                                    categoryLabelsByUuid[categoryUuid] ??
                                    categoryUuid
                                }}
                            </Badge>
                        </div>
                        <p
                            v-else
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ t('trackedItems.tree.labels.noCategories') }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span v-if="item.counts.transactions > 0">
                            {{
                                t('trackedItems.tree.usage.transactions', {
                                    count: item.counts.transactions,
                                })
                            }}
                        </span>
                        <span v-if="item.counts.budgets > 0">
                            {{
                                t('trackedItems.tree.usage.budgets', {
                                    count: item.counts.budgets,
                                })
                            }}
                        </span>
                        <span v-if="item.counts.recurring_entries > 0">
                            {{
                                t('trackedItems.tree.usage.recurring', {
                                    count: item.counts.recurring_entries,
                                })
                            }}
                        </span>
                        <span v-if="item.counts.scheduled_entries > 0">
                            {{
                                t('trackedItems.tree.usage.scheduled', {
                                    count: item.counts.scheduled_entries,
                                })
                            }}
                        </span>
                        <span
                            v-if="
                                item.counts.transactions +
                                    item.counts.budgets +
                                    item.counts.recurring_entries +
                                    item.counts.scheduled_entries ===
                                0
                            "
                        >
                            {{ t('trackedItems.tree.labels.noUsage') }}
                        </span>
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end"
                >
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        @click="emit('edit', item)"
                    >
                        <Pencil class="h-4 w-4" />
                        {{ t('trackedItems.tree.actions.edit') }}
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        @click="emit('toggleActive', item)"
                    >
                        <component
                            :is="item.is_active ? CircleOff : BadgeCheck"
                            class="h-4 w-4"
                        />
                        {{
                            item.is_active
                                ? t('trackedItems.tree.actions.deactivate')
                                : t('trackedItems.tree.actions.activate')
                        }}
                    </Button>
                    <Button
                        variant="destructive"
                        class="h-10 rounded-2xl"
                        @click="emit('delete', item)"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('trackedItems.tree.actions.delete') }}
                    </Button>
                </div>
            </div>
        </article>
    </div>

    <div
        v-else
        class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50/80 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/60"
    >
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ emptyMessage ?? t('trackedItems.tree.emptyDefault') }}
        </p>
    </div>
</template>
