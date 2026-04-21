<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { SupportRequestCategory, SupportRequestStatus } from '@/types';

const props = defineProps<{
    value: SupportRequestStatus | SupportRequestCategory;
    kind: 'status' | 'category';
}>();

const label = computed(() => {
    if (props.kind === 'category') {
        return {
            bug: 'Bug',
            feature_request: 'Feature request',
            general_support: 'General support',
        }[props.value as SupportRequestCategory];
    }

    return {
        new: 'New',
        in_progress: 'In progress',
        closed: 'Closed',
    }[props.value as SupportRequestStatus];
});

const className = computed(() => {
    if (props.kind === 'category') {
        return {
            bug: 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/15 dark:text-rose-300',
            feature_request:
                'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:border-sky-500/25 dark:bg-sky-500/15 dark:text-sky-300',
            general_support:
                'border-border bg-background/80 text-muted-foreground',
        }[props.value as SupportRequestCategory];
    }

    return {
        new: 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/15 dark:text-amber-300',
        in_progress:
            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:border-sky-500/25 dark:bg-sky-500/15 dark:text-sky-300',
        closed: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-300',
    }[props.value as SupportRequestStatus];
});
</script>

<template>
    <Badge
        variant="outline"
        class="rounded-full border px-2.5 py-1 text-[11px] font-medium"
        :class="className"
    >
        {{ label }}
    </Badge>
</template>
