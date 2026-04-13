<script setup lang="ts">
import Editor from '@tinymce/tinymce-vue';
import tinymce from 'tinymce/tinymce';
import { computed, onMounted, ref, watch } from 'vue';
import 'tinymce/icons/default';
import 'tinymce/models/dom';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/image';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/skins/content/default/content.css';
import 'tinymce/skins/ui/oxide/content.css';
import 'tinymce/skins/ui/oxide/skin.css';
import 'tinymce/themes/silver';
import {
    deleteManagedEditorImage,
    extractManagedImagePaths,
    TINYMCE_EDITOR_BLOCK_FORMATS,
    TINYMCE_EDITOR_PLUGINS,
    TINYMCE_EDITOR_TOOLBAR,
    uploadManagedEditorImage,
} from '@/lib/tinymce-editor';

if (typeof window !== 'undefined') {
    const tinyWindow = window as Window & { tinymce?: typeof tinymce };
    tinyWindow.tinymce = tinymce;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
        uploadLabel?: string;
    }>(),
    {
        placeholder: '',
        uploadLabel: 'Carica immagine',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const content = ref(props.modelValue ?? '');
const trackedManagedPaths = ref(extractManagedImagePaths(content.value));
const isClientReady = ref(false);
const isEditorReady = ref(false);
const isSyncingFromProps = ref(false);

onMounted(() => {
    isClientReady.value = true;
});

watch(
    () => props.modelValue,
    (value) => {
        const normalizedValue = value ?? '';

        if (normalizedValue === content.value) {
            return;
        }

        isSyncingFromProps.value = true;
        content.value = normalizedValue;
        trackedManagedPaths.value = extractManagedImagePaths(normalizedValue);
        queueMicrotask(() => {
            isSyncingFromProps.value = false;
        });
    },
);

async function removeDeletedImages(nextHtml: string): Promise<void> {
    const nextPaths = extractManagedImagePaths(nextHtml);
    const removedPaths = [...trackedManagedPaths.value].filter(
        (path) => !nextPaths.has(path),
    );

    trackedManagedPaths.value = nextPaths;

    if (removedPaths.length === 0) {
        return;
    }

    await Promise.allSettled(
        removedPaths.map((path) => deleteManagedEditorImage(path)),
    );
}

watch(content, (value) => {
    const normalizedValue = value ?? '';

    if (isSyncingFromProps.value) {
        return;
    }

    emit('update:modelValue', normalizedValue);
    void removeDeletedImages(normalizedValue);
});

const init = computed(() => ({
    menubar: false,
    branding: false,
    promotion: false,
    license_key: 'gpl',
    min_height: 420,
    resize: true,
    skin: false,
    content_css: false,
    plugins: TINYMCE_EDITOR_PLUGINS,
    toolbar: TINYMCE_EDITOR_TOOLBAR,
    toolbar_mode: 'sliding',
    block_formats: TINYMCE_EDITOR_BLOCK_FORMATS,
    placeholder: props.placeholder,
    automatic_uploads: true,
    file_picker_types: 'image',
    image_caption: false,
    image_title: true,
    a11y_advanced_options: true,
    image_advtab: false,
    convert_urls: false,
    relative_urls: false,
    remove_script_host: false,
    extended_valid_elements:
        'img[src|alt|title|width|height|class|loading|data-editor-path]',
    link_default_target: '_blank',
    link_assume_external_targets: 'https',
    content_style:
        'body { font-family: ui-sans-serif, system-ui, sans-serif; font-size: 15px; line-height: 1.7; color: #1f2937; } img { max-width: 100%; height: auto; border-radius: 1rem; }',
    setup: (editor: { on: (event: string, callback: () => void) => void }) => {
        editor.on('init', () => {
            isEditorReady.value = true;
        });
    },
    images_upload_handler: async (blobInfo: { blob: () => Blob }) => {
        const payload = await uploadManagedEditorImage(blobInfo.blob());

        return payload.url;
    },
    file_picker_callback: (
        callback: (url: string, meta?: Record<string, string>) => void,
    ) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/webp,image/gif';
        input.onchange = async () => {
            const file = input.files?.[0];

            if (!file) {
                return;
            }

            const payload = await uploadManagedEditorImage(file);

            callback(payload.url, { alt: file.name });
        };
        input.click();
    },
}));
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        data-editor-provider="tinymce"
    >
        <div
            v-if="!isClientReady || !isEditorReady"
            class="pointer-events-none absolute inset-0 z-10 space-y-3 border-b border-slate-200 bg-slate-50/95 p-3"
        >
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="item in 8"
                    :key="item"
                    class="h-9 rounded-xl border border-slate-200 bg-white/80"
                    :class="item < 7 ? 'w-9' : 'w-28'"
                />
            </div>
            <div
                class="rounded-[1.25rem] border border-dashed border-slate-200 bg-white px-4 py-16 text-sm text-slate-500"
            >
                Caricamento editor...
            </div>
        </div>

        <Editor
            v-if="isClientReady"
            v-model="content"
            license-key="gpl"
            output-format="html"
            model-events="change input undo redo blur"
            :init="init"
        />
    </div>
</template>
