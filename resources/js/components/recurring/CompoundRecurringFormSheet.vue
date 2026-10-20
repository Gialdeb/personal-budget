<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { add } from '@/actions/App/Http/Controllers/CompoundRecurringEntryController';
import InputError from '@/components/InputError.vue';
import CompoundRecurringPaymentFields from '@/components/recurring/CompoundRecurringPaymentFields.vue';
import RecurringReminderFields from '@/components/recurring/RecurringReminderFields.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { useForm } from '@/composables/useNotifiedForm';
import type { RecurringEntryFormOptions } from '@/types/transactions';
const props = defineProps<{
    open: boolean;
    compoundUuid: string;
    currency: string;
    reminders: RecurringEntryFormOptions['reminders'];
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const { t } = useI18n();
const mobile = useMediaQuery('(max-width: 767px)');
const row = () => ({
    name: '',
    date: new Date().toLocaleDateString('sv-SE'),
    amount: '',
});
const form = useForm({
    components: [row()],
    reminder_days_before: [] as number[],
});
watch(
    () => props.open,
    (open) => {
        if (open) {
            form.clearErrors();
            form.components = [row()];
        }
    },
);
function error(key: string): string | undefined {
    return (form.errors as Record<string, string>)[key];
}
function submit(): void {
    form.post(add.url(props.compoundUuid), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
    });
}
</script>
<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            :side="mobile ? 'bottom' : 'right'"
            class="max-h-[90dvh] overflow-y-auto p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:w-[32rem] sm:max-w-lg sm:p-6"
        >
            <SheetHeader
                ><SheetTitle>{{ t('recurringDetail.compound.add') }}</SheetTitle
                ><SheetDescription>{{
                    t('recurringDetail.compound.description')
                }}</SheetDescription></SheetHeader
            >
            <form class="space-y-3" @submit.prevent="submit">
                <CompoundRecurringPaymentFields
                    v-model="form.components"
                    :currency="currency"
                    :errors="form.errors"
                />
                <RecurringReminderFields
                    v-model="form.reminder_days_before"
                    :options="reminders"
                    :error="
                        form.errors.reminder_days_before ??
                        error('reminder_days_before.0')
                    "
                />
                <InputError
                    :message="
                        error('entry') ??
                        error('start_date') ??
                        error('expected_amount') ??
                        error('currency')
                    "
                />
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                    >{{ t('recurringDetail.compound.save') }}</Button
                >
            </form>
        </SheetContent>
    </Sheet>
</template>
