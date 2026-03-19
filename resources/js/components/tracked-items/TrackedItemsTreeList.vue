<script setup lang="ts">
import { BadgeCheck, CircleOff, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TrackedItemTreeItem } from '@/types';

defineOptions({
    name: 'TrackedItemsTreeList',
});

defineProps<{
    items: TrackedItemTreeItem[];
    emptyMessage?: string;
}>();

const emit = defineEmits<{
    edit: [item: TrackedItemTreeItem];
    createChild: [item: TrackedItemTreeItem];
    toggleActive: [item: TrackedItemTreeItem];
    delete: [item: TrackedItemTreeItem];
}>();

function depthStyle(depth: number): { paddingLeft: string } {
    return {
        paddingLeft: `${Math.min(depth, 5) * 14}px`,
    };
}
</script>

<template>
    <div v-if="items.length" class="space-y-3">
        <article
            v-for="item in items"
            :key="item.id"
            class="space-y-3 rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                :style="depthStyle(item.depth)"
            >
                <div class="min-w-0 space-y-3">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            {{ item.depth === 0 ? 'R' : item.children_count > 0 ? 'N' : 'F' }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-slate-950 dark:text-slate-50">
                                {{ item.name }}
                            </p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
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
                            {{ item.is_active ? 'Attivo' : 'In archivio' }}
                        </Badge>
                        <Badge
                            class="rounded-full"
                            :class="
                                item.used
                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                    : 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300'
                            "
                        >
                            {{ item.used ? `In uso (${item.usage_count})` : 'Mai usato' }}
                        </Badge>
                        <Badge variant="secondary" class="rounded-full">
                            {{ item.children_count > 0 ? `${item.children_count} figli` : 'Foglia' }}
                        </Badge>
                    </div>

                    <div class="flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400">
                        <span v-if="item.parent_full_path">
                            Padre: {{ item.parent_full_path }}
                        </span>
                        <span v-if="item.counts.transactions > 0">
                            {{ item.counts.transactions }} transazioni
                        </span>
                        <span v-if="item.counts.budgets > 0">
                            {{ item.counts.budgets }} budget
                        </span>
                        <span v-if="item.counts.recurring_entries > 0">
                            {{ item.counts.recurring_entries }} ricorrenze
                        </span>
                        <span v-if="item.counts.scheduled_entries > 0">
                            {{ item.counts.scheduled_entries }} scadenze
                        </span>
                        <span v-if="item.counts.transactions + item.counts.budgets + item.counts.recurring_entries + item.counts.scheduled_entries === 0">
                            Nessun utilizzo collegato
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        @click="emit('createChild', item)"
                    >
                        <Plus class="h-4 w-4" />
                        Figlio
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        @click="emit('edit', item)"
                    >
                        <Pencil class="h-4 w-4" />
                        Modifica
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
                        {{ item.is_active ? 'Disattiva' : 'Attiva' }}
                    </Button>
                    <Button
                        variant="destructive"
                        class="h-10 rounded-2xl"
                        @click="emit('delete', item)"
                    >
                        <Trash2 class="h-4 w-4" />
                        Elimina
                    </Button>
                </div>
            </div>

            <TrackedItemsTreeList
                v-if="item.children.length"
                :items="item.children"
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
            {{ emptyMessage ?? 'Nessun elemento da mostrare.' }}
        </p>
    </div>
</template>
