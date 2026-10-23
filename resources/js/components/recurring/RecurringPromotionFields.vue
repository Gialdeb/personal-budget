<script setup lang="ts">
import { Gift } from 'lucide-vue-next';
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDatePicker from '@/components/AppDatePicker.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import RecurringPromotionSummary from '@/components/recurring/RecurringPromotionSummary.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RecurringPromotionInput } from '@/types';
const model = defineModel<RecurringPromotionInput | null>({ required: true });
const props = defineProps<{
    currency: string;
    direction: string;
    normalAmount?: string;
}>();
const { t } = useI18n();
const id = useId();
const key = 'recurringDetail.promotion.';
function toggle(event: Event): void {
    model.value = (event.target as HTMLInputElement).checked
        ? {
              amount: '',
              normal_amount: props.normalAmount ?? '',
              duration: 'first_payment',
              outcome: 'unknown',
          }
        : null;
}
</script>
<template>
    <Collapsible
        :open="model !== null"
        class="grid min-w-0 gap-3 rounded-2xl border p-4 transition-colors"
        :class="
            model
                ? 'border-sky-200 bg-sky-50/60 dark:border-sky-500/30 dark:bg-sky-500/10'
                : 'border-border bg-background'
        "
        data-promotion-fields
    >
        <label class="flex min-h-11 min-w-0 cursor-pointer items-center gap-3"
            ><span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300"
                ><Gift class="size-5" aria-hidden="true" /></span
            ><span class="min-w-0 flex-1"
                ><span class="block text-sm font-semibold">{{
                    t(
                        key +
                            (direction === 'income'
                                ? 'incomeOption'
                                : 'option'),
                    )
                }}</span
                ><span class="block text-xs text-muted-foreground">{{
                    t(key + 'hint')
                }}</span></span
            ><span class="relative flex h-6 w-11 shrink-0 items-center"
                ><input
                    type="checkbox"
                    :checked="model !== null"
                    class="peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                    @change="toggle" /><span
                    aria-hidden="true"
                    class="h-6 w-11 rounded-full bg-muted transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2" /><span
                    aria-hidden="true"
                    class="pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-background shadow-sm transition-transform peer-checked:translate-x-5" /></span
        ></label>
        <CollapsibleContent class="space-y-3">
            <template v-if="model">
                <div
                    class="grid min-w-0 gap-1.5 [&>div]:w-full [&>div>div]:w-full"
                >
                    <Label :for="id + '-amount'">{{ t(key + 'amount') }}</Label
                    ><MobileAmountInput
                        :id="id + '-amount'"
                        v-model="model.amount"
                        :currency-code="currency"
                        class="w-full min-w-0"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <p :id="id + '-duration'" class="text-sm font-medium">
                        {{ t(key + 'duration') }}
                    </p>
                    <div
                        role="group"
                        :aria-labelledby="id + '-duration'"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-for="duration in [
                                'first_payment',
                                'payments',
                                'until_date',
                            ] as const"
                            :key="duration"
                            type="button"
                            size="sm"
                            class="h-auto min-h-9 text-left whitespace-normal"
                            :variant="
                                model.duration === duration
                                    ? 'default'
                                    : 'outline'
                            "
                            :aria-pressed="model.duration === duration"
                            @click="model.duration = duration"
                            >{{
                                t(
                                    key +
                                        (direction === 'income' &&
                                        duration === 'first_payment'
                                            ? 'incomeFirst'
                                            : direction === 'income' &&
                                                duration === 'payments'
                                              ? 'incomePayments'
                                              : duration),
                                )
                            }}</Button
                        >
                    </div>
                </div>
                <div v-if="model.duration === 'payments'" class="grid gap-1.5">
                    <Label :for="id + '-payments'">{{
                        t(key + 'count')
                    }}</Label>
                    <Input
                        :id="id + '-payments'"
                        v-model.number="model.payments"
                        type="number"
                        min="1"
                        step="1"
                        required
                    />
                </div>
                <div
                    v-if="model.duration === 'until_date'"
                    class="grid gap-1.5"
                >
                    <Label :for="id + '-until'">{{ t(key + 'until') }}</Label
                    ><AppDatePicker
                        :id="id + '-until'"
                        v-model="model.until_date"
                        required
                    />
                </div>
                <div
                    class="grid min-w-0 gap-1.5 [&>div]:w-full [&>div>div]:w-full"
                >
                    <Label :for="id + '-normal'">{{ t(key + 'normal') }}</Label
                    ><MobileAmountInput
                        :id="id + '-normal'"
                        v-model="model.normal_amount"
                        :currency-code="currency"
                        class="w-full min-w-0"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <p :id="id + '-outcome'" class="text-sm font-medium">
                        {{ t(key + 'outcome') }}
                    </p>
                    <div
                        role="group"
                        :aria-labelledby="id + '-outcome'"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-for="outcome in [
                                'keep',
                                'normal',
                                'unknown',
                            ] as const"
                            :key="outcome"
                            type="button"
                            size="sm"
                            class="h-auto min-h-9 text-left whitespace-normal"
                            :variant="
                                model.outcome === outcome
                                    ? 'default'
                                    : 'outline'
                            "
                            :aria-pressed="model.outcome === outcome"
                            @click="model.outcome = outcome"
                            >{{
                                t(
                                    key +
                                        (outcome === 'keep'
                                            ? direction === 'income'
                                                ? 'incomeKeep'
                                                : 'keep'
                                            : outcome === 'normal'
                                              ? direction === 'income'
                                                  ? 'incomeNormal'
                                                  : 'normalOutcome'
                                              : 'unknown'),
                                )
                            }}</Button
                        >
                    </div>
                </div>
                <RecurringPromotionSummary
                    v-if="
                        Number(model.amount) > 0 &&
                        Number(model.normal_amount) > 0
                    "
                    :promotion="model"
                    :currency="currency"
                    :direction="direction"
                    class="rounded-xl border bg-background/70 p-3"
                />
                <p class="text-xs text-muted-foreground">
                    {{ t(key + 'afterTrial') }}
                </p>
            </template>
        </CollapsibleContent>
    </Collapsible>
</template>
