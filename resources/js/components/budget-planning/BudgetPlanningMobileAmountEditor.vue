<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import MobileAmountInput from '@/components/MobileAmountInput.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        rowName: string;
        monthLabel: string;
        currency: string;
        amountRaw: number;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [amount: number];
}>();

const { t } = useI18n();
const page = usePage();
const draftValue = ref('');

const moneyFormatLocale = computed(() =>
    String(page.props.auth.user?.format_locale ?? 'it-IT'),
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        draftValue.value = props.amountRaw === 0 ? '' : String(props.amountRaw);
    },
    { immediate: true },
);

function handleValueUpdate(value: string): void {
    draftValue.value = value;

    const parsed = value === '' ? 0 : Number(value);

    if (!Number.isFinite(parsed) || parsed < 0) {
        return;
    }

    emit('save', parsed);
}

function handleOpenUpdate(value: boolean): void {
    emit('update:open', value);
}
</script>

<template>
    <MobileAmountInput
        :model-value="draftValue"
        :format-locale="moneyFormatLocale"
        :currency-code="currency"
        :disabled="disabled"
        :mobile-title="t('planning.mobileEditor.title')"
        :mobile-description="`${rowName} · ${monthLabel}`"
        :mobile-save-label="t('planning.mobileEditor.save')"
        :mobile-clear-label="t('planning.mobileEditor.clear')"
        :editor-open="open"
        :show-trigger="false"
        @update:model-value="handleValueUpdate"
        @update:editor-open="handleOpenUpdate"
    />
</template>
