<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    preview as previewRefund,
    store,
} from '@/actions/App/Http/Controllers/RecurringPlanRefundController';
import { show as showTransactions } from '@/actions/App/Http/Controllers/TransactionsController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@/composables/useNotifiedForm';
import { formatCurrency } from '@/lib/currency';
import type { PlanRefundPreview } from '@/types/recurring-refunds';

const props = defineProps<{
    planUuid: string;
    preview: PlanRefundPreview;
    hideTrigger?: boolean;
}>();
const { t } = useI18n();
const data = ref(props.preview);
const open = ref(false);
const loading = ref(false);
const loadError = ref('');
const form = useForm({
    request_uuid: '',
    transaction_date: '',
    account_uuid: '',
    expected_total_cents: 0,
    irreversible: false,
});
const triggerDisabled = computed(
    () =>
        loading.value ||
        form.processing ||
        data.value.total_cents <= 0 ||
        !data.value.accounts.length,
);
defineExpose({ open: openRefund, triggerDisabled });
const errors = computed(() => form.errors as Record<string, string>);
watch(
    () => props.preview,
    (value) => {
        data.value = value;
    },
);

function transactionUrl(uuid: string, date: string): string {
    const [year, month] = date.split('-').map(Number);

    return showTransactions.url(
        { year, month },
        { query: { highlight: uuid, source: 'recurring' } },
    );
}

async function openRefund(): Promise<void> {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await fetch(previewRefund.url(props.planUuid), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('preview');
        }

        data.value = await response.json();
        form.clearErrors();
        form.request_uuid = crypto.randomUUID();
        const now = new Date();
        form.transaction_date = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        form.account_uuid = data.value.accounts.some(
            (account) => account.uuid === data.value.account_uuid,
        )
            ? data.value.account_uuid
            : (data.value.accounts[0]?.uuid ?? '');
        form.expected_total_cents = data.value.total_cents;
        form.irreversible = false;
        open.value = true;
    } catch {
        loadError.value = t('recurringDetail.planRefund.failed');
    } finally {
        loading.value = false;
    }
}

function submit(): void {
    if (
        !form.irreversible ||
        form.processing ||
        form.expected_total_cents <= 0
    ) {
        return;
    }

    form.post(store.url(props.planUuid), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <section class="min-w-0 space-y-3" data-plan-refund>
        <Button
            v-if="!hideTrigger"
            variant="outline"
            size="sm"
            :disabled="triggerDisabled"
            @click="openRefund"
        >
            {{ t('recurringDetail.planRefund.action') }}
        </Button>
        <p
            v-if="data.total_cents <= 0 && !data.refunds.length"
            class="text-xs text-muted-foreground"
        >
            {{ t('recurringDetail.planRefund.unavailable') }}
        </p>
        <InputError :message="loadError" />
        <div
            v-if="data.refunds.length"
            class="space-y-2 rounded-xl border p-3 text-sm"
        >
            <h2 class="font-semibold">
                {{ t('recurringDetail.planRefund.history') }}
            </h2>
            <article
                v-for="refund in data.refunds"
                :key="refund.uuid"
                class="space-y-1 border-t pt-2"
            >
                <Link
                    :href="transactionUrl(refund.uuid, refund.date)"
                    class="font-medium text-primary underline"
                >
                    {{ t('recurringDetail.planRefund.credit') }} ·
                    {{ refund.date }} · {{ refund.account }} ·
                    <SensitiveValue
                        :value="
                            formatCurrency(
                                refund.amount_cents / 100,
                                data.currency,
                            )
                        "
                    />
                </Link>
                <ul class="space-y-1 text-xs text-muted-foreground">
                    <li
                        v-for="(payment, index) in refund.payments"
                        :key="`${payment.uuid}-${index}`"
                    >
                        <Link
                            :href="transactionUrl(payment.uuid, payment.date)"
                            class="underline"
                        >
                            {{ t('recurringDetail.planRefund.payment') }} ·
                            {{ payment.date }} ·
                            <SensitiveValue
                                :value="
                                    formatCurrency(
                                        payment.amount_cents / 100,
                                        data.currency,
                                    )
                                "
                            />
                        </Link>
                    </li>
                </ul>
            </article>
        </div>
        <Dialog v-model:open="open">
            <DialogContent
                class="max-h-[90dvh] overflow-y-auto sm:max-w-lg"
                @interact-outside="form.processing && $event.preventDefault()"
                @escape-key-down="form.processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        t('recurringDetail.planRefund.title')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('recurringDetail.planRefund.description')
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <label
                            for="plan-refund-date"
                            class="text-sm font-medium"
                            >{{ t('recurringDetail.planRefund.date') }}</label
                        >
                        <AppDatePicker
                            id="plan-refund-date"
                            v-model="form.transaction_date"
                            :disabled="form.processing"
                        />
                        <InputError :message="errors.transaction_date" />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="plan-refund-account"
                            class="text-sm font-medium"
                            >{{
                                t('recurringDetail.planRefund.account')
                            }}</label
                        >
                        <Select
                            v-model="form.account_uuid"
                            :disabled="form.processing"
                        >
                            <SelectTrigger id="plan-refund-account"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem
                                    v-for="account in data.accounts"
                                    :key="account.uuid"
                                    :value="account.uuid"
                                    >{{ account.name }}</SelectItem
                                ></SelectContent
                            >
                        </Select>
                        <InputError :message="errors.account_uuid" />
                    </div>
                    <div class="rounded-xl bg-muted p-3" data-refund-total>
                        <p class="text-sm">
                            {{ t('recurringDetail.planRefund.total') }}
                        </p>
                        <SensitiveValue
                            class="text-xl font-semibold"
                            :value="
                                formatCurrency(
                                    data.total_cents / 100,
                                    data.currency,
                                )
                            "
                        />
                    </div>
                    <label class="flex items-start gap-3 text-sm">
                        <input
                            v-model="form.irreversible"
                            type="checkbox"
                            class="mt-1 size-4 shrink-0"
                            :disabled="form.processing"
                        />
                        {{ t('recurringDetail.planRefund.irreversible') }}
                    </label>
                    <InputError :message="errors.irreversible" />
                    <InputError
                        :message="
                            errors.refund ||
                            errors.transaction ||
                            errors.request_uuid ||
                            errors.expected_total_cents
                        "
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="open = false"
                            >{{ t('app.common.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                !form.irreversible ||
                                !form.account_uuid ||
                                data.total_cents <= 0
                            "
                            >{{
                                t('recurringDetail.planRefund.action')
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
