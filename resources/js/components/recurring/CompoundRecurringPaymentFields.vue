<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
export type CompoundPaymentDraft = {
    name: string;
    date: string;
    amount: string;
};
const props = withDefaults(
    defineProps<{
        currency: string;
        errors?: Record<string, string | undefined>;
        minDate?: string;
    }>(),
    { errors: () => ({}) },
);
const model = defineModel<CompoundPaymentDraft[]>({ required: true });
const { t } = useI18n();
const today = new Date().toLocaleDateString('sv-SE');
function addPayment(): void {
    model.value = [...model.value, { name: '', date: today, amount: '' }];
}
</script>
<template>
    <div class="space-y-3">
        <fieldset
            v-for="(component, index) in model"
            :key="index"
            class="space-y-2 rounded-xl border p-3"
        >
            <legend class="px-1 text-xs font-medium">
                {{
                    t('recurringDetail.compound.component', {
                        number: index + 1,
                    })
                }}
            </legend>
            <Input
                :id="`compound-payment-${index}-name`"
                v-model="component.name"
                :aria-invalid="Boolean(errors[`components.${index}.name`])"
                :aria-describedby="
                    errors[`components.${index}.name`]
                        ? `compound-payment-${index}-name-error`
                        : undefined
                "
                :aria-label="t('recurringDetail.compound.componentName')"
                :placeholder="t('recurringDetail.compound.componentName')"
                required
                maxlength="100"
            />
            <InputError
                :id="`compound-payment-${index}-name-error`"
                :message="errors[`components.${index}.name`]"
            />
            <Label :for="`compound-payment-${index}-date`">{{
                t('recurringDetail.compound.firstDate')
            }}</Label
            ><AppDatePicker
                :id="`compound-payment-${index}-date`"
                v-model="component.date"
                :min="minDate"
                :aria-invalid="Boolean(errors[`components.${index}.date`])"
                :aria-describedby="
                    errors[`components.${index}.date`]
                        ? `compound-payment-${index}-date-error`
                        : undefined
                "
                required
            />
            <InputError
                :id="`compound-payment-${index}-date-error`"
                :message="errors[`components.${index}.date`]"
            />
            <MobileAmountInput
                :id="`compound-payment-${index}-amount`"
                :aria-invalid="Boolean(errors[`components.${index}.amount`])"
                :aria-describedby="
                    errors[`components.${index}.amount`]
                        ? `compound-payment-${index}-amount-error`
                        : undefined
                "
                v-model="component.amount"
                :label="t('recurringDetail.compound.amount')"
                :currency-code="currency"
                required
            />
            <InputError
                :id="`compound-payment-${index}-amount-error`"
                :message="errors[`components.${index}.amount`]"
            />
            <Button
                v-if="model.length > 1"
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 text-xs"
                @click="model.splice(index, 1)"
                >{{ t('recurringDetail.compound.removeRow') }}</Button
            >
        </fieldset>
        <InputError :message="props.errors.components" />
        <Button
            v-if="model.length < 12"
            type="button"
            variant="outline"
            size="sm"
            @click="addPayment"
            >{{ t('recurringDetail.compound.add') }}</Button
        >
    </div>
</template>
