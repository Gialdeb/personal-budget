<script setup lang="ts">
import {
    BadgeCheck,
    ChevronDown,
    ChevronRight,
    CircleOff,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
    parentUuid?: string | null;
    reorderable?: boolean;
}>();

const { t } = useI18n();

const emit = defineEmits<{
    edit: [item: CategoryTreeItem];
    createChild: [item: CategoryTreeItem];
    toggleActive: [item: CategoryTreeItem];
    delete: [item: CategoryTreeItem];
    reorder: [parentUuid: string | null, items: CategoryTreeItem[]];
}>();

const expandedItems = ref<Record<string, boolean>>({});
const draggedCategoryUuid = ref<string | null>(null);
const dropTargetUuid = ref<string | null>(null);
const activePointerId = ref<number | null>(null);

function canCreateChild(item: CategoryTreeItem): boolean {
    if (props.maxParentDepthForChildren === undefined) {
        return true;
    }

    return item.depth <= props.maxParentDepthForChildren;
}

function hasChildren(item: CategoryTreeItem): boolean {
    return item.children.length > 0;
}

function isExpanded(item: CategoryTreeItem): boolean {
    return expandedItems.value[item.uuid] ?? false;
}

function setExpanded(item: CategoryTreeItem, open: boolean): void {
    expandedItems.value = {
        ...expandedItems.value,
        [item.uuid]: open,
    };
}

function iconBackground(item: CategoryTreeItem): string {
    return `${item.color ?? '#334155'}1f`;
}

function iconBorder(item: CategoryTreeItem): string {
    return `${item.color ?? '#334155'}3d`;
}

function clearDrag(): void {
    activePointerId.value = null;
    draggedCategoryUuid.value = null;
    dropTargetUuid.value = null;
}

