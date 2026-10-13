<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    LoaderCircle,
    Pause,
    Pencil,
    Play,
    Receipt,
    ShieldCheck,
    Trash2,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    cancel,
    destroy,
    pause,
    resume,
    updateInstallmentDistribution,
} from '@/actions/App/Http/Controllers/RecurringEntryController';
import {
    convert as convertOccurrence,
    undoConversion,
    skip as skipOccurrence,
    restore as restoreOccurrence,
    updateAmount,
    update as updateOccurrence,
} from '@/actions/App/Http/Controllers/RecurringEntryOccurrenceController';
import { refund as refundTransaction } from '@/actions/App/Http/Controllers/RecurringEntryTransactionController';
import InputError from '@/components/InputError.vue';
import OccurrencePayments from '@/components/recurring/OccurrencePayments.vue';
import RecurringEntryFormSheet from '@/components/recurring/RecurringEntryFormSheet.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import TransactionFormSheet from '@/components/transactions/TransactionFormSheet.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/currency';
import type {
    Auth,
    BreadcrumbItem,
    RecurringEntryShowPageProps,
} from '@/types';

const ui = useUiCopy();

const props = defineProps<RecurringEntryShowPageProps>();
function toggleSkippedOccurrence(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
    if (!occurrence.can_skip && !occurrence.can_restore) {
        return;
    }

    const action = occurrence.can_restore ? restoreOccurrence : skipOccurrence;
    router.patch(
        action.url([props.recurringEntry.entry.uuid, occurrence.uuid]),
        {},
        { preserveScroll: true },
    );
}
const page = usePage();
const { t, locale } = useI18n();

const formOpen = ref(false);
const paymentFormOpen = ref(false);
const paymentLoading = ref(false);
const paymentOccurrence = ref<{
    uuid: string;
    account_uuid: string;
    category_uuid: string | null;
    tracked_item_uuid: string | null;
    direction: 'income' | 'expense';
    description: string;
    amount: string;
} | null>(null);
const paymentDate = computed(
    () => new Date(`${props.dateOptions.today}T12:00:00`),
);
function registerPayment(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
    if (!occurrence.can_register_payment || paymentLoading.value) {
        return;
    }

    paymentLoading.value = true;
    router.reload({
        only: ['transactionSheet'],
        onSuccess: () => {
            paymentOccurrence.value = {
                uuid: occurrence.uuid,
                account_uuid:
                    occurrence.resource?.uuid ??
                    entry.value.account?.uuid ??
                    '',
                category_uuid: entry.value.category?.uuid ?? null,
                tracked_item_uuid: entry.value.tracked_item?.uuid ?? null,
                direction: entry.value.direction as 'income' | 'expense',
                description: entry.value.title,
                amount: String(
                    occurrence.remaining_amount ??
                        occurrence.expected_amount ??
                        '',
                ),
            };
            paymentFormOpen.value = true;
        },
        onFinish: () => {
            paymentLoading.value = false;
        },
    });
}
const cancelDialogOpen = ref(false);
const resumeDialogOpen = ref(false);
const pauseDialogOpen = ref(false);
const pauseForm = useForm({ periods: 1 });
const pauseUnit = computed(() =>
    t(
        `transactions.recurring.pauseUnits.${entry.value.recurrence_type ?? 'custom'}`,
    ),
);
const deleteDialogOpen = ref(false);
const sharedPaymentOccurrence = ref<
    (typeof props.recurringEntry.occurrences)[number] | null
>(null);
const sharedPaymentForm = useForm({
    shared_expense_paid_by: 'self',
    amount: '',
    transaction_date: '',
    account_uuid: '',
    confirm_future_date: true,
});
function submitSharedPayment(): void {
    if (!sharedPaymentOccurrence.value) {
        return;
    }

    sharedPaymentForm.post(
        convertOccurrence.url([
            props.recurringEntry.entry.uuid,
            sharedPaymentOccurrence.value.uuid,
        ]),
        {
            preserveScroll: true,
            onSuccess: () => {
                sharedPaymentOccurrence.value = null;
            },
        },
    );
}
const convertDialogOccurrenceUuid = ref<string | null>(null);
const refundDialogTransactionUuid = ref<string | null>(null);
const undoConversionOccurrenceUuid = ref<string | null>(null);
const amountEditingOccurrenceUuid = ref<string | null>(null);
const amountForm = useForm({ amount: '' });
const installmentDistributionDialogOpen = ref(false);
const installmentDistributionConfirmOpen = ref(false);
const installmentAmounts = ref<Array<{ uuid: string; amount: string }>>([]);
const editingOccurrence = ref<
    (typeof props.recurringEntry.occurrences)[number] | null
>(null);
const occurrenceForm = useForm({ due_date: '', account_uuid: '' });

const auth = computed(() => page.props.auth as Auth);
const undoingExternalPayment = computed(() =>
    props.recurringEntry.occurrences.some(
        (occurrence) =>
            occurrence.uuid === undoConversionOccurrenceUuid.value &&
            occurrence.external_payment != null &&
            (!occurrence.converted_transaction ||
                occurrence.converted_transaction.is_refunded),
    ),
);
const entry = computed(() => props.recurringEntry.entry);
const highlightedOccurrenceUuid = computed(() => {
    const query = page.url.split('?')[1] ?? '';

    return new URLSearchParams(query).get('highlight');
});
const progressTotal = computed(() =>
    entry.value.entry_type === 'installment'
        ? (entry.value.installments_count ??
          props.recurringEntry.summary.total_occurrences)
        : props.recurringEntry.summary.total_occurrences,
);
const installmentDistributionTotalCents = computed(() =>
    installmentAmounts.value.reduce(
        (total, installment) =>
            total + Math.round(Number(installment.amount || 0) * 100),
        0,
    ),
);
const installmentDistributionDifferenceCents = computed(
    () =>
        Math.round(Number(entry.value.total_amount ?? 0) * 100) -
        installmentDistributionTotalCents.value,
);
const installmentDistributionIsValid = computed(
    () =>
        installmentDistributionDifferenceCents.value === 0 &&
        installmentAmounts.value.every(
            (installment) => Number(installment.amount) > 0,
        ),
);
const affectedConvertedInstallments = computed(
    () =>
        props.recurringEntry.occurrences.filter(
            (occurrence) => occurrence.converted_transaction !== null,
        ).length,
);
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('transactions.recurring.title'),
        href: '/recurring-entries',
    },
    {
        title: entry.value.title,
        href: entry.value.show_url,
    },
];

