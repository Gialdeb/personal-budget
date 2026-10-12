<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { update } from '@/actions/App/Http/Controllers/SalaryEmploymentController';
import InputError from '@/components/InputError.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import RecurringPostingMode from '@/components/recurring/RecurringPostingMode.vue';
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
import type { RecurringEntryFormOptions } from '@/types';
export type SalaryPaymentConfig = {
    id: number;
    kind: string;
    label: string;
    amount: number;
    next_date?: string;
    start_date: string;
    recurrence_type: string;
    account_uuid: string;
    category_uuid: string;
    reference_uuid: string | null;
    automatic: boolean;
    valid_from: string | null;
    currency: string;
    account: string;
    category: string;
    url: string;
};
const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    employment: {
        uuid: string;
        name: string;
        schedules: SalaryPaymentConfig[];
    };
    options: RecurringEntryFormOptions;
}>();
const { t } = useI18n();
type Payment = {
    schedule_id: number | null;
    kind: string;
    label: string;
    amount: number | string;
    start_date: string;
    recurrence_type: string;
    account_uuid: string;
    category_uuid: string;
    reference_uuid: string | null;
};
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const minimum = tomorrow.toLocaleDateString('en-CA');
const form = useForm({
    effective_from: minimum,
    name: '',
    automatic: false,
    payments: [] as Payment[],
});
const customPreset = ref(false);
const preset = computed({
    get: () => {
        if (customPreset.value) {
            return 'custom';
        }

        const extras = form.payments.filter((row) => row.kind === 'additional');

        return extras.length === 0
            ? '12'
            : extras.length === 1 &&
                Number(extras[0].start_date.slice(5, 7)) === 12
              ? '13'
              : extras.length === 2 &&
                  extras.some(
                      (row) => Number(row.start_date.slice(5, 7)) === 12,
                  ) &&
                  extras.some((row) => Number(row.start_date.slice(5, 7)) === 6)
                ? '14'
                : 'custom';
    },
    set: (value: string) => {
        customPreset.value = value === 'custom';
        applyPreset(value);
    },
});
watch(open, (value) => {
    if (value) {
        customPreset.value = false;
        form.clearErrors();
        form.name = props.employment.name;
        form.effective_from = minimum;
        form.automatic = props.employment.schedules[0]?.automatic ?? false;
        form.payments = props.employment.schedules.map((row) => ({
            schedule_id: row.id,
            kind: row.kind,
            label: row.label,
            amount: row.amount,
            start_date: row.next_date ?? row.start_date,
            recurrence_type: row.recurrence_type,
            account_uuid: row.account_uuid,
            category_uuid: row.category_uuid,
            reference_uuid: row.reference_uuid,
        }));
    }
});
function newExtra(month = 12): Payment {
    const main = form.payments.find((row) => row.kind === 'ordinary')!;
    let year = Number(form.effective_from.slice(0, 4));
    let date = `${year}-${String(month).padStart(2, '0')}-01`;

    if (date < form.effective_from) {
        year++;
        date = `${year}-${String(month).padStart(2, '0')}-01`;
    }

    return {
        ...main,
        schedule_id: null,
        kind: 'additional',
        label: month === 12 ? t('salary.thirteenth') : t('salary.fourteenth'),
        start_date: date,
        recurrence_type: 'yearly',
    };
}
function applyPreset(value: string): void {
    if (value === 'custom') {
        return;
    }

    const current = [...form.payments];
    const months = value === '14' ? [12, 6] : value === '13' ? [12] : [];
    form.payments = [
        ...current.filter((row) => row.kind !== 'additional'),
        ...months.map(
            (month) =>
                current.find(
                    (row) =>
                        row.kind === 'additional' &&
                        Number(row.start_date.slice(5, 7)) === month,
                ) ?? newExtra(month),
        ),
    ];
}
function save(): void {
    form.patch(update.url(props.employment.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>
<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader
                ><DialogTitle>{{ t('salary.editEmployment') }}</DialogTitle
                ><DialogDescription>{{
                    t('salary.futureOnlyHint')
                }}</DialogDescription></DialogHeader
            >
            <form class="space-y-5" @submit.prevent="save">
                <label class="grid gap-2 text-sm font-medium"
                    >{{ t('salary.effectiveFrom')
                    }}<Input
                        v-model="form.effective_from"
                        type="date"
                        :min="minimum"
                        required
                /></label>
                <label class="grid gap-2 text-sm"
                    >{{ t('salary.name')
                    }}<Input v-model="form.name" maxlength="150"
                /></label>
                <label class="grid gap-2 text-sm font-medium"
                    >{{ t('salary.preset')
                    }}<select
                        v-model="preset"
                        class="h-11 rounded-xl border bg-background px-3"
                    >
                        <option value="12">{{ t('salary.twelve') }}</option>
                        <option value="13">{{ t('salary.thirteen') }}</option>
                        <option value="14">{{ t('salary.fourteen') }}</option>
                        <option value="custom">{{ t('salary.custom') }}</option>
                    </select></label
                >
                <section
                    v-for="(payment, index) in form.payments"
                    :key="index"
                    class="space-y-3 rounded-2xl border p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-medium">
                            {{ t(`salary.${payment.kind}`) }}
                        </h3>
                        <Button
                            v-if="payment.kind !== 'ordinary'"
                            type="button"
                            variant="ghost"
                            @click="form.payments.splice(index, 1)"
                            >{{ t('salary.remove') }}</Button
                        >
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.label')
                            }}<Input
                                v-model="payment.label"
                                required
                                maxlength="150"
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.amount')
                            }}<Input
                                v-model="payment.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.date')
                            }}<Input
                                v-model="payment.start_date"
                                type="date"
                                required
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.cadence')
                            }}<select
                                v-model="payment.recurrence_type"
                                class="h-11 rounded-xl border bg-background px-3"
                            >
                                <option
                                    v-for="cadence in [
                                        'monthly',
                                        'yearly',
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
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.account')
                            }}<MobileSearchableSelect
                                v-model="payment.account_uuid"
                                :options="options.accounts"
                                :mobile-title="t('salary.account')"
                                :teleport="false"
                                @update:model-value="
                                    payment.category_uuid = '';
                                    payment.reference_uuid = null;
                                "
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >{{ t('salary.category')
                            }}<MobileSearchableSelect
                                v-model="payment.category_uuid"
                                :options="
                                    (
                                        options.categories[
                                            payment.account_uuid
                                        ] ?? []
                                    ).filter(
                                        (row) =>
                                            row.direction_type === 'income',
                                    )
                                "
                                :mobile-title="t('salary.category')"
                                :teleport="false"
                        /></label>
                        <label class="grid gap-1 text-sm sm:col-span-2"
                            >{{ t('salary.employer')
                            }}<MobileSearchableSelect
                                :model-value="payment.reference_uuid ?? ''"
                                @update:model-value="
                                    payment.reference_uuid = $event || null
                                "
                                :options="
                                    (
                                        options.tracked_items[
                                            payment.account_uuid
                                        ] ?? []
                                    ).filter(
                                        (row) =>
                                            row.group_keys?.includes(
                                                'income',
                                            ) ||
                                            row.category_uuids?.includes(
                                                payment.category_uuid,
                                            ),
                                    )
                                "
                                :mobile-title="t('salary.employer')"
                                clearable
                                :teleport="false"
                        /></label>
                    </div>
                </section>
                <Button
                    type="button"
                    variant="outline"
                    @click="
                        customPreset = true;
                        form.payments.push(newExtra());
                    "
                    >{{ t('salary.addSchedule') }}</Button
                >
                <RecurringPostingMode v-model="form.automatic" />
                <div v-if="form.hasErrors" class="space-y-2">
                    <InputError
                        v-for="(message, key) in form.errors"
                        :key="key"
                        :message="message"
                    />
                </div>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="open = false"
                        >{{ t('salary.cancel') }}</Button
                    ><Button :disabled="form.processing">{{
                        t('salary.saveFuture')
                    }}</Button></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>
</template>
