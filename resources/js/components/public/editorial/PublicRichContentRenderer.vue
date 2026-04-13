<script setup lang="ts">
import { computed } from 'vue';
import { sanitizePublicRichText } from '@/lib/public-rich-text';

const props = defineProps<{
    content: string | null | undefined;
    class?: string;
}>();

const html = computed(() => sanitizePublicRichText(props.content));
</script>

<template>
    <div
        :class="[
            'max-w-none [&_a]:font-semibold [&_a]:text-[#b65642] [&_a]:underline-offset-4 [&_a]:transition [&_a:hover]:text-[#ea5a47] [&_a:hover]:underline [&_figcaption]:mt-3 [&_figcaption]:text-center [&_figcaption]:text-sm [&_figcaption]:leading-6 [&_figcaption]:text-slate-500 [&_figure]:my-8 [&_figure]:space-y-0 [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-slate-950 [&_h3]:mt-7 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:tracking-tight [&_h3]:text-slate-950 [&_img]:my-6 [&_img]:rounded-2xl [&_img]:border [&_img]:border-[#ece2db] [&_img]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.3)] [&_li]:leading-7 [&_li]:text-slate-700 [&_ol]:mt-4 [&_ol]:list-decimal [&_ol]:space-y-2 [&_ol]:pl-5 [&_p]:text-[1rem] [&_p]:leading-8 [&_p]:text-slate-700 [&_p+p]:mt-5 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5',
            props.class,
        ]"
        v-html="html"
    />
</template>
