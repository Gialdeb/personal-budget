<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDatePicker from '@/components/AppDatePicker.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { salaryPresetSchedules } from '@/lib/salary-presets';
import type { SalarySetup } from '@/lib/salary-presets';
const model = defineModel<SalarySetup>({ required: true });
const props = defineProps<{
    startDate: string;
    amount: string;
    accounts: { value: string; label: string }[];
    employments: { uuid: string; name: string }[];
}>();
const { t } = useI18n();
const preset = ref('12');
function applyPreset(): void {
    if (preset.value === 'custom') {
        return;
    }

    model.value.additional_schedules = salaryPresetSchedules(
        preset.value,
        props.startDate,
        props.amount,
        [t('salary.thirteenth'), t('salary.fourteenth')],
    );
}
function addSchedule(): void {
    preset.value = 'custom';
    model.value.additional_schedules.push({
        label: '',
        amount: props.amount,
        start_date: props.startDate,
        recurrence_type: 'yearly',
        recurrence_interval: 1,
        account_uuid: '',
    });
}
</script>
<template>
    <section class="space-y-4">
        <label class="grid gap-2 text-sm font-medium"
            >{{ t('salary.preset') }}
            <select
                v-model="preset"
                class="h-11 rounded-2xl border border-input bg-background px-3"
                @change="applyPreset"
            >
                <option value="12">{{ t('salary.twelve') }}</option>
                <option value="13">{{ t('salary.thirteen') }}</option>
                <option value="14">{{ t('salary.fourteen') }}</option>
                <option value="custom">{{ t('salary.custom') }}</option>
            </select>
        </label>
        <div
            v-for="(schedule, index) in model.additional_schedules"
            :key="index"
            class="space-y-3 rounded-2xl border border-border bg-muted/20 p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Input
                    v-model="schedule.label"
                    :aria-label="t('salary.label')"
                    class="min-w-0 flex-1"
                    maxlength="150"
                    :placeholder="t('salary.additional')"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="model.additional_schedules.splice(index, 1)"
                    >{{ t('salary.remove') }}</Button
                >
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1 text-sm"
                    >{{ t('salary.date')
                    }}<AppDatePicker v-model="schedule.start_date" required
                /></label>
                <label class="grid gap-1 text-sm"
                    >{{ t('salary.amount')
                    }}<MobileAmountInput
                        v-model="schedule.amount"
                        min="0.01"
                        required
                /></label>
            </div>
            <details class="space-y-3 text-sm">
                <summary class="cursor-pointer text-muted-foreground">
                    {{ t('salary.paymentOptions') }}
                </summary>
                <label class="grid gap-1"
                    >{{ t('salary.cadence')
                    }}<select
                        v-model="schedule.recurrence_type"
                        class="h-11 rounded-xl border bg-background px-3"
                    >
                        <option
                            v-for="cadence in [
                                'yearly',
                                'monthly',
                                'quarterly',
                                'weekly',
                                'daily',
                            ]"
                            :key="cadence"
                            :value="cadence"
                        >
                            {{ t(`salary.${cadence}`) }}
                        </option>
                    </select></label
                >
                <label class="grid gap-1"
                    >{{ t('salary.account')
                    }}<select
                        v-model="schedule.account_uuid"
                        class="h-11 rounded-xl border bg-background px-3"
                    >
                        <option value="">{{ t('salary.sameAccount') }}</option>
                        <option
                            v-for="account in accounts"
                            :key="account.value"
                            :value="account.value"
                        >
                            {{ account.label }}
                        </option>
                    </select></label
                >
            </details>
        </div>
        <Button
            v-if="preset === 'custom'"
            type="button"
            variant="outline"
            @click="addSchedule"
            >{{ t('salary.addSchedule') }}</Button
        >
        <details class="space-y-4 rounded-2xl border border-border p-4">
            <summary class="cursor-pointer text-sm font-medium">
                {{ t('salary.advanced') }}
            </summary>
            <label v-if="employments.length" class="grid gap-2 text-sm"
                >{{ t('salary.employment')
                }}<select
                    v-model="model.employment_uuid"
                    class="h-11 rounded-xl border bg-background px-3"
                >
                    <option value="">{{ t('salary.newEmployment') }}</option>
                    <option
                        v-for="employment in employments"
                        :key="employment.uuid"
                        :value="employment.uuid"
                    >
                        {{ employment.name }}
                    </option>
                </select></label
            >
            <label v-if="!model.employment_uuid" class="grid gap-2 text-sm"
                >{{ t('salary.name')
                }}<Input
                    v-model="model.name"
                    maxlength="150"
                    :placeholder="t('salary.namePlaceholder')"
            /></label>
            <label v-if="model.employment_uuid" class="grid gap-2 text-sm"
                >{{ t('salary.kind')
                }}<select
                    v-model="model.kind"
                    class="h-11 rounded-xl border bg-background px-3"
                >
                    <option value="ordinary">{{ t('salary.ordinary') }}</option>
                    <option value="additional">
                        {{ t('salary.additional') }}
                    </option>
                    <option value="other">{{ t('salary.other') }}</option>
                </select></label
            >
            <template v-if="!model.employment_uuid">
                <label class="grid gap-2 text-sm"
                    >{{ t('salary.gross')
                    }}<MobileAmountInput
                        v-model="model.annual_gross_salary"
                        :currency-code="model.annual_gross_salary_currency"
                        min="0"
                /></label>
                <label
                    v-if="model.annual_gross_salary"
                    class="grid gap-2 text-sm"
                    >{{ t('salary.currency')
                    }}<Input
                        v-model="model.annual_gross_salary_currency"
                        maxlength="3"
                /></label>
                <p class="text-xs text-muted-foreground">
                    {{ t('salary.grossHint') }}
                </p>
            </template>
        </details>
    </section>
</template>