const summaryCards = computed(() => [
    {
        key: 'paid',
        label: t('transactions.recurring.labels.installmentPaid'),
        value:
            props.recurringEntry.summary.paid_year === null
                ? `${props.recurringEntry.summary.paid_count} / ${progressTotal.value}`
                : `${props.recurringEntry.summary.paid_count} · ${props.recurringEntry.summary.paid_year}`,
        tone: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        icon: ShieldCheck,
    },
    {
        key: 'pending',
        label: t('transactions.recurring.labels.pending'),
        value: props.recurringEntry.summary.pending_occurrences,
        tone: 'bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-200',
        icon: Receipt,
    },
    {
        key: 'progress',
        label:
            entry.value.entry_type === 'installment'
                ? t('transactions.recurring.labels.installmentProgress')
                : t('transactions.recurring.labels.recurringProgress'),
        value: `${props.recurringEntry.summary.settled_occurrences} / ${progressTotal.value}`,
        tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        icon: ShieldCheck,
    },
    {
        key: 'remaining',
        label: t('transactions.recurring.labels.plannedExpenses'),
        value: formatMoney(props.recurringEntry.summary.remaining_amount),
        tone: 'bg-rose-500/12 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        icon: Undo2,
    },
]);

function formatMoney(value: number, currency?: string | null): string {
    return formatCurrency(
        value,
        currency ??
            entry.value.currency ??
            auth.value.user?.base_currency_code ??
            'EUR',
        auth.value.user?.format_locale,
    );
}

function entryTypeLabel(value: string | null): string {
    return value
        ? t(`transactions.recurring.enums.entryType.${value}`)
        : t('app.common.notAvailable');
}

function directionLabel(value: string | null): string {
    return value
        ? t(`transactions.recurring.enums.direction.${value}`)
        : t('app.common.notAvailable');
}

function occurrenceState(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): {
    label: string;
    tone: string;
} {
    if (occurrence.external_payment?.settled) {
        return {
            label: `Pagata da ${occurrence.external_payment.payer ?? 'controparte'}`,
            tone: 'bg-primary/10 text-primary',
        };
    }

    if (occurrence.status === 'skipped') {
        return {
            label: t('transactions.recurring.enums.occurrenceStatus.skipped'),
            tone: 'bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-300',
        };
    }

    if (occurrence.settlement_status === 'due') {
        return {
            label: ui('Da regolarizzare'),
            tone: 'bg-primary/10 text-primary',
        };
    }

    if (occurrence.settlement_status === 'partial') {
        return {
            label: ui('Parzialmente saldata'),
            tone: 'bg-primary/10 text-primary',
        };
    }

    if (occurrence.settlement_status === 'amount_confirmation') {
        return {
            label: ui('Importo da confermare'),
            tone: 'bg-primary/10 text-primary',
        };
    }

    if (
        occurrence.settlement_status === 'settled' &&
        !occurrence.converted_transaction
    ) {
        return { label: 'Saldata', tone: 'bg-primary/10 text-primary' };
    }

    if (
        occurrence.status === 'refunded' ||
        occurrence.converted_transaction?.is_refunded
    ) {
        return {
            label: t('transactions.recurring.table.refundedBadge'),
            tone: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        };
    }

    if (occurrence.converted_transaction) {
        return {
            label: entry.value.shared_expense_counterparty
                ? 'Pagata da te'
                : t('transactions.recurring.table.convertedBadge'),
            tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        };
    }

    return {
        label: t('transactions.recurring.table.pendingBadge'),
        tone: 'bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-300',
    };
}

function handlePause(): void {
    if (entry.value.entry_type === 'recurring') {
        pauseForm.clearErrors();
        pauseDialogOpen.value = true;

        return;
    }

    router.patch(pause.url(entry.value.uuid), {}, { preserveScroll: true });
}

function confirmTemporaryPause(): void {
    pauseForm.patch(pause.url(entry.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            pauseDialogOpen.value = false;
        },
    });
}

function handleResume(): void {
    router.patch(
        resume.url(entry.value.uuid),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                resumeDialogOpen.value = false;
            },
        },
    );
}

function handleCancel(): void {
    router.patch(
        cancel.url(entry.value.uuid),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                cancelDialogOpen.value = false;
            },
        },
    );
}

function handleDelete(): void {
    router.delete(destroy.url(entry.value.uuid), {
        onFinish: () => {
            deleteDialogOpen.value = false;
        },
    });
}

function openInstallmentDistribution(): void {
    installmentAmounts.value = props.recurringEntry.occurrences.map(
        (occurrence) => ({
            uuid: occurrence.uuid,
            amount: String(occurrence.expected_amount ?? ''),
        }),
    );
    installmentDistributionDialogOpen.value = true;
}

function compensateLastInstallment(changedUuid: string): void {
    const target = installmentAmounts.value.at(-1);

    if (!target || target.uuid === changedUuid) {
        return;
    }

    const totalCents = Math.round(Number(entry.value.total_amount ?? 0) * 100);
    const otherCents = installmentAmounts.value
        .filter((installment) => installment.uuid !== target.uuid)
        .reduce(
            (total, installment) =>
                total + Math.round(Number(installment.amount || 0) * 100),
            0,
        );

    if (totalCents - otherCents > 0) {
        target.amount = ((totalCents - otherCents) / 100).toFixed(2);
    }
}

function distributionDifferenceLabel(): string {
    const difference = installmentDistributionDifferenceCents.value;

    if (difference === 0) {
        return ui('Differenza: €0,00');
    }

    const amount = formatMoney(Math.abs(difference) / 100);

    return difference > 0
        ? `Mancano ${amount} rispetto al totale del piano.`
        : ui(
              'Gli importi delle rate superano il totale del piano di {amount}.',
              { amount },
          );
}

function confirmInstallmentDistribution(): void {
    if (installmentDistributionIsValid.value) {
        installmentDistributionConfirmOpen.value = true;
    }
}

function saveInstallmentDistribution(): void {
    router.patch(
        updateInstallmentDistribution.url(entry.value.uuid),
        {
            installments: installmentAmounts.value.map((installment) => ({
                uuid: installment.uuid,
                amount: Number(installment.amount),
            })),
            confirm: true,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                installmentDistributionConfirmOpen.value = false;
                installmentDistributionDialogOpen.value = false;
            },
        },
    );
}

function handleConvert(occurrenceUuid: string): void {
    router.post(
        convertOccurrence.url([entry.value.uuid, occurrenceUuid]),
        {
            confirm_future_date: true,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                convertDialogOccurrenceUuid.value = null;
            },
        },
    );
}

function startAmountEdit(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
    if (!occurrence.can_update_amount) {
        return;
    }

    amountForm.clearErrors();
    amountForm.amount = String(occurrence.expected_amount ?? '');
    amountEditingOccurrenceUuid.value = occurrence.uuid;
}

function cancelAmountEdit(): void {
    amountEditingOccurrenceUuid.value = null;
    amountForm.reset();
    amountForm.clearErrors();
}

