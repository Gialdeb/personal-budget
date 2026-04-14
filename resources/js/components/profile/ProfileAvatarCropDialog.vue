<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const CROP_FRAME_SIZE = 280;
const OUTPUT_SIZE = 512;

const props = defineProps<{
    open: boolean;
    file: File | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [payload: { file: File; previewUrl: string }];
}>();

const { t } = useI18n();

const imageSource = ref<string | null>(null);
const imageElement = ref<HTMLImageElement | null>(null);
const naturalWidth = ref(0);
const naturalHeight = ref(0);
const scale = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const isRendering = ref(false);
const isDragging = ref(false);
const dragStartX = ref(0);
const dragStartY = ref(0);
const dragOriginX = ref(0);
const dragOriginY = ref(0);

const minimumScale = computed(() => {
    if (naturalWidth.value === 0 || naturalHeight.value === 0) {
        return 1;
    }

    return Math.max(
        CROP_FRAME_SIZE / naturalWidth.value,
        CROP_FRAME_SIZE / naturalHeight.value,
    );
});

const maximumScale = computed(() => Math.max(minimumScale.value * 3, 3));

const scaledWidth = computed(() => naturalWidth.value * scale.value);
const scaledHeight = computed(() => naturalHeight.value * scale.value);
const maxOffsetX = computed(() =>
    Math.max(0, (scaledWidth.value - CROP_FRAME_SIZE) / 2),
);
const maxOffsetY = computed(() =>
    Math.max(0, (scaledHeight.value - CROP_FRAME_SIZE) / 2),
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            resetState();

            return;
        }

        if (props.file) {
            loadFile(props.file);
        }
    },
);

watch(
    () => props.file,
    (file) => {
        if (!props.open || !file) {
            return;
        }

        loadFile(file);
    },
);

watch(scale, () => {
    clampOffsets();
});

function resetState(): void {
    imageSource.value = null;
    imageElement.value = null;
    naturalWidth.value = 0;
    naturalHeight.value = 0;
    scale.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
    isRendering.value = false;
}

function closeDialog(): void {
    isDragging.value = false;
    emit('update:open', false);
}

function loadFile(file: File): void {
    const reader = new FileReader();

    reader.onload = () => {
        imageSource.value =
            typeof reader.result === 'string' ? reader.result : null;
    };

    reader.readAsDataURL(file);
}

function initializeCrop(event: Event): void {
    const target = event.target;

    if (!(target instanceof HTMLImageElement)) {
        return;
    }

    imageElement.value = target;
    naturalWidth.value = target.naturalWidth;
    naturalHeight.value = target.naturalHeight;
    scale.value = minimumScale.value;
    offsetX.value = 0;
    offsetY.value = 0;
}

function clampOffsets(): void {
    offsetX.value = Math.max(
        -maxOffsetX.value,
        Math.min(maxOffsetX.value, offsetX.value),
    );
    offsetY.value = Math.max(
        -maxOffsetY.value,
        Math.min(maxOffsetY.value, offsetY.value),
    );
}

function startDragging(event: PointerEvent): void {
    if (!imageSource.value) {
        return;
    }

    isDragging.value = true;
    dragStartX.value = event.clientX;
    dragStartY.value = event.clientY;
    dragOriginX.value = offsetX.value;
    dragOriginY.value = offsetY.value;
}

function dragImage(event: PointerEvent): void {
    if (!isDragging.value) {
        return;
    }

    offsetX.value = dragOriginX.value + (event.clientX - dragStartX.value);
    offsetY.value = dragOriginY.value + (event.clientY - dragStartY.value);
    clampOffsets();
}

function stopDragging(): void {
    isDragging.value = false;
}

