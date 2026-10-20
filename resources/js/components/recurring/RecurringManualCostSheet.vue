<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    store,
    update,
    destroy,
} from '@/actions/App/Http/Controllers/RecurringEntryManualCostController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { useForm } from '@/composables/useNotifiedForm';
import type { RecurringManualCost } from '@/types/transactions';

const props = defineProps<{
    open: boolean;
    recurringEntryUuid: string;
    currency: string;
    cost: RecurringManualCost | null;
}>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [date: string];
}>();
const { t } = useI18n();
const isMobile = useMediaQuery('(max-width: 767px)');
const confirmingDelete = ref(false);
const today = new Date().toLocaleDateString('sv-SE');
const form = useForm({ date: today, amount: '' });
watch(
    () => props.open,
    (open) => {
        if (open) {
            form.clearErrors();
            form.date = props.cost?.date ?? today;
            form.amount = props.cost?.amount.toFixed(2) ?? '';
            confirmingDelete.value = false;
        }
    },
);
function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', form.date);
            emit('update:open', false);
        },
    };

    if (props.cost) {
        form.patch(
            update.url([props.recurringEntryUuid, props.cost.uuid]),
            options,
        );
    } else {
        form.post(store.url(props.recurringEntryUuid), options);
    }
}
function remove(): void {
    if (!props.cost) {
        return;
    }

    form.delete(destroy.url([props.recurringEntryUuid, props.cost.uuid]), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            :side="isMobile ? 'bottom' : 'right'"
            class="max-h-[85dvh] overflow-y-auto p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:p-6"
        >
            <SheetHeader>
                <SheetTitle>{{
                    t(
                        cost
                            ? 'recurringDetail.costTrend.editManual'
                            : 'recurringDetail.costTrend.addManual',
                    )
                }}</SheetTitle>
                <SheetDescription>{{
                    t('recurringDetail.costTrend.informational')
                }}</SheetDescription>
            </SheetHeader>
            <form class="space-y-3" @submit.prevent="save">
                <div class="space-y-1">
                    <Label for="manual-cost-date">{{
                        t('recurringDetail.costTrend.date')
                    }}</Label>
                    <AppDatePicker
                        id="manual-cost-date"
                        v-model="form.date"
                        :max="today"
                        required
                    />
                    <InputError :message="form.errors.date" />
                </div>
                <div class="space-y-1">
                    <Label for="manual-cost-amount"
                        >{{ t('recurringDetail.costTrend.amount') }} ·
                        {{ currency }}</Label
                    >
                    <MobileAmountInput
                        id="manual-cost-amount"
                        v-model="form.amount"
                        :currency-code="currency"
                        required
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.amount" />
                </div>
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                    >{{ t('recurringDetail.costTrend.save') }}</Button
                >
            </form>
            <div v-if="cost" class="border-t pt-3">
                <Button
                    v-if="!confirmingDelete"
                    type="button"
                    variant="ghost"
                    class="w-full text-destructive"
                    :disabled="form.processing"
                    @click="confirmingDelete = true"
                    >{{ t('recurringDetail.costTrend.delete') }}</Button
                >
                <div v-else class="space-y-2">
                    <p class="text-sm">
                        {{ t('recurringDetail.costTrend.confirmDelete') }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="form.processing"
                            @click="remove"
                            >{{ t('recurringDetail.costTrend.delete') }}</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="confirmingDelete = false"
                            >{{ t('recurringDetail.costTrend.cancel') }}</Button
                        >
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