function submitAmount(occurrenceUuid: string): void {
    amountForm.patch(updateAmount.url([entry.value.uuid, occurrenceUuid]), {
        preserveScroll: true,
        onSuccess: cancelAmountEdit,
    });
}

function startOccurrenceEdit(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
    if (!occurrence.can_update_occurrence || !occurrence.resource) {
        return;
    }

    occurrenceForm.clearErrors();
    occurrenceForm.due_date = occurrenceDateValue(occurrence) ?? '';
    occurrenceForm.account_uuid = occurrence.resource.uuid;
    editingOccurrence.value = occurrence;
}

function cancelOccurrenceEdit(): void {
    editingOccurrence.value = null;
    occurrenceForm.reset();
    occurrenceForm.clearErrors();
}

function submitOccurrenceEdit(): void {
    if (!editingOccurrence.value) {
        return;
    }

    occurrenceForm.patch(
        updateOccurrence.url([entry.value.uuid, editingOccurrence.value.uuid]),
        {
            preserveScroll: true,
            onSuccess: cancelOccurrenceEdit,
        },
    );
}

function requestConvert(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
    if (
        entry.value.shared_expense_counterparty ||
        occurrence.converted_transaction?.is_refunded
    ) {
        sharedPaymentForm.clearErrors();
        sharedPaymentForm.shared_expense_paid_by = occurrence.external_payment
            ? 'counterparty'
            : (entry.value.shared_expense_paid_by ?? 'self');
        sharedPaymentForm.amount = String(
            occurrence.remaining_amount ?? occurrence.expected_amount ?? '',
        );
        sharedPaymentForm.transaction_date =
            occurrence.due_date ?? occurrence.expected_date ?? '';
        sharedPaymentForm.account_uuid =
            occurrence.resource?.uuid ?? entry.value.account?.uuid ?? '';
        sharedPaymentOccurrence.value = occurrence;

        return;
    }

    if (isFutureOccurrence(occurrence)) {
        convertDialogOccurrenceUuid.value = occurrence.uuid;

        return;
    }

    handleConvert(occurrence.uuid);
}

function handleRefund(): void {
    if (!refundDialogTransactionUuid.value) {
        return;
    }

    router.post(
        refundTransaction.url(refundDialogTransactionUuid.value),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                refundDialogTransactionUuid.value = null;
            },
        },
    );
}

function handleUndoConversion(): void {
    if (!undoConversionOccurrenceUuid.value) {
        return;
    }

    router.delete(
        undoConversion.url([
            entry.value.uuid,
            undoConversionOccurrenceUuid.value,
        ]),
        {
            preserveScroll: true,
            onFinish: () => {
                undoConversionOccurrenceUuid.value = null;
            },
        },
    );
}

function handleMobilePrimaryAction(event: Event): void {
    const customEvent = event as CustomEvent<{ kind?: string }>;

    if (customEvent.detail?.kind !== 'recurring') {
        return;
    }

    customEvent.preventDefault();
    formOpen.value = true;
}

onMounted(() => {
    window.addEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );

    void nextTick(() => {
        if (!highlightedOccurrenceUuid.value) {
            return;
        }

        const rows = Array.from(
            document.querySelectorAll<HTMLElement>(
                `[data-occurrence-uuid="${highlightedOccurrenceUuid.value}"]`,
            ),
        );
        const visibleRow = rows.find((row) => row.offsetParent !== null);

        visibleRow?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});

onBeforeUnmount(() => {
    window.removeEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );
});

function occurrenceDateValue(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): string | null {
    return occurrence.due_date ?? occurrence.expected_date;
}

function isFutureOccurrence(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): boolean {
    const dateValue = occurrenceDateValue(occurrence);

    if (!dateValue) {
        return false;
    }

    return dateValue > new Date().toISOString().slice(0, 10);
}
</script>

