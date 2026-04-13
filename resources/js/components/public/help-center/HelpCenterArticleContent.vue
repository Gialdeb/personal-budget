<script setup lang="ts">
import PublicRichContentRenderer from '@/components/public/editorial/PublicRichContentRenderer.vue';

defineProps<{
    body: string | null;
}>();
</script>

<template>
    <PublicRichContentRenderer
        :content="body"
        class="rounded-[2rem] border border-[#ece2db] bg-white px-6 py-7 shadow-[0_24px_70px_-50px_rgba(15,23,42,0.18)] sm:px-8 sm:py-9"
    />
</template>
