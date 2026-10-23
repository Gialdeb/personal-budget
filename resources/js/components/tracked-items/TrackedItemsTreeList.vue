<script setup lang="ts">
import {
    ArrowRightLeft,
    BadgeCheck,
    CircleOff,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useUiCopy } from '@/i18n/ui-copy';
import type { TrackedItemItem } from '@/types';

const copy = useUiCopy();

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
    moveMovements: [item: TrackedItemItem];
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
                            <img
                                v-if="item.logo_url"
                                :src="item.logo_url"
                                :alt="item.name"
                                class="h-9 w-9 rounded-xl object-contain"
                                loading="lazy"
                            />
                            <template v-else>
                                {{ item.name.slice(0, 1).toUpperCase() }}
                            </template>
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
                            v-if="item.is_shared_expense_counterpart"
                            variant="secondary"
                            class="rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-200"
                        >
                            {{
                                t(
                                    'trackedItems.tree.badges.sharedExpenseCounterpart',
                                )
                            }}
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

                    <Badge v-if="item.canonical_uuid" variant="outline"
                        >{{ copy('Alias permanente di') }}
                        {{ item.canonical_name }}</Badge
                    >
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
                        v-if="item.link_counts"
                        class="space-y-2 text-xs text-muted-foreground"
                        data-reference-link-counts
                    >
                        <p>
                            {{
                                t('trackedItems.tree.links.direct', {
                                    count: item.link_counts.direct,
                                })
                            }}
                            ·
                            {{
                                t('trackedItems.tree.links.indirect', {
                                    count: item.link_counts.indirect,
                                })
                            }}
                        </p>
                        <ul class="flex flex-wrap gap-x-4 gap-y-2">
                            <li
                                v-for="group in item.link_counts.groups"
                                :key="group.key"
                            >
                                <span class="font-medium"
                                    >{{
                                        t(
                                            `trackedItems.tree.links.groups.${group.key}`,
                                        )
                                    }}: {{ group.total }}</span
                                >
                                <span
                                    v-if="group.key === 'shared'"
                                    class="block"
                                >
                                    <span
                                        v-for="(count, type) in group.types"
                                        :key="type"
                                        class="mr-2"
                                        >{{
                                            t(
                                                `trackedItems.tree.links.groups.${type}`,
                                            )
                                        }}: {{ count }}</span
                                    >
                                </span>
                                <span v-if="group.indirect">
                                    ·
                                    {{
                                        t('trackedItems.tree.links.indirect', {
                                            count: group.indirect,
                                        })
                                    }}</span
                                >
                                <span v-if="group.archived">
                                    ·
                                    {{
                                        t('trackedItems.tree.links.archived', {
                                            count: group.archived,
                                        })
                                    }}</span
                                >
                            </li>
                        </ul>
                        <p v-if="item.link_counts.indirect">
                            {{ t('trackedItems.tree.links.previewNote') }}
                        </p>
                    </div>
                    <div
                        v-else
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
                        v-if="item.is_active && !item.canonical_uuid"
                        variant="outline"
                        class="h-10 rounded-2xl border-sky-200 bg-sky-50/70 text-sky-700 hover:bg-sky-100 hover:text-sky-800 dark:border-sky-500/25 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                        :aria-label="
                            copy('Sposta movimenti da {name}', {
                                name: item.name,
                            })
                        "
                        @click="emit('moveMovements', item)"
                    >
                        <ArrowRightLeft class="h-4 w-4" />{{
                            copy('Sposta movimenti')
                        }}</Button
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
                        :disabled="!!item.canonical_uuid"
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
