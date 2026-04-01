<script setup lang="ts">
import { Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { resolveCategoryIcon } from '@/lib/category-appearance';
import { cn } from '@/lib/utils';

type SearchableOption = {
    value: string;
    label: string;
    groupLabel?: string;
    badgeLabel?: string;
    badgeClass?: string;
    fullPath?: string;
    full_path?: string;
    icon?: string | null;
    color?: string | null;
    is_selectable?: boolean;
};

const props = withDefaults(
    defineProps<{
        option: SearchableOption;
        selected?: boolean;
        compact?: boolean;
    }>(),
    {
        selected: false,
        compact: false,
    },
);

const fullPath = computed(() => {
    const resolvedPath = props.option.fullPath ?? props.option.full_path;

    if (typeof resolvedPath === 'string' && resolvedPath.trim() !== '') {
        return resolvedPath.trim();
    }

    return props.option.label;
});

const pathSegments = computed(() =>
    fullPath.value
        .split('>')
        .map((segment) => segment.trim())
        .filter((segment) => segment !== ''),
);

const isCategoryLike = computed(
    () =>
        Boolean(props.option.icon) ||
        Boolean(props.option.color) ||
        pathSegments.value.length > 1,
);

const title = computed(() => {
    if (!isCategoryLike.value) {
        return props.option.label;
    }

    return pathSegments.value.at(-1) ?? props.option.label;
});

const hierarchyLabel = computed(() => {
    if (!isCategoryLike.value || pathSegments.value.length <= 1) {
        return null;
    }

    return pathSegments.value.slice(0, -1).join(' / ');
});

const resolvedIcon = computed(() =>
    isCategoryLike.value ? resolveCategoryIcon(props.option.icon) : Wallet,
);

const iconContainerStyle = computed(() => {
    const accentColor = props.option.color?.trim();

    if (!accentColor) {
        return undefined;
    }

    return {
        backgroundColor: `${accentColor}1f`,
        color: accentColor,
        boxShadow: props.selected
            ? `0 0 0 1px ${accentColor}55 inset`
            : undefined,
    };
});

const resolvedBadgeClass = computed(
    () =>
        props.option.badgeClass ??
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200',
);

const isSelectable = computed(() => props.option.is_selectable !== false);
</script>

<template>
    <span class="flex min-w-0 items-center gap-3">
        <span
            v-if="isCategoryLike"
            :style="iconContainerStyle"
            :class="
                cn(
                    'flex shrink-0 items-center justify-center rounded-2xl border border-slate-200/70 bg-slate-100 text-slate-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300',
                    compact ? 'size-9' : 'size-10',
                )
            "
        >
            <component
                :is="resolvedIcon"
                :class="compact ? 'size-4' : 'size-[1.1rem]'"
            />
        </span>

        <span class="min-w-0 flex-1">
            <span
                v-if="hierarchyLabel"
                class="block truncate text-[11px] font-semibold tracking-[0.16em] text-slate-400 uppercase dark:text-slate-500"
            >
                {{ hierarchyLabel }}
            </span>

            <span class="mt-0.5 flex min-w-0 items-center gap-2">
                <span
                    :class="
                        cn(
                            'truncate text-sm font-medium',
                            selected
                                ? 'text-slate-950 dark:text-white'
                                : isSelectable
                                  ? 'text-slate-800 dark:text-slate-100'
                                  : 'text-slate-500 dark:text-slate-400',
                            compact && isCategoryLike
                                ? 'text-[0.95rem] font-semibold'
                                : '',
                        )
                    "
                >
                    {{ title }}
                </span>

                <span
                    v-if="option.badgeLabel"
                    :class="
                        cn(
                            'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                            resolvedBadgeClass,
                        )
                    "
                >
                    {{ option.badgeLabel }}
                </span>
            </span>
        </span>
    </span>
</template>
