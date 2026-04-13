<script setup lang="ts">
import PublicRichContentRenderer from '@/components/public/editorial/PublicRichContentRenderer.vue';

const props = defineProps<{
    content: string | null | undefined;
}>();
</script>

<template>
    <PublicRichContentRenderer
        :content="props.content"
        class="prose prose-slate prose-a:no-underline hover:prose-a:text-[#c53f31] prose-strong:text-slate-950"
    />
</template>
