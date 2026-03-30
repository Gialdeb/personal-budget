<script setup lang="ts">
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    eyebrow?: string;
    title?: string;
    description?: string;
    class?: string;
    contentClass?: string;
};

const props = defineProps<Props>();
</script>

<template>
    <section :id="props.id" :class="cn('scroll-mt-24 space-y-8', props.class)">
        <div
            v-if="
                props.eyebrow ||
                props.title ||
                props.description ||
                $slots.header
            "
            class="max-w-3xl space-y-3"
        >
            <slot name="header">
                <p
                    v-if="props.eyebrow"
                    class="text-[11px] font-semibold tracking-[0.24em] text-sky-700 uppercase"
                >
                    {{ props.eyebrow }}
                </p>
                <h2
                    v-if="props.title"
                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl"
                >
                    {{ props.title }}
                </h2>
                <p
                    v-if="props.description"
                    class="text-sm leading-7 text-slate-600 sm:text-base"
                >
                    {{ props.description }}
                </p>
            </slot>
        </div>

        <div :class="props.contentClass">
            <slot />
        </div>
    </section>
</template>