<template>
    <Head
        :title="`${entry.title} · ${t('transactions.recurring.detail.title')}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[30px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_36%),linear-gradient(135deg,rgba(255,255,255,0.97),rgba(248,250,252,0.93))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.12),_transparent_36%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.91))]"
            >
                <div
                    class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)] lg:p-7"
                >
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                as-child
                                variant="outline"
                                class="rounded-2xl"
                            >
                                <Link href="/recurring-entries">
                                    <ArrowLeft class="mr-2 size-4" />
                                    {{
                                        t(
                                            'transactions.recurring.actions.backToIndex',
                                        )
                                    }}
                                </Link>
                            </Button>
                            <Badge
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                {{ entryTypeLabel(entry.entry_type) }}
                            </Badge>
                            <Badge
                                class="rounded-full bg-white/80 px-3 py-1 text-slate-700 dark:bg-slate-950/70 dark:text-slate-200"
                            >
                                {{ directionLabel(entry.direction) }}
                            </Badge>
                            <Badge
                                data-testid="plan-posting-mode"
                                class="rounded-full bg-sky-500/12 px-3 py-1 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                            >
                                {{
                                    t(
                                        entry.auto_create_transaction
                                            ? 'transactions.recurring.detail.automaticMode'
                                            : 'transactions.recurring.detail.manualMode',
                                    )
                                }}
                            </Badge>
                            <Badge
                                v-if="entry.is_amount_variable"
                                class="rounded-full bg-violet-500/12 px-3 py-1 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                            >
                                {{
                                    t(
                                        'transactions.recurring.form.labels.variableAmount',
                                    )
                                }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <Link
                                v-if="entry.salary_url"
                                :href="entry.salary_url"
                                class="inline-flex rounded-xl border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 dark:border-emerald-800 dark:text-emerald-300"
                                >{{ t('salary.details') }}</Link
                            >
                            <h1
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ entry.title }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    entry.description ??
                                    t(
                                        'transactions.recurring.labels.noDescription',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="entry.can_edit"
                                class="rounded-2xl"
                                @click="formOpen = true"
                            >
                                <Pencil class="mr-2 size-4" />
                                {{ t('transactions.recurring.actions.edit') }}
                            </Button>
                            <Button
                                v-if="
                                    entry.can_edit &&
                                    entry.entry_type === 'installment'
                                "
                                variant="outline"
                                class="rounded-2xl"
                                @click="openInstallmentDistribution"
                            >
                                {{ ui('Modifica importi rate') }}
                            </Button>
                            <Button
                                v-if="props.recurringEntry.actions.can_pause"
                                variant="outline"
                                class="rounded-2xl"
                                @click="handlePause"
                            >
                                <Pause class="mr-2 size-4" />
                                {{ t('transactions.recurring.actions.pause') }}
                            </Button>
                            <Button
                                v-if="props.recurringEntry.actions.can_resume"
                                variant="outline"
                                class="rounded-2xl"
                                @click="resumeDialogOpen = true"
                            >
                                <Play class="mr-2 size-4" />
                                {{ t('transactions.recurring.actions.resume') }}
                            </Button>
                            <Button
                                v-if="props.recurringEntry.actions.can_cancel"
                                variant="outline"
                                class="rounded-2xl"
                                @click="cancelDialogOpen = true"
                            >
                                {{ t('transactions.recurring.actions.cancel') }}
                            </Button>
                            <Button
                                v-if="props.recurringEntry.actions.can_delete"
                                variant="destructive"
                                class="rounded-2xl"
                                @click="deleteDialogOpen = true"
                            >
                                <Trash2 class="mr-2 size-4" />
                                {{ t('transactions.recurring.actions.delete') }}
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.key"
                            class="rounded-[24px] border border-white/70 bg-white/82 p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/70"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p
                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{ card.label }}
                                    </p>
                                    <p
                                        class="text-2xl font-semibold text-slate-950 dark:text-white"
                                    >
                                        <SensitiveValue
                                            v-if="card.key === 'remaining'"
                                            variant="veil"
                                            :value="card.value"
                                        />
                                        <template v-else>
                                            {{ card.value }}
                                        </template>
                                    </p>
                                    <p
                                        v-if="card.key === 'paid'"
                                        class="text-sm text-muted-foreground"
                                    >
                                        <SensitiveValue
                                            variant="veil"
                                            :value="
                                                formatMoney(
                                                    recurringEntry.summary
                                                        .paid_amount,
                                                )
                                            "
                                        />
                                    </p>
                                </div>
                                <div
                                    class="flex size-10 items-center justify-center rounded-2xl"
                                    :class="card.tone"
                                >
                                    <component :is="card.icon" class="size-5" />
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section
                v-if="recurringEntry.amount_history?.length"
                data-testid="plan-amount-history"
                class="space-y-3 rounded-2xl border bg-muted/30 p-4"
            >
                <h2 class="text-base font-semibold">
                    {{ t('transactions.recurring.detail.amountHistory') }}
                </h2>
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                >
                    <SensitiveValue
                        :value="
                            formatCurrency(
                                recurringEntry.amount_history[0]
                                    .previous_amount,
                                recurringEntry.amount_history[0].currency,
                            )
                        "
                    />
                    <span>{{
                        t('transactions.recurring.detail.amountUntil', {
                            date: new Date(
                                `${recurringEntry.amount_history[0].previous_until}T00:00:00`,
                            ).toLocaleDateString(locale),
                        })
                    }}</span>
                </div>
                <div
                    v-for="change in recurringEntry.amount_history"
                    :key="change.id"
                    class="flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-sm"
                >
                    <div class="space-y-1">
                        <SensitiveValue
                            class="font-semibold"
                            :value="
                                formatCurrency(
                                    change.new_amount,
                                    change.currency,
                                )
                            "
                        />
                        <p class="text-muted-foreground">
                            {{
                                t('transactions.recurring.detail.amountFrom', {
                                    date: new Date(
                                        `${change.effective_from}T00:00:00`,
                                    ).toLocaleDateString(locale),
                                })
                            }}
                        </p>
                    </div>
                    <Badge variant="secondary" class="gap-1 tabular-nums">
                        <SensitiveValue
                            :value="`${change.difference > 0 ? '+' : ''}${formatCurrency(change.difference, change.currency)}`"
                        />
                        <template v-if="change.percentage !== null">
                            ·
                            <SensitiveValue
                                :value="`${change.percentage > 0 ? '+' : ''}${change.percentage.toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`"
                            />
                        </template>
                    </Badge>
                </div>
            </section>

            <section
                v-if="entry.entry_type === 'recurring' && entry.trial_duration"
                class="grid gap-2 rounded-2xl border bg-muted/30 p-4 sm:grid-cols-2"
            >
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ ui('Prova gratuita') }}
                    </p>
                    <p class="font-semibold">
                        {{ entry.trial_duration }}
                        {{
                            entry.trial_unit === 'months'
                                ? ui('mesi')
                                : entry.trial_unit === 'weeks'
                                  ? ui('settimane')
                                  : ui('giorni')
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ ui('Primo addebito') }}
                    </p>
                    <p class="font-semibold">
                        {{
                            entry.first_billing_date
                                ? new Date(
                                      `${entry.first_billing_date}T00:00:00`,
                                  ).toLocaleDateString(locale, {
                                      day: 'numeric',
                                      month: 'long',
                                      year: 'numeric',
                                  })
                                : '—'
                        }}
                    </p>
                </div>
                <p class="text-sm text-primary sm:col-span-2">
                    {{
                        entry.trial_active
                            ? ui(
                                  'Prova gratuita in corso: la parte a pagamento non è ancora iniziata.',
                              )
                            : ui('Periodo di prova terminato.')
                    }}
                </p>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/92 shadow-sm dark:border-white/10 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 p-5 lg:p-6 dark:border-white/10"
                >
                    <h2
                        class="text-xl font-semibold text-slate-950 dark:text-white"
                    >
                        {{ t('transactions.recurring.detail.occurrences') }}
                    </h2>
                </div>

                <div class="space-y-4 p-4 sm:hidden">
                    <article
                        v-for="occurrence in props.recurringEntry.occurrences"
                        :key="occurrence.uuid"
                        :data-occurrence-uuid="occurrence.uuid"
                        class="space-y-4 rounded-[24px] border bg-white/90 p-4 transition-all dark:bg-slate-950/70"
                        :class="
                            highlightedOccurrenceUuid === occurrence.uuid
                                ? 'border-violet-400 ring-2 ring-violet-300/60 dark:border-violet-500 dark:ring-violet-500/25'
                                : 'border-slate-200/80 dark:border-white/10'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-white"
                                >
                                    {{
                                        occurrence.due_date ??
                                        occurrence.expected_date
                                    }}
                                </p>
                                <Badge
                                    class="rounded-full"
                                    :class="occurrenceState(occurrence).tone"
                                >
                                    {{ occurrenceState(occurrence).label }}
                                </Badge>
                            </div>
                            <form
                                v-if="
                                    amountEditingOccurrenceUuid ===
                                    occurrence.uuid
                                "
                                class="w-40 space-y-2"
                                @submit.prevent="submitAmount(occurrence.uuid)"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        v-model="amountForm.amount"
                                        type="number"
                                        inputmode="decimal"
                                        min="0.01"
                                        step="0.01"
                                        autofocus
                                        class="h-9 rounded-xl text-right"
                                    />
                                    <Button
                                        type="submit"
                                        size="icon"
                                        class="size-9 shrink-0 rounded-xl"
                                        :disabled="amountForm.processing"
                                        :aria-label="
                                            t(
                                                'transactions.recurring.actions.saveAmount',
                                            )
                                        "
                                    >
                                        <LoaderCircle
                                            v-if="amountForm.processing"
                                            class="size-4 animate-spin"
                                        />
                                        <Check v-else class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-9 shrink-0 rounded-xl"
                                        :aria-label="
                                            t(
                                                'transactions.recurring.actions.cancelAmountEdit',
                                            )
                                        "
                                        @click="cancelAmountEdit"
                                    >
                                        <X class="size-4" />
                                    </Button>
                                </div>
                                <InputError
                                    :message="amountForm.errors.amount"
                                />
                            </form>
                            <div v-else class="flex items-center gap-1.5">
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-white"
                                >
                                    <SensitiveValue
                                        :value="
                                            formatMoney(
                                                occurrence.expected_amount ?? 0,
                                            )
                                        "
                                    />
                                </p>
                                <Button
                                    v-if="occurrence.can_update_amount"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 rounded-xl text-violet-700 dark:text-violet-300"
                                    :aria-label="
                                        t(
                                            'transactions.recurring.actions.editAmount',
                                        )
                                    "
                                    @click="startAmountEdit(occurrence)"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                            </div>
                        </div>

                        <dl
                            v-if="(occurrence.allocated_amount ?? 0) > 0"
                            class="grid min-w-0 grid-cols-3 gap-2 rounded-xl bg-muted/50 p-3 text-xs"
                        >
                            <div
                                v-for="(amount, label) in {
                                    amountDue: occurrence.expected_amount,
                                    amountPaid: occurrence.allocated_amount,
                                    amountRemaining:
                                        occurrence.remaining_amount,
                                }"
                                :key="label"
                                class="min-w-0"
                            >
                                <dt class="text-muted-foreground">
                                    {{
                                        t(
                                            `transactions.recurring.labels.${label}`,
                                        )
                                    }}
                                </dt>
                                <dd class="mt-1 font-semibold tabular-nums">
                                    <SensitiveValue
                                        :value="formatMoney(amount ?? 0)"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <div class="space-y-1 text-sm">
                            <p class="text-slate-700 dark:text-slate-200">
                                <span
                                    class="text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.account',
                                        )
                                    }}:
                                </span>
                                {{
                                    occurrence.resource?.name ??
                                    t('transactions.recurring.labels.noAccount')
                                }}
                                <span
                                    v-if="occurrence.resource?.is_overridden"
                                    class="ml-1 text-xs text-violet-700 dark:text-violet-300"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.table.overrideBadge',
                                        )
                                    }}
                                </span>
                            </p>
                            <p
                                v-if="occurrence.has_date_override"
                                class="text-xs text-violet-700 dark:text-violet-300"
                            >
                                {{
                                    t(
                                        'transactions.recurring.table.overrideBadge',
                                    )
                                }}
                            </p>
                            <p
                                v-if="
                                    occurrence.converted_transaction?.show_url
                                "
                                class="text-slate-600 dark:text-slate-300"
                            >
                                <Link
                                    :href="
                                        occurrence.converted_transaction
                                            .show_url
                                    "
                                    class="font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.actions.openTransaction',
                                        )
                                    }}
                                </Link>
                            </p>
                            <p
                                v-if="
                                    occurrence.converted_transaction
                                        ?.refund_transaction?.transaction_date
                                "
                                class="text-xs text-amber-700 dark:text-amber-300"
                            >
                                {{
                                    t(
                                        'transactions.recurring.table.refundReference',
                                        {
                                            date: occurrence
                                                .converted_transaction
                                                .refund_transaction
                                                .transaction_date,
                                        },
                                    )
                                }}
                            </p>
                            <p
                                v-if="
                                    !occurrence.converted_transaction &&
                                    !occurrence.external_payment
                                "
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.recurring.table.noLinkedTransaction',
                                    )
                                }}
                            </p>
                        </div>

                        <OccurrencePayments
                            :payments="occurrence.payments"
                            :external-payment="occurrence.external_payment"
                            :history="occurrence.payment_history"
                            :currency="entry.currency"
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="occurrence.can_register_payment"
                                variant="outline"
                                class="h-11 rounded-full px-3 text-xs"
                                :disabled="paymentLoading"
                                @click="registerPayment(occurrence)"
                                >{{ ui('Registra pagamento') }}</Button
                            >
                            <Button
                                v-if="occurrence.can_update_occurrence"
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="startOccurrenceEdit(occurrence)"
                            >
                                <Pencil class="mr-1.5 size-3.5" />
                                {{
                                    t(
                                        'transactions.recurring.actions.editOccurrence',
                                    )
                                }}
                            </Button>
                            <Button
                                v-if="
                                    occurrence.can_skip ||
                                    occurrence.can_restore
                                "
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="toggleSkippedOccurrence(occurrence)"
                            >
                                {{
                                    t(
                                        occurrence.can_restore
                                            ? 'transactions.recurring.actions.restoreOccurrence'
                                            : 'transactions.recurring.actions.skipOccurrence',
                                    )
                                }}
                            </Button>
                            <Button
                                v-if="
                                    occurrence.can_convert &&
                                    occurrence.status !== 'skipped'
                                "
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="requestConvert(occurrence)"
                            >
                                {{
                                    entry.shared_expense_counterparty
                                        ? 'Registra pagamento'
                                        : t(
                                              'transactions.recurring.actions.convert',
                                          )
                                }}
                            </Button>
                            <Button
                                v-if="occurrence.can_undo_conversion"
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="
                                    undoConversionOccurrenceUuid =
                                        occurrence.uuid
                                "
                            >
                                {{
                                    occurrence.external_payment &&
                                    (!occurrence.converted_transaction ||
                                        occurrence.converted_transaction
                                            .is_refunded)
                                        ? 'Annulla pagamento'
                                        : t(
                                              'transactions.recurring.actions.undoConversion',
                                          )
                                }}
                            </Button>
                            <Button
                                v-if="
                                    occurrence.converted_transaction?.can_refund
                                "
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="
                                    refundDialogTransactionUuid =
                                        occurrence.converted_transaction.uuid
                                "
                            >
                                {{ t('transactions.recurring.actions.refund') }}
                            </Button>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto sm:block">
                    <table class="min-w-full text-sm">
                        <thead
                            class="bg-slate-950/[0.03] text-left text-xs tracking-[0.16em] text-slate-500 uppercase dark:bg-white/[0.04] dark:text-slate-400"
                        >
                            <tr>
                                <th class="px-4 py-3">
                                    {{
                                        t('transactions.recurring.labels.date')
                                    }}
                                </th>
                                <th class="px-4 py-3">
                                    {{
                                        t(
                                            'transactions.recurring.labels.amount',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3">
                                    {{
                                        t(
                                            'transactions.recurring.labels.account',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3">
                                    {{
                                        t(
                                            'transactions.recurring.labels.occurrenceStatus',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3">
                                    {{
                                        t(
                                            'transactions.recurring.labels.convertedTransaction',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3 text-right">
                                    {{
                                        t(
                                            'transactions.recurring.labels.actions',
                                        )
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="occurrence in props.recurringEntry
                                    .occurrences"
                                :key="occurrence.uuid"
                                :data-occurrence-uuid="occurrence.uuid"
                                class="border-t transition-colors"
                                :class="
                                    highlightedOccurrenceUuid ===
                                    occurrence.uuid
                                        ? 'border-violet-300 bg-violet-50/75 dark:border-violet-500/40 dark:bg-violet-500/10'
                                        : 'border-slate-200/70 dark:border-white/10'
                                "
                            >
                                <td
                                    class="px-4 py-4 text-slate-700 dark:text-slate-200"
                                >
                                    <div class="space-y-1">
                                        <p>
                                            {{
                                                occurrence.due_date ??
                                                occurrence.expected_date
                                            }}
                                        </p>
                                        <p
                                            v-if="occurrence.has_date_override"
                                            class="text-xs text-violet-700 dark:text-violet-300"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.table.overrideBadge',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-4 font-semibold text-slate-950 dark:text-white"
                                >
                                    <form
                                        v-if="
                                            amountEditingOccurrenceUuid ===
                                            occurrence.uuid
                                        "
                                        class="max-w-64 space-y-2"
                                        @submit.prevent="
                                            submitAmount(occurrence.uuid)
                                        "
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Input
                                                v-model="amountForm.amount"
                                                type="number"
                                                inputmode="decimal"
                                                min="0.01"
                                                step="0.01"
                                                autofocus
                                                class="h-9 w-32 rounded-xl text-right"
                                            />
                                            <Button
                                                type="submit"
                                                size="icon"
                                                class="size-9 rounded-xl"
                                                :disabled="
                                                    amountForm.processing
                                                "
                                                :aria-label="
                                                    t(
                                                        'transactions.recurring.actions.saveAmount',
                                                    )
                                                "
                                            >
                                                <LoaderCircle
                                                    v-if="amountForm.processing"
                                                    class="size-4 animate-spin"
                                                />
                                                <Check v-else class="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                class="size-9 rounded-xl"
                                                :aria-label="
                                                    t(
                                                        'transactions.recurring.actions.cancelAmountEdit',
                                                    )
                                                "
                                                @click="cancelAmountEdit"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </div>
                                        <InputError
                                            :message="amountForm.errors.amount"
                                        />
                                    </form>
                                    <div
                                        v-else
                                        class="flex items-center gap-1.5"
                                    >
                                        <SensitiveValue
                                            :value="
                                                formatMoney(
                                                    occurrence.expected_amount ??
                                                        0,
                                                )
                                            "
                                        />
                                        <Button
                                            v-if="occurrence.can_update_amount"
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            class="size-8 rounded-xl text-violet-700 dark:text-violet-300"
                                            :aria-label="
                                                t(
                                                    'transactions.recurring.actions.editAmount',
                                                )
                                            "
                                            @click="startAmountEdit(occurrence)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>
                                    </div>
                                    <dl
                                        v-if="
                                            (occurrence.allocated_amount ?? 0) >
                                            0
                                        "
                                        class="mt-2 space-y-1 text-xs"
                                    >
                                        <div
                                            v-for="(amount, label) in {
                                                amountDue:
                                                    occurrence.expected_amount,
                                                amountPaid:
                                                    occurrence.allocated_amount,
                                                amountRemaining:
                                                    occurrence.remaining_amount,
                                            }"
                                            :key="label"
                                            class="flex gap-2"
                                        >
                                            <dt class="text-muted-foreground">
                                                {{
                                                    t(
                                                        `transactions.recurring.labels.${label}`,
                                                    )
                                                }}
                                            </dt>
                                            <dd
                                                class="font-semibold tabular-nums"
                                            >
                                                <SensitiveValue
                                                    :value="
                                                        formatMoney(amount ?? 0)
                                                    "
                                                />
                                            </dd>
                                        </div>
                                    </dl>
                                </td>
                                <td
                                    class="px-4 py-4 text-slate-700 dark:text-slate-200"
                                >
                                    <div class="space-y-1">
                                        <p>
                                            {{
                                                occurrence.resource?.name ??
                                                t(
                                                    'transactions.recurring.labels.noAccount',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                occurrence.resource
                                                    ?.is_overridden
                                            "
                                            class="text-xs text-violet-700 dark:text-violet-300"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.table.overrideBadge',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            class="rounded-full"
                                            :class="
                                                occurrenceState(occurrence).tone
                                            "
                                        >
                                            {{
                                                occurrenceState(occurrence)
                                                    .label
                                            }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div
                                        v-if="occurrence.converted_transaction"
                                        class="space-y-1"
                                    >
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                occurrence.converted_transaction
                                                    .transaction_date
                                            }}
                                        </p>
                                        <Link
                                            v-if="
                                                occurrence.converted_transaction
                                                    .show_url
                                            "
                                            :href="
                                                occurrence.converted_transaction
                                                    .show_url
                                            "
                                            class="inline-flex text-xs font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.actions.openTransaction',
                                                )
                                            }}
                                        </Link>
                                        <p
                                            v-if="
                                                occurrence.converted_transaction
                                                    .refund_transaction
                                                    ?.transaction_date
                                            "
                                            class="text-xs text-amber-700 dark:text-amber-300"
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.table.refundReference',
                                                    {
                                                        date: occurrence
                                                            .converted_transaction
                                                            .refund_transaction
                                                            .transaction_date,
                                                    },
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'transactions.recurring.table.noLinkedTransaction',
                                            )
                                        }}
                                    </span>
                                    <OccurrencePayments
                                        :history="occurrence.payment_history"
                                        :payments="occurrence.payments"
                                        :external-payment="
                                            occurrence.external_payment
                                        "
                                        :currency="entry.currency"
                                    />
                                </td>
                                <td class="px-4 py-4">
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <Button
                                            v-if="
                                                occurrence.can_register_payment
                                            "
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            :disabled="paymentLoading"
                                            @click="registerPayment(occurrence)"
                                            >{{
                                                ui('Registra pagamento')
                                            }}</Button
                                        >
                                        <Button
                                            v-if="
                                                occurrence.can_update_occurrence
                                            "
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            @click="
                                                startOccurrenceEdit(occurrence)
                                            "
                                        >
                                            <Pencil class="mr-1.5 size-3.5" />
                                            {{
                                                t(
                                                    'transactions.recurring.actions.editOccurrence',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="
                                                occurrence.can_skip ||
                                                occurrence.can_restore
                                            "
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            @click="
                                                toggleSkippedOccurrence(
                                                    occurrence,
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    occurrence.can_restore
                                                        ? 'transactions.recurring.actions.restoreOccurrence'
                                                        : 'transactions.recurring.actions.skipOccurrence',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="
                                                occurrence.can_convert &&
                                                occurrence.status !== 'skipped'
                                            "
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            @click="requestConvert(occurrence)"
                                        >
                                            {{
                                                entry.shared_expense_counterparty
                                                    ? 'Registra pagamento'
                                                    : t(
                                                          'transactions.recurring.actions.convert',
                                                      )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="
                                                occurrence.can_undo_conversion
                                            "
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            @click="
                                                undoConversionOccurrenceUuid =
                                                    occurrence.uuid
                                            "
                                        >
                                            {{
                                                occurrence.external_payment &&
                                                (!occurrence.converted_transaction ||
                                                    occurrence
                                                        .converted_transaction
                                                        .is_refunded)
                                                    ? 'Annulla pagamento'
                                                    : t(
                                                          'transactions.recurring.actions.undoConversion',
                                                      )
                                            }}
                                        </Button>
                                        <Button
                                            v-if="
                                                occurrence.converted_transaction
                                                    ?.can_refund
                                            "
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            @click="
                                                refundDialogTransactionUuid =
                                                    occurrence
                                                        .converted_transaction
                                                        .uuid
                                            "
                                        >
                                            {{
                                                t(
                                                    'transactions.recurring.actions.refund',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <RecurringEntryFormSheet
            v-model:open="formOpen"
            :entry="entry"
            :form-options="props.formOptions"
            :date-options="props.dateOptions"
            :default-start-date="
                entry.start_date ?? new Date().toISOString().slice(0, 10)
            "
            :show-start-month-selector="false"
            @saved="formOpen = false"
        />

        <Dialog
            :open="editingOccurrence !== null"
            @update:open="(open) => !open && cancelOccurrenceEdit()"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.dialogs.editOccurrenceTitle')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'transactions.recurring.dialogs.editOccurrenceDescription',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitOccurrenceEdit">
                    <label class="block space-y-2 text-sm font-medium">
                        <span>{{
                            t('transactions.recurring.labels.date')
                        }}</span>
                        <Input v-model="occurrenceForm.due_date" type="date" />
                        <InputError :message="occurrenceForm.errors.due_date" />
                    </label>
                    <label class="block space-y-2 text-sm font-medium">
                        <span>{{
                            t('transactions.recurring.labels.account')
                        }}</span>
                        <Select v-model="occurrenceForm.account_uuid">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in props.formOptions
                                        .accounts"
                                    :key="account.value"
                                    :value="account.value"
                                    >{{ account.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="occurrenceForm.errors.account_uuid"
                        />
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="cancelOccurrenceEdit"
                        >
                            {{ t('app.common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="occurrenceForm.processing"
                        >
                            {{ t('app.common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="pauseDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.actions.pause')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'transactions.recurring.dialogs.temporaryPauseDescription',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="confirmTemporaryPause" class="space-y-4">
                    <label for="pause-periods">{{
                        t('transactions.recurring.dialogs.pauseFor')
                    }}</label>
                    <div class="flex items-center gap-3">
                        <Input
                            id="pause-periods"
                            v-model="pauseForm.periods"
                            type="number"
                            min="1"
                            max="1000"
                            step="1"
                            required
                        />
                        <span>{{ pauseUnit }}</span>
                    </div>
                    <InputError :message="pauseForm.errors.periods" />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="pauseDialogOpen = false"
                            >{{
                                t('transactions.recurring.actions.cancel')
                            }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="pauseForm.processing"
                            >{{
                                t('transactions.recurring.actions.pause')
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="resumeDialogOpen">
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>{{
                        t('transactions.recurring.dialogs.resumeTitle')
                    }}</DialogTitle
                    ><DialogDescription>{{
                        t('transactions.recurring.dialogs.resumeDescription')
                    }}</DialogDescription></DialogHeader
                >
                <DialogFooter
                    ><Button
                        variant="outline"
                        @click="resumeDialogOpen = false"
                        >{{ t('app.common.cancel') }}</Button
                    ><Button @click="handleResume"
                        ><Play class="mr-2 size-4" />{{
                            t('transactions.recurring.actions.resume')
                        }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
        <Dialog v-model:open="cancelDialogOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.dialogs.cancelTitle')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'transactions.recurring.dialogs.cancelDescription',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        @click="cancelDialogOpen = false"
                    >
                        {{ t('app.common.cancel') }}
                    </Button>
                    <Button class="rounded-xl" @click="handleCancel">
                        {{ t('transactions.recurring.actions.cancel') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.dialogs.deleteTitle')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'transactions.recurring.dialogs.deleteDescription',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        @click="deleteDialogOpen = false"
                    >
                        {{ t('app.common.cancel') }}
                    </Button>
                    <Button
                        variant="destructive"
                        class="rounded-xl"
                        @click="handleDelete"
                    >
                        {{ t('transactions.recurring.actions.delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="sharedPaymentOccurrence !== null"
            @update:open="
                (value) => {
                    if (!value) sharedPaymentOccurrence = null;
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Registra pagamento</DialogTitle>
                    <DialogDescription
                        >Indica chi ha pagato questa scadenza. L’anticipo della
                        controparte viene registrato nelle Spese
                        condivise.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSharedPayment">
                    <fieldset
                        v-if="entry.shared_expense_counterparty"
                        class="space-y-2"
                    >
                        <legend class="text-sm font-medium">Pagato da</legend>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                :variant="
                                    sharedPaymentForm.shared_expense_paid_by ===
                                    'self'
                                        ? 'default'
                                        : 'outline'
                                "
                                :aria-pressed="
                                    sharedPaymentForm.shared_expense_paid_by ===
                                    'self'
                                "
                                @click="
                                    sharedPaymentForm.shared_expense_paid_by =
                                        'self'
                                "
                                >Io</Button
                            >
                            <Button
                                type="button"
                                :variant="
                                    sharedPaymentForm.shared_expense_paid_by ===
                                    'counterparty'
                                        ? 'default'
                                        : 'outline'
                                "
                                :aria-pressed="
                                    sharedPaymentForm.shared_expense_paid_by ===
                                    'counterparty'
                                "
                                @click="
                                    sharedPaymentForm.shared_expense_paid_by =
                                        'counterparty'
                                "
                                >{{
                                    entry.shared_expense_counterparty?.name
                                }}</Button
                            >
                        </div>
                    </fieldset>
                    <div class="space-y-2">
                        <label
                            for="shared-occurrence-amount"
                            class="text-sm font-medium"
                            >Importo pagato</label
                        >
                        <Input
                            id="shared-occurrence-amount"
                            v-model="sharedPaymentForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                        />
                        <InputError
                            :message="sharedPaymentForm.errors.amount"
                        />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="shared-occurrence-date"
                            class="text-sm font-medium"
                            >Data del pagamento</label
                        >
                        <Input
                            id="shared-occurrence-date"
                            v-model="sharedPaymentForm.transaction_date"
                            type="date"
                            required
                        />
                        <InputError
                            :message="sharedPaymentForm.errors.transaction_date"
                        />
                    </div>
                    <div
                        v-if="
                            sharedPaymentForm.shared_expense_paid_by === 'self'
                        "
                        class="space-y-2"
                    >
                        <label
                            for="recurring-payment-account"
                            class="text-sm font-medium"
                            >Conto del pagamento</label
                        >
                        <Select v-model="sharedPaymentForm.account_uuid">
                            <SelectTrigger id="recurring-payment-account"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in props.formOptions
                                        .accounts"
                                    :key="account.value"
                                    :value="account.value"
                                    >{{ account.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="sharedPaymentForm.errors.account_uuid"
                        />
                    </div>
                    <InputError
                        :message="
                            sharedPaymentForm.errors.shared_expense_paid_by
                        "
                    />
                    <InputError
                        :message="
                            (sharedPaymentForm.errors as Record<string, string>)
                                .occurrence
                        "
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="sharedPaymentOccurrence = null"
                            >Annulla</Button
                        >
                        <Button
                            type="submit"
                            :disabled="sharedPaymentForm.processing"
                            >Salva pagamento</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="convertDialogOccurrenceUuid !== null"
            @update:open="
                (value) => {
                    if (!value) convertDialogOccurrenceUuid = null;
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.dialogs.convertFutureTitle')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'transactions.recurring.dialogs.convertFutureDescription',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        @click="convertDialogOccurrenceUuid = null"
                    >
                        {{ t('app.common.cancel') }}
                    </Button>
                    <Button
                        class="rounded-xl"
                        @click="handleConvert(convertDialogOccurrenceUuid!)"
                    >
                        {{
                            entry.shared_expense_counterparty
                                ? 'Registra pagamento'
                                : t('transactions.recurring.actions.convert')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="undoConversionOccurrenceUuid !== null"
            @update:open="
                (value) => {
                    if (!value) undoConversionOccurrenceUuid = null;
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        undoingExternalPayment
                            ? 'Annulla pagamento della controparte'
                            : t(
                                  'transactions.recurring.dialogs.undoConversionTitle',
                              )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            undoingExternalPayment
                                ? 'Il movimento condiviso verrà eliminato e la scadenza tornerà da pagare.'
                                : t(
                                      'transactions.recurring.dialogs.undoConversionDescription',
                                  )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        @click="undoConversionOccurrenceUuid = null"
                    >
                        {{ t('app.common.cancel') }}
                    </Button>
                    <Button class="rounded-xl" @click="handleUndoConversion">
                        {{
                            undoingExternalPayment
                                ? 'Annulla pagamento'
                                : t(
                                      'transactions.recurring.actions.undoConversion',
                                  )
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="refundDialogTransactionUuid !== null"
            @update:open="
                (value) => {
                    if (!value) refundDialogTransactionUuid = null;
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('transactions.recurring.dialogs.refundTitle')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'transactions.recurring.dialogs.refundDescription',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        @click="refundDialogTransactionUuid = null"
                    >
                        {{ t('app.common.cancel') }}
                    </Button>
                    <Button class="rounded-xl" @click="handleRefund">
                        {{ t('transactions.recurring.actions.refund') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="installmentDistributionDialogOpen">
            <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader class="space-y-2 text-left">
                    <DialogTitle>{{ ui('Modifica importi rate') }}</DialogTitle>
                    <DialogDescription>{{
                        ui(
                            'Personalizza la distribuzione senza modificare il totale del piano.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <div
                    class="grid grid-cols-3 gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <span
                        >{{ ui('Totale piano') }}<br /><strong>{{
                            formatMoney(Number(entry.total_amount ?? 0))
                        }}</strong></span
                    >
                    <span
                        >{{ ui('Totale rate') }}<br /><strong>{{
                            formatMoney(installmentDistributionTotalCents / 100)
                        }}</strong></span
                    >
                    <span
                        :class="
                            installmentDistributionIsValid
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : 'text-rose-700 dark:text-rose-300'
                        "
                        >{{ distributionDifferenceLabel() }}</span
                    >
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(installment, index) in installmentAmounts"
                        :key="installment.uuid"
                        class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-800"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">
                                {{ ui('Rata') }} {{ index + 1 }}
                            </p>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    occurrenceState(
                                        props.recurringEntry.occurrences[index],
                                    ).label
                                }}
                                ·
                                {{
                                    occurrenceDateValue(
                                        props.recurringEntry.occurrences[index],
                                    )
                                }}
                            </p>
                        </div>
                        <Input
                            v-model="installment.amount"
                            inputmode="decimal"
                            class="w-28 text-right"
                            @update:model-value="
                                compensateLastInstallment(installment.uuid)
                            "
                        />
                    </div>
                </div>
                <DialogFooter class="gap-2"
                    ><Button
                        variant="outline"
                        @click="installmentDistributionDialogOpen = false"
                        >{{ ui('Annulla') }}</Button
                    ><Button
                        :disabled="!installmentDistributionIsValid"
                        @click="confirmInstallmentDistribution"
                        >{{ ui('Conferma nuovi importi') }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="installmentDistributionConfirmOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader class="space-y-3 text-left"
                    ><DialogTitle>{{
                        ui('Conferma nuovi importi')
                    }}</DialogTitle
                    ><DialogDescription
                        >{{ ui('Totale rate:') }}
                        {{ formatMoney(installmentDistributionTotalCents / 100)
                        }}{{ ui('. Differenza: €0,00.') }}</DialogDescription
                    ></DialogHeader
                >
                <div
                    class="space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-50"
                >
                    <p>
                        {{
                            ui(
                                'Le rate e le eventuali transazioni collegate saranno aggiornate in modo atomico.',
                            )
                        }}
                    </p>
                    <p v-if="affectedConvertedInstallments > 0">
                        {{ ui('Stai modificando anche') }}
                        {{ affectedConvertedInstallments }}
                        {{
                            ui(
                                'rate già registrate: le transazioni resteranno collegate e conserveranno tutti i dati diversi dall’importo.',
                            )
                        }}
                    </p>
                </div>
                <DialogFooter class="gap-2"
                    ><Button
                        variant="outline"
                        @click="installmentDistributionConfirmOpen = false"
                        >{{ ui('Annulla') }}</Button
                    ><Button @click="saveInstallmentDistribution">{{
                        ui('Conferma nuovi importi')
                    }}</Button></DialogFooter
                >
            </DialogContent>
        </Dialog>
        <TransactionFormSheet
            v-if="props.transactionSheet"
            v-model:open="paymentFormOpen"
            :year="paymentDate.getFullYear()"
            :month="paymentDate.getMonth() + 1"
            :sheet="props.transactionSheet"
            :payment-occurrence="paymentOccurrence"
        />
    </AppLayout>
</template>
