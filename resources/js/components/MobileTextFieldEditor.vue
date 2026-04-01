<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useMobileSheetViewport } from '@/composables/useMobileSheetViewport';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        open: boolean;
        label: string;
        placeholder?: string;
        description?: string;
        disabled?: boolean;
        multiline?: boolean;
        rows?: number;
    }>(),
    {
        placeholder: '',
        description: undefined,
        disabled: false,
        multiline: false,
        rows: 6,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:open': [value: boolean];
}>();

const { t } = useI18n();
const draftValue = ref('');
const singleLineInput = ref<HTMLInputElement | null>(null);
const multiLineInput = ref<HTMLTextAreaElement | null>(null);
const { mobileFooterStyle, handleFocusIn } = useMobileSheetViewport();

function focusEditorField(): void {
    requestAnimationFrame(() => {
        if (props.multiline) {
            multiLineInput.value?.focus();

            return;
        }

        singleLineInput.value?.focus();
    });
}

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            return;
        }

        draftValue.value = props.modelValue;
        await nextTick();
        focusEditorField();
    },
    { immediate: true },
);

function saveValue(): void {
    emit('update:modelValue', draftValue.value);
    emit('update:open', false);
}

function handleOpenAutoFocus(event: Event): void {
    event.preventDefault();

    void nextTick(() => {
        focusEditorField();
    });
}
</script>

<template>
    <div class="grid gap-2">
        <Label>{{ label }}</Label>

        <button
            type="button"
            :disabled="disabled"
            :class="
                cn(
                    'w-full rounded-2xl border border-slate-200 bg-transparent px-3 py-3 text-left text-sm shadow-xs transition-colors outline-none placeholder:text-slate-400 focus:border-slate-400 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-800 dark:placeholder:text-slate-500',
                    multiline ? 'min-h-28' : 'h-11 py-0',
                )
            "
            @click="emit('update:open', true)"
        >
            <span
                :class="
                    cn(
                        'block truncate',
                        props.modelValue.trim() === ''
                            ? 'text-slate-400 dark:text-slate-500'
                            : 'text-slate-950 dark:text-slate-50',
                    )
                "
            >
                {{
                    props.modelValue.trim() === ''
                        ? placeholder
                        : props.modelValue
                }}
            </span>
        </button>

        <Sheet
            :open="open"
            :modal="false"
            @update:open="emit('update:open', $event)"
        >
            <SheetContent
                side="bottom"
                class="rounded-t-[2rem] px-4 pt-5 pb-[calc(env(safe-area-inset-bottom)+1rem)]"
                @open-auto-focus="handleOpenAutoFocus"
            >
                <SheetHeader class="text-left">
                    <SheetTitle class="text-xl font-semibold tracking-tight">
                        {{ label }}
                    </SheetTitle>
                    <SheetDescription
                        v-if="description"
                        class="text-sm leading-5"
                    >
                        {{ description }}
                    </SheetDescription>
                </SheetHeader>

                <div class="mt-5 space-y-4" @focusin.capture="handleFocusIn">
                    <input
                        v-if="!multiline"
                        ref="singleLineInput"
                        v-model="draftValue"
                        :placeholder="placeholder"
                        autofocus
                        autocomplete="off"
                        autocapitalize="sentences"
                        enterkeyhint="done"
                        class="h-12 w-full rounded-2xl border border-slate-200 bg-transparent px-4 text-base shadow-xs outline-none focus:border-sky-400 dark:border-slate-800"
                    />

                    <textarea
                        v-else
                        ref="multiLineInput"
                        v-model="draftValue"
                        :rows="rows"
                        :placeholder="placeholder"
                        autofocus
                        autocapitalize="sentences"
                        class="min-h-48 w-full rounded-2xl border border-slate-200 bg-transparent px-4 py-3 text-base shadow-xs transition-colors outline-none placeholder:text-slate-400 focus:border-slate-400 dark:border-slate-800 dark:placeholder:text-slate-500"
                    />

                    <div
                        :style="mobileFooterStyle"
                        class="flex flex-col-reverse gap-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="h-12 rounded-2xl"
                            @click="emit('update:open', false)"
                        >
                            {{ t('app.common.cancel') }}
                        </Button>
                        <Button
                            type="button"
                            class="h-12 rounded-2xl"
                            @click="saveValue"
                        >
                            {{ t('app.common.save') }}
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
