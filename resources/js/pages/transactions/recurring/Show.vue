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
} from '@/actions/App/Http/Controllers/RecurringEntryController';
import {
    convert as convertOccurrence,
    updateAmount,
} from '@/actions/App/Http/Controllers/RecurringEntryOccurrenceController';
import { refund as refundTransaction } from '@/actions/App/Http/Controllers/RecurringEntryTransactionController';
import InputError from '@/components/InputError.vue';
import RecurringEntryFormSheet from '@/components/recurring/RecurringEntryFormSheet.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency } from '@/lib/currency';
import type {
    Auth,
    BreadcrumbItem,
    RecurringEntryShowPageProps,
} from '@/types';

const props = defineProps<RecurringEntryShowPageProps>();
const page = usePage();
const { t } = useI18n();

const formOpen = ref(false);
const cancelDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const convertDialogOccurrenceUuid = ref<string | null>(null);
const refundDialogTransactionUuid = ref<string | null>(null);
const undoConversionOccurrenceUuid = ref<string | null>(null);
const amountEditingOccurrenceUuid = ref<string | null>(null);
const amountForm = useForm({ amount: '' });

const auth = computed(() => page.props.auth as Auth);
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
        value: `${props.recurringEntry.summary.converted_occurrences} / ${progressTotal.value}`,
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
            label: t('transactions.recurring.table.convertedBadge'),
            tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        };
    }

    return {
        label: t('transactions.recurring.table.pendingBadge'),
        tone: 'bg-slate-900/6 text-slate-700 dark:bg-white/8 dark:text-slate-300',
    };
}

function handlePause(): void {
    router.patch(pause.url(entry.value.uuid), {}, { preserveScroll: true });
}

function handleResume(): void {
    router.patch(resume.url(entry.value.uuid), {}, { preserveScroll: true });
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

function requestConvert(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
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
        `/recurring-entries/${entry.value.uuid}/occurrences/${undoConversionOccurrenceUuid.value}/conversion`,
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
                                @click="handleResume"
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

                    <div class="grid gap-3 sm:grid-cols-3">
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

                        <div class="space-y-1 text-sm">
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
                                v-if="!occurrence.converted_transaction"
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'transactions.recurring.table.noLinkedTransaction',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="occurrence.can_convert"
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="requestConvert(occurrence)"
                            >
                                {{
                                    t('transactions.recurring.actions.convert')
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
                                    t(
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
                                    {{
                                        occurrence.due_date ??
                                        occurrence.expected_date
                                    }}
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
                                </td>
                                <td class="px-4 py-4">
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <Button
                                            v-if="occurrence.can_convert"
                                            variant="outline"
                                            class="h-9 rounded-full px-3 text-xs"
                                            @click="requestConvert(occurrence)"
                                        >
                                            {{
                                                t(
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
                                                t(
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
                        {{ t('transactions.recurring.actions.convert') }}
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
                        t('transactions.recurring.dialogs.undoConversionTitle')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
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
                        {{ t('transactions.recurring.actions.undoConversion') }}
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
    </AppLayout>
</template>
