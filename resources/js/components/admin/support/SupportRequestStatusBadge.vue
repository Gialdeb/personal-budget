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
            bug: 'border-rose-200 bg-rose-50 text-rose-700',
            feature_request: 'border-sky-200 bg-sky-50 text-sky-700',
            general_support: 'border-slate-200 bg-slate-50 text-slate-700',
        }[props.value as SupportRequestCategory];
    }

    return {
        new: 'border-amber-200 bg-amber-50 text-amber-700',
        in_progress: 'border-sky-200 bg-sky-50 text-sky-700',
        closed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
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
