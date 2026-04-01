<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { drawKofiWidget, ensureKofiWidgetScript } from '@/lib/kofi-widget';

const props = defineProps<{
    buttonLabel: string;
    buttonColor: string;
    pageId: string;
    scriptUrl: string;
}>();

const host = ref<HTMLElement | null>(null);
const isRendering = ref(false);
const renderedSignature = ref<string | null>(null);

async function renderWidget(): Promise<void> {
    if (!host.value || isRendering.value) {
        return;
    }

    const signature = JSON.stringify({
        buttonLabel: props.buttonLabel,
        buttonColor: props.buttonColor,
        pageId: props.pageId,
        scriptUrl: props.scriptUrl,
    });

    if (renderedSignature.value === signature) {
        return;
    }

    isRendering.value = true;

    const widget = await ensureKofiWidgetScript(props.scriptUrl);

    try {
        drawKofiWidget(
            widget,
            host.value,
            props.buttonLabel,
            props.buttonColor,
            props.pageId,
        );

        renderedSignature.value = signature;
    } finally {
        isRendering.value = false;
    }
}

onMounted(async () => {
    await renderWidget();
});

watch(
    () => [props.buttonLabel, props.buttonColor, props.pageId, props.scriptUrl],
    async () => {
        await renderWidget();
    },
);
</script>

<template>
    <div ref="host" data-kofi-widget-host />
</template>
