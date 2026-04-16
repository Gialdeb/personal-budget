<script setup lang="ts">
import { CircleAlert, CircleCheckBig } from 'lucide-vue-next';
import { computed } from 'vue';
import type { ToastFeedback } from '@/composables/useToastFeedback';

const props = defineProps<{
    items: Array<ToastFeedback | null | undefined>;
}>();

const visibleItems = computed(() =>
    props.items.filter((item): item is ToastFeedback => item != null),
);
</script>

<template>
    <div
        v-if="visibleItems.length > 0"
        class="pointer-events-none fixed inset-x-4 bottom-4 z-[90] flex flex-col gap-3 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-full sm:max-w-sm"
    >
        <TransitionGroup
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
            <div
                v-for="item in visibleItems"
                :key="`${item.variant}:${item.title}:${item.message}`"
                class="pointer-events-auto overflow-hidden rounded-[1.5rem] border shadow-2xl"
                :class="
                    item.variant === 'default'
                        ? 'border-emerald-200 bg-emerald-500 text-white'
                        : 'border-rose-200 bg-rose-600 text-white'
                "
            >
                <div class="flex items-start gap-3 px-4 py-4">
                    <div
                        class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15"
                    >
                        <CircleCheckBig
                            v-if="item.variant === 'default'"
                            class="h-5 w-5"
                        />
                        <CircleAlert v-else class="h-5 w-5" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-semibold">
                            {{ item.title }}
                        </p>
                        <p class="mt-1 text-sm text-white/90">
                            {{ item.message }}
                        </p>
                    </div>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>
