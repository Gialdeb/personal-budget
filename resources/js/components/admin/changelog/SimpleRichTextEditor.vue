<script setup lang="ts">
import {
    Bold,
    Italic,
    Link2,
    List,
    ListOrdered,
    Pilcrow,
} from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
    }>(),
    {
        placeholder: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = ref<HTMLDivElement | null>(null);

function syncEditor(value: string | null): void {
    if (!editor.value) {
        return;
    }

    const normalizedValue = value ?? '';

    if (editor.value.innerHTML !== normalizedValue) {
        editor.value.innerHTML = normalizedValue;
    }
}

function updateValue(): void {
    emit('update:modelValue', editor.value?.innerHTML ?? '');
}

function apply(command: string, value?: string): void {
    editor.value?.focus();
    document.execCommand(command, false, value);
    updateValue();
}

function addLink(): void {
    const url = window.prompt('URL', 'https://');

    if (!url) {
        return;
    }

    apply('createLink', url);
}

onMounted(() => {
    nextTick(() => syncEditor(props.modelValue));
});

watch(
    () => props.modelValue,
    (value) => syncEditor(value),
);
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div
            class="flex flex-wrap gap-2 border-b border-slate-200 bg-slate-50 p-3"
        >
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                @click="apply('bold')"
            >
                <Bold class="size-4" />
            </button>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                @click="apply('italic')"
            >
                <Italic class="size-4" />
            </button>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                @click="apply('insertUnorderedList')"
            >
                <List class="size-4" />
            </button>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                @click="apply('insertOrderedList')"
            >
                <ListOrdered class="size-4" />
            </button>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                @click="apply('formatBlock', 'p')"
            >
                <Pilcrow class="size-4" />
            </button>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-slate-300 hover:text-slate-950"
                @click="addLink"
            >
                <Link2 class="size-4" />
            </button>
        </div>

        <div
            ref="editor"
            contenteditable="true"
            class="min-h-40 w-full px-4 py-3 text-sm leading-7 text-slate-800 outline-none"
            :data-placeholder="placeholder"
            @input="updateValue"
        />
    </div>
</template>
