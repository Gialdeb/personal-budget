<script setup lang="ts">
import { computed } from 'vue';
import { sanitizePublicRichText } from '@/lib/public-rich-text';

const props = defineProps<{
    content: string | null | undefined;
}>();

const html = computed(() => sanitizePublicRichText(props.content));
</script>

<template>
    <div
        class="prose prose-slate prose-p:leading-7 prose-a:font-medium prose-a:text-[#de4f3d] prose-a:no-underline hover:prose-a:text-[#c53f31] prose-strong:text-slate-950 prose-ul:pl-5 prose-ol:pl-5 max-w-none"
        v-html="html"
    />
</template>
