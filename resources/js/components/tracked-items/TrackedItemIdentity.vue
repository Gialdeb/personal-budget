<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { getInitials } from '@/composables/useInitials';

const props = withDefaults(
    defineProps<{
        name: string;
        logoUrl?: string | null;
        compact?: boolean;
        showName?: boolean;
    }>(),
    {
        logoUrl: null,
        compact: false,
        showName: true,
    },
);

const imageFailed = ref(false);
const nameOpen = ref(false);
function revealName(): void {
    void nextTick(() => {
        nameOpen.value = true;
    });
}
const normalizedName = computed(() =>
    props.name.trim().replace(/\s+/gu, ' ').normalize('NFC'),
);
const initials = computed(
    () =>
        Array.from(getInitials(normalizedName.value)).slice(0, 2).join('') ||
        '?',
);
const avatarStyle = computed(() => {
    const colors = [
        '#6d28d9',
        '#1d4ed8',
        '#0f766e',
        '#166534',
        '#9f1239',
        '#9a3412',
        '#075985',
        '#5b21b6',
    ];
    const hash = Array.from(normalizedName.value.toLowerCase()).reduce(
        (value, character) =>
            (Math.imul(value, 31) + character.codePointAt(0)!) >>> 0,
        0,
    );

    return { backgroundColor: colors[hash % colors.length], color: '#ffffff' };
});

watch(
    () => props.logoUrl,
    () => {
        imageFailed.value = false;
    },
);
</script>

<template>
    <span
        class="inline-flex max-w-full min-w-0 items-center gap-2"
        :title="logoUrl && !imageFailed ? name : undefined"
    >
        <img
            v-if="logoUrl && !imageFailed"
            :src="logoUrl"
            :alt="name"
            :class="
                compact
                    ? 'h-6 max-w-20 sm:h-7 sm:max-w-24'
                    : 'h-9 max-w-28 sm:h-10 sm:max-w-32'
            "
            class="shrink-0 rounded-md object-contain"
            loading="lazy"
            decoding="async"
            @error="imageFailed = true"
        />
        <TooltipProvider v-else :delay-duration="0">
            <Tooltip v-model:open="nameOpen">
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        :aria-label="name"
                        class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        @click.stop="revealName"
                    >
                        <span
                            aria-hidden="true"
                            class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold"
                            :class="
                                compact
                                    ? 'size-7 text-[11px]'
                                    : 'size-10 text-sm'
                            "
                            :style="avatarStyle"
                            >{{ initials }}</span
                        >
                    </button>
                </TooltipTrigger>
                <TooltipContent
                    :collision-padding="12"
                    class="z-[320] max-w-[calc(100vw-1.5rem)] text-sm wrap-anywhere"
                    >{{ name }}</TooltipContent
                >
            </Tooltip>
        </TooltipProvider>
        <span v-if="showName" class="min-w-0 wrap-anywhere">{{ name }}</span>
    </span>
</template>
