<script setup lang="ts">
import { BadgeCheck, CircleOff, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { resolveCategoryIcon } from '@/lib/category-appearance';
import type { CategoryTreeItem } from '@/types';

defineOptions({
    name: 'CategoryTreeList',
});

const props = defineProps<{
    items: CategoryTreeItem[];
    emptyMessage?: string;
    readOnly?: boolean;
    showSlug?: boolean;
    maxParentDepthForChildren?: number;
}>();

const { t } = useI18n();

const emit = defineEmits<{
    edit: [item: CategoryTreeItem];
    createChild: [item: CategoryTreeItem];
    toggleActive: [item: CategoryTreeItem];
    delete: [item: CategoryTreeItem];
}>();

function depthStyle(depth: number): { paddingLeft: string } {
    return {
        paddingLeft: `${Math.min(depth, 5) * 14}px`,
    };
}

function canCreateChild(item: CategoryTreeItem): boolean {
    if (props.maxParentDepthForChildren === undefined) {
        return true;
    }

    return item.depth <= props.maxParentDepthForChildren;
}
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
                :style="depthStyle(item.depth)"
            >
                <div class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl text-white shadow-sm"
                            :style="{
                                backgroundColor: item.color ?? '#334155',
                            }"
                        >
                            <component
                                :is="resolveCategoryIcon(item.icon)"
                                class="h-5 w-5"
                            />
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
                            class="rounded-full"
                            :class="
                                item.is_shared
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            "
                        >
                            {{
                                item.is_shared
                                    ? t('categories.tree.badges.shared')
                                    : t('categories.tree.badges.personal')
                            }}
                        </Badge>
                        <Badge variant="secondary" class="rounded-full">
                            {{ item.direction_label }}
                        </Badge>
                        <Badge variant="secondary" class="rounded-full">
                            {{ item.group_label }}
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
                                    ? t('categories.tree.status.active')
                                    : t('categories.tree.status.inactive')
                            }}
                        </Badge>
                        <Badge
                            class="rounded-full"
                            :class="
                                item.is_selectable
                                    ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                            "
                        >
                            {{
                                item.is_selectable
                                    ? t('categories.tree.status.selectable')
                                    : t('categories.tree.status.container')
                            }}
                        </Badge>
                    </div>

                    <div
                        class="flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span v-if="item.is_shared && item.account_name"
                            >{{
                                t('categories.tree.scopeAccount', {
                                    account: item.account_name,
                                })
                            }}</span
                        >
                        <span
                            v-if="showSlug !== false"
                            >{{ t('categories.tree.fields.slug') }}:
                            {{ item.slug }}</span
                        >
                        <span
                            >{{ t('categories.tree.fields.order') }}:
                            {{ item.sort_order }}</span
                        >
                        <span
                            >{{ t('categories.tree.fields.children') }}:
                            {{ item.children_count }}</span
                        >
                        <span
                            >{{ t('categories.tree.fields.usage') }}:
                            {{ item.usage_count }}</span
                        >
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end"
                >
                    <Button
                        v-if="canCreateChild(item)"
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        :disabled="readOnly"
                        @click="emit('createChild', item)"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('categories.tree.actions.createChild') }}
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        :disabled="readOnly"
                        @click="emit('edit', item)"
                    >
                        <Pencil class="h-4 w-4" />
                        {{ t('categories.tree.actions.edit') }}
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        :disabled="readOnly || item.is_system"
                        @click="emit('toggleActive', item)"
                    >
                        <component
                            :is="item.is_active ? CircleOff : BadgeCheck"
                            class="h-4 w-4"
                        />
                        {{
                            item.is_active
                                ? t('categories.tree.actions.deactivate')
                                : t('categories.tree.actions.activate')
                        }}
                    </Button>
                    <Button
                        variant="destructive"
                        class="h-10 rounded-2xl"
                        :disabled="readOnly || item.is_system || !item.is_deletable"
                        @click="emit('delete', item)"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('categories.tree.actions.delete') }}
                    </Button>
                </div>
            </div>

            <CategoryTreeList
                v-if="item.children.length"
                :items="item.children"
                :read-only="readOnly"
                :show-slug="showSlug"
                :max-parent-depth-for-children="maxParentDepthForChildren"
                @edit="emit('edit', $event)"
                @create-child="emit('createChild', $event)"
                @toggle-active="emit('toggleActive', $event)"
                @delete="emit('delete', $event)"
            />
        </article>
    </div>

    <div
        v-else
        class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50/80 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/60"
    >
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ emptyMessage ?? t('categories.tree.emptyDefault') }}
        </p>
    </div>
</template>