async function confirmCrop(): Promise<void> {
    if (!props.file || !imageElement.value) {
        return;
    }

    isRendering.value = true;

    const canvas = document.createElement('canvas');
    canvas.width = OUTPUT_SIZE;
    canvas.height = OUTPUT_SIZE;
    const context = canvas.getContext('2d');

    if (!context) {
        isRendering.value = false;

        return;
    }

    const sourceWidth = Math.min(
        naturalWidth.value,
        CROP_FRAME_SIZE / scale.value,
    );
    const sourceHeight = Math.min(
        naturalHeight.value,
        CROP_FRAME_SIZE / scale.value,
    );
    const rawSourceX =
        naturalWidth.value / 2 -
        (CROP_FRAME_SIZE / 2 + offsetX.value) / scale.value;
    const rawSourceY =
        naturalHeight.value / 2 -
        (CROP_FRAME_SIZE / 2 + offsetY.value) / scale.value;
    const sourceX = Math.max(
        0,
        Math.min(naturalWidth.value - sourceWidth, rawSourceX),
    );
    const sourceY = Math.max(
        0,
        Math.min(naturalHeight.value - sourceHeight, rawSourceY),
    );

    context.drawImage(
        imageElement.value,
        sourceX,
        sourceY,
        sourceWidth,
        sourceHeight,
        0,
        0,
        OUTPUT_SIZE,
        OUTPUT_SIZE,
    );

    const blob = await new Promise<Blob | null>((resolve) => {
        canvas.toBlob((value) => resolve(value), 'image/jpeg', 0.92);
    });

    isRendering.value = false;

    if (!blob) {
        return;
    }

    const fileNameBase = props.file.name.replace(/\.[^.]+$/, '') || 'avatar';
    const croppedFile = new File([blob], `${fileNameBase}-avatar.jpg`, {
        type: 'image/jpeg',
    });

    emit('confirm', {
        file: croppedFile,
        previewUrl: URL.createObjectURL(blob),
    });
    closeDialog();
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ t('settings.profile.avatar.crop.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('settings.profile.avatar.crop.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <div class="space-y-3">
                    <div
                        class="relative mx-auto flex aspect-square w-full max-w-[320px] touch-none items-center justify-center overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900"
                        @pointerdown="startDragging"
                        @pointermove="dragImage"
                        @pointerup="stopDragging"
                        @pointerleave="stopDragging"
                        @pointercancel="stopDragging"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_50%,rgba(15,23,42,0.15)_51%,rgba(15,23,42,0.35)_100%)]"
                        />
                        <img
                            v-if="imageSource"
                            :alt="t('settings.profile.avatar.crop.title')"
                            :src="imageSource"
                            :style="{
                                width: `${naturalWidth * scale}px`,
                                height: `${naturalHeight * scale}px`,
                                transform: `translate(${offsetX}px, ${offsetY}px)`,
                            }"
                            class="max-w-none object-cover select-none"
                            draggable="false"
                            @load="initializeCrop"
                        />
                        <div
                            class="pointer-events-none absolute inset-[1.25rem] rounded-[1.5rem] ring-2 ring-white/90 ring-offset-0"
                        />
                    </div>
                    <p
                        class="text-center text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ t('settings.profile.avatar.crop.helper') }}
                    </p>
                </div>

                <div class="space-y-5">
                    <div class="grid gap-2">
                        <Label for="avatar-zoom">
                            {{ t('settings.profile.avatar.crop.zoom') }}
                        </Label>
                        <Input
                            id="avatar-zoom"
                            v-model.number="scale"
                            type="range"
                            :min="minimumScale"
                            :max="maximumScale"
                            :step="0.01"
                        />
                    </div>
                    <div
                        class="rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-300"
                    >
                        {{ t('settings.profile.avatar.crop.dragHint') }}
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-3 sm:justify-end">
                <Button type="button" variant="outline" @click="closeDialog">
                    {{ t('app.common.cancel') }}
                </Button>
                <Button
                    type="button"
                    :disabled="isRendering || !imageSource"
                    @click="confirmCrop"
                >
                    {{ t('settings.profile.avatar.crop.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