function startPointerDrag(event: PointerEvent, item: CategoryTreeItem): void {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    activePointerId.value = event.pointerId;
    draggedCategoryUuid.value = item.uuid;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function categoryAtPointer(event: PointerEvent): CategoryTreeItem | undefined {
    const categoryUuid = document
        .elementFromPoint(event.clientX, event.clientY)
        ?.closest<HTMLElement>('[data-category-uuid]')?.dataset.categoryUuid;

    return props.items.find((item) => item.uuid === categoryUuid);
}

function movePointerDrag(event: PointerEvent): void {
    if (activePointerId.value !== event.pointerId) {
        return;
    }

    event.preventDefault();
    const item = categoryAtPointer(event);
    dropTargetUuid.value =
        item && draggedCategoryUuid.value !== item.uuid ? item.uuid : null;
}

function reorder(fromUuid: string, toUuid: string): void {
    const ordered = [...props.items];
    const from = ordered.findIndex((item) => item.uuid === fromUuid);
    const to = ordered.findIndex((item) => item.uuid === toUuid);

    if (from === -1 || to === -1 || from === to) {
        return;
    }

    const [item] = ordered.splice(from, 1);
    ordered.splice(to, 0, item);
    emit('reorder', props.parentUuid ?? null, ordered);
}

function endPointerDrag(event: PointerEvent): void {
    if (activePointerId.value !== event.pointerId) {
        return;
    }

    const item = categoryAtPointer(event);

    if (draggedCategoryUuid.value && item) {
        reorder(draggedCategoryUuid.value, item.uuid);
    }

    clearDrag();
}

function move(item: CategoryTreeItem, offset: number): void {
    const index = props.items.findIndex(
        (candidate) => candidate.uuid === item.uuid,
    );
    const target = props.items[index + offset];

    if (target) {
        reorder(item.uuid, target.uuid);
    }
}

function forwardReorder(
    parentUuid: string | null,
    items: CategoryTreeItem[],
): void {
    emit('reorder', parentUuid, items);
}
</script>

<template>
    <div v-if="items.length" class="space-y-3">
        <Collapsible
            v-for="item in items"
            :key="item.uuid"
            :open="isExpanded(item)"
            class="overflow-hidden rounded-[1.35rem] border border-slate-200/80 bg-white/96 shadow-[0_20px_55px_-42px_rgba(15,23,42,0.35)] sm:rounded-[1.65rem] dark:border-slate-800 dark:bg-slate-950/82"
            @update:open="setExpanded(item, $event)"
        >
            <div
                :data-category-uuid="item.uuid"
                class="space-y-3 p-3 sm:space-y-4 sm:p-5"
                :class="[
                    draggedCategoryUuid === item.uuid ? 'opacity-50' : '',
                    dropTargetUuid === item.uuid
                        ? 'ring-2 ring-sky-500 ring-inset'
                        : '',
                ]"
            >
                <div class="flex items-start gap-2.5 sm:gap-3">
                    <button
                        v-if="reorderable"
                        type="button"
                        class="flex h-10 w-10 shrink-0 cursor-grab touch-none items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing dark:hover:bg-slate-900 dark:hover:text-slate-200"
                        :aria-label="t('categories.tree.actions.dragHandle')"
                        :title="t('categories.tree.actions.dragHandle')"
                        @click.stop
                        @pointerdown="startPointerDrag($event, item)"
                        @pointermove="movePointerDrag"
                        @pointerup="endPointerDrag"
                        @pointercancel="clearDrag"
                    >
                        <GripVertical class="h-5 w-5" />
                    </button>
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[1.15rem] border sm:h-12 sm:w-12 sm:rounded-2xl dark:border-white/10"
                        :style="{
                            backgroundColor: iconBackground(item),
                            borderColor: iconBorder(item),
                            color: item.color ?? '#334155',
                        }"
                    >
                        <component
                            :is="resolveCategoryIcon(item.icon)"
                            class="h-[18px] w-[18px] sm:h-5 sm:w-5"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start gap-2 sm:gap-3">
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-base leading-5 font-semibold text-slate-950 sm:text-[1.08rem] sm:leading-6 dark:text-slate-50"
                                >
                                    {{ item.name }}
                                </p>
                                <p
                                    class="mt-0.5 truncate text-[11px] leading-4 text-slate-500 sm:text-xs sm:leading-5 dark:text-slate-400"
                                >
                                    {{
                                        item.depth === 0
                                            ? item.direction_label
                                            : item.full_path
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex shrink-0 items-center gap-1.5 sm:gap-2"
                            >
                                <span
                                    v-if="item.children_count > 0"
                                    class="inline-flex min-w-8 items-center justify-center rounded-full px-2.5 py-1 text-[11px] font-semibold sm:min-w-10 sm:px-3 sm:text-xs"
                                    :style="{
                                        backgroundColor: iconBackground(item),
                                        color: item.color ?? '#0f172a',
                                    }"
                                >
                                    {{ item.children_count }}
                                </span>
                                <span
                                    v-if="item.usage_count > 0"
                                    class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white sm:min-w-10 sm:px-3 sm:text-xs dark:bg-slate-100 dark:text-slate-950"
                                >
                                    {{ item.usage_count }}
                                </span>
                                <CollapsibleTrigger
                                    v-if="hasChildren(item)"
                                    as-child
                                >
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-[1.05rem] border border-slate-200/80 bg-slate-50 text-slate-600 transition hover:bg-slate-100 sm:h-10 sm:w-10 sm:rounded-2xl dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                        :aria-label="
                                            isExpanded(item)
                                                ? t(
                                                      'categories.tree.actions.collapse',
                                                  )
                                                : t(
                                                      'categories.tree.actions.expand',
                                                  )
                                        "
                                    >
                                        <ChevronDown
                                            v-if="isExpanded(item)"
                                            class="h-4 w-4"
                                        />
                                        <ChevronRight v-else class="h-4 w-4" />
                                    </button>
                                </CollapsibleTrigger>
                            </div>
                        </div>

                        <div
                            class="mt-2.5 flex flex-wrap gap-1.5 sm:mt-3 sm:gap-2"
                        >
                            <Badge
                                class="rounded-full px-2.5 py-0.5 text-[11px] sm:px-3 sm:py-1 sm:text-xs"
                                :class="
                                    item.is_shared
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{
                                    item.is_shared
                                        ? t('categories.tree.badges.shared')
                                        : t('categories.tree.badges.personal')
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
                            class="mt-2.5 flex flex-wrap gap-x-3 gap-y-1.5 text-[11px] leading-4 text-slate-500 sm:mt-3 sm:gap-x-4 sm:gap-y-2 sm:text-xs sm:leading-5 dark:text-slate-400"
                        >
                            <span
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{ t('categories.tree.fields.order') }}:
                                {{ item.sort_order }}
                            </span>
                            <span>
                                {{ t('categories.tree.fields.children') }}:
                                {{ item.children_count }}
                            </span>
                            <span>
                                {{ t('categories.tree.fields.usage') }}:
                                {{ item.usage_count }}
                            </span>
                            <span v-if="item.is_shared && item.account_name">
                                {{
                                    t('categories.tree.scopeAccount', {
                                        account: item.account_name,
                                    })
                                }}
                            </span>
                            <span
                                v-if="showSlug !== false"
                                class="hidden sm:inline"
                            >
                                {{ t('categories.tree.fields.slug') }}:
                                {{ item.slug }}
                            </span>
                            <span class="hidden sm:inline">
                                {{ item.direction_label }} ·
                                {{ item.group_label }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 xl:grid-cols-4">
                    <div
                        v-if="reorderable"
                        class="col-span-2 flex gap-2 xl:hidden"
                    >
                        <Button
                            variant="secondary"
                            class="min-h-10 flex-1 rounded-[1.05rem]"
                            :disabled="items[0]?.uuid === item.uuid"
                            @click="move(item, -1)"
                        >
                            {{ t('categories.tree.actions.moveUp') }}
                        </Button>
                        <Button
                            variant="secondary"
                            class="min-h-10 flex-1 rounded-[1.05rem]"
                            :disabled="
                                items[items.length - 1]?.uuid === item.uuid
                            "
                            @click="move(item, 1)"
                        >
                            {{ t('categories.tree.actions.moveDown') }}
                        </Button>
                    </div>
                    <Button
                        v-if="canCreateChild(item)"
                        variant="secondary"
                        class="h-auto min-h-10 justify-start rounded-[1.05rem] bg-slate-100/90 px-3 py-2.5 text-left text-sm whitespace-normal text-slate-800 hover:bg-slate-200 sm:min-h-11 sm:rounded-2xl sm:px-4 sm:py-3 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                        :disabled="readOnly"
                        @click="emit('createChild', item)"
                    >
                        <Plus class="h-4 w-4 shrink-0" />
                        <span class="leading-4">{{
                            t('categories.tree.actions.createChild')
                        }}</span>
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-auto min-h-10 justify-start rounded-[1.05rem] bg-slate-100/90 px-3 py-2.5 text-left text-sm whitespace-normal text-slate-800 hover:bg-slate-200 sm:min-h-11 sm:rounded-2xl sm:px-4 sm:py-3 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                        :disabled="readOnly"
                        @click="emit('edit', item)"
                    >
                        <Pencil class="h-4 w-4 shrink-0" />
                        <span class="leading-4">{{
                            t('categories.tree.actions.edit')
                        }}</span>
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-auto min-h-10 justify-start rounded-[1.05rem] bg-slate-100/90 px-3 py-2.5 text-left text-sm whitespace-normal text-slate-800 hover:bg-slate-200 sm:min-h-11 sm:rounded-2xl sm:px-4 sm:py-3 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                        :disabled="readOnly || item.is_system"
                        @click="emit('toggleActive', item)"
                    >
                        <component
                            :is="item.is_active ? CircleOff : BadgeCheck"
                            class="h-4 w-4 shrink-0"
                        />
                        <span class="leading-4">
                            {{
                                item.is_active
                                    ? t('categories.tree.actions.deactivate')
                                    : t('categories.tree.actions.activate')
                            }}
                        </span>
                    </Button>
                    <Button
                        variant="destructive"
                        class="h-auto min-h-10 justify-start rounded-[1.05rem] px-3 py-2.5 text-left text-sm whitespace-normal sm:min-h-11 sm:rounded-2xl sm:px-4 sm:py-3"
                        :disabled="
                            readOnly || item.is_system || !item.is_deletable
                        "
                        @click="emit('delete', item)"
                    >
                        <Trash2 class="h-4 w-4 shrink-0" />
                        <span class="leading-4">{{
                            t('categories.tree.actions.delete')
                        }}</span>
                    </Button>
                </div>
            </div>

            <CollapsibleContent v-if="hasChildren(item)">
                <div
                    class="border-t border-slate-200/70 bg-slate-50/60 px-2.5 py-2.5 sm:px-3 sm:py-3 dark:border-slate-800 dark:bg-slate-950/45"
                >
                    <div
                        class="border-l border-slate-200/80 pl-2.5 sm:pl-4 dark:border-slate-800"
                    >
                        <CategoryTreeList
                            :items="item.children"
                            :read-only="readOnly"
                            :show-slug="showSlug"
                            :max-parent-depth-for-children="
                                maxParentDepthForChildren
                            "
                            :parent-uuid="item.uuid"
                            :reorderable="reorderable"
                            @edit="emit('edit', $event)"
                            @create-child="emit('createChild', $event)"
                            @toggle-active="emit('toggleActive', $event)"
                            @delete="emit('delete', $event)"
                            @reorder="forwardReorder"
                        />
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
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
