<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import type {
    RecurringMonthlyCalendarDay,
    RecurringMonthlyOccurrence,
} from '@/types';

const props = defineProps<{
    days: RecurringMonthlyCalendarDay[];
    baseCurrency: string;
    formatLocale?: string | null;
    highlightedEntryUuid?: string | null;
}>();

const emit = defineEmits<{
    convert: [occurrence: RecurringMonthlyOccurrence];
    refund: [occurrence: RecurringMonthlyOccurrence];
    edit: [entryUuid: string];
}>();

const { locale, t } = useI18n();

const hasItems = computed(() =>
    props.days.some((day) => day.occurrences.length > 0),
);

function formatMoney(value: number, currency?: string | null): string {
    return formatCurrency(
        value,
        currency ?? props.baseCurrency,
        props.formatLocale ?? undefined,
    );
}

function formatDayHeading(date: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date(`${date}T00:00:00`));
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

function occurrenceState(occurrence: RecurringMonthlyOccurrence): {
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
</script>

<template>
    <div class="lg:hidden">
        <div
            v-if="!hasItems"
            class="rounded-[24px] border border-slate-200/80 px-4 py-10 text-center text-sm text-slate-600 dark:border-white/10 dark:text-slate-300"
        >
            {{ t('transactions.recurring.table.empty') }}
        </div>

        <div v-else class="space-y-5">
            <section
                v-for="day in days"
                :id="`${day.anchor}-mobile`"
                :key="day.date"
                class="overflow-hidden rounded-[24px] border border-slate-200/80 dark:border-white/10"
            >
                <div
                    class="space-y-2 border-b border-slate-200/80 bg-slate-50/85 px-4 py-3 dark:border-white/10 dark:bg-slate-900/60"
                >
                    <h3
                        class="text-sm font-semibold tracking-[0.12em] text-slate-700 uppercase dark:text-slate-200"
                    >
                        {{ formatDayHeading(day.date) }}
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            class="rounded-full bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300"
                        >
                            {{
                                t(
                                    'transactions.recurring.labels.plannedIncome',
                                )
                            }}: {{ formatMoney(day.income_total) }}
                        </Badge>
                        <Badge
                            class="rounded-full bg-rose-500/10 text-rose-700 dark:bg-rose-500/12 dark:text-rose-300"
                        >
                            {{
                                t(
                                    'transactions.recurring.labels.plannedExpenses',
                                )
                            }}: {{ formatMoney(day.expense_total) }}
                        </Badge>
                    </div>
                </div>

                <div class="divide-y divide-slate-200/80 dark:divide-white/10">
                    <article
                        v-for="occurrence in day.occurrences"
                        :key="occurrence.uuid"
                        :data-recurring-entry-row="
                            occurrence.recurring_entry?.uuid ?? occurrence.uuid
                        "
                        :class="
                            occurrence.recurring_entry?.uuid ===
                            props.highlightedEntryUuid
                                ? 'bg-sky-50/80 dark:bg-sky-500/8'
                                : ''
                        "
                        class="space-y-4 px-4 py-4 transition-colors"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <Link
                                    v-if="occurrence.recurring_entry?.show_url"
                                    :href="occurrence.recurring_entry.show_url"
                                    class="font-semibold text-slate-950 underline-offset-4 hover:underline dark:text-white"
                                >
                                    {{
                                        occurrence.title ??
                                        occurrence.recurring_entry.title
                                    }}
                                </Link>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        occurrence.description ??
                                        t(
                                            'transactions.recurring.labels.noDescription',
                                        )
                                    }}
                                </p>
                            </div>
                            <p
                                class="text-sm font-semibold"
                                :class="
                                    occurrence.direction === 'income'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-rose-700 dark:text-rose-300'
                                "
                            >
                                {{
                                    formatMoney(
                                        occurrence.expected_amount ?? 0,
                                        occurrence.currency,
                                    )
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge
                                class="rounded-full bg-white/85 text-slate-700 dark:bg-slate-950/80 dark:text-slate-300"
                            >
                                {{ entryTypeLabel(occurrence.entry_type) }}
                            </Badge>
                            <Badge
                                class="rounded-full"
                                :class="
                                    occurrence.direction === 'income'
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/12 dark:text-emerald-300'
                                        : 'bg-rose-500/10 text-rose-700 dark:bg-rose-500/12 dark:text-rose-300'
                                "
                            >
                                {{ directionLabel(occurrence.direction) }}
                            </Badge>
                            <Badge
                                class="rounded-full"
                                :class="occurrenceState(occurrence).tone"
                            >
                                {{ occurrenceState(occurrence).label }}
                            </Badge>
                        </div>

                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div class="space-y-1">
                                <dt
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.account',
                                        )
                                    }}
                                </dt>
                                <dd class="text-slate-700 dark:text-slate-200">
                                    {{
                                        occurrence.recurring_entry?.account
                                            ?.name ??
                                        t(
                                            'transactions.recurring.labels.noAccount',
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="space-y-1">
                                <dt
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.category',
                                        )
                                    }}
                                </dt>
                                <dd class="text-slate-700 dark:text-slate-200">
                                    {{
                                        occurrence.recurring_entry?.category
                                            ?.name ??
                                        t(
                                            'transactions.recurring.labels.noCategory',
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="space-y-1">
                                <dt
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.trackedItem',
                                        )
                                    }}
                                </dt>
                                <dd class="text-slate-700 dark:text-slate-200">
                                    {{
                                        occurrence.recurring_entry?.tracked_item
                                            ?.name ??
                                        t(
                                            'transactions.recurring.labels.noTrackedItem',
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="space-y-1">
                                <dt
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.labels.convertedTransaction',
                                        )
                                    }}
                                </dt>
                                <dd
                                    v-if="occurrence.converted_transaction"
                                    class="space-y-1"
                                >
                                    <Link
                                        v-if="
                                            occurrence.converted_transaction
                                                .show_url
                                        "
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
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            occurrence.converted_transaction
                                                .transaction_date
                                        }}
                                    </p>
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
                                </dd>
                                <dd
                                    v-else
                                    class="text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            'transactions.recurring.table.noLinkedTransaction',
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="occurrence.can_convert"
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="emit('convert', occurrence)"
                            >
                                {{
                                    t('transactions.recurring.actions.convert')
                                }}
                            </Button>
                            <Button
                                v-if="
                                    occurrence.converted_transaction?.can_refund
                                "
                                variant="outline"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="emit('refund', occurrence)"
                            >
                                {{ t('transactions.recurring.actions.refund') }}
                            </Button>
                            <Button
                                variant="ghost"
                                class="h-9 rounded-full px-3 text-xs"
                                @click="
                                    occurrence.recurring_entry?.uuid
                                        ? emit(
                                              'edit',
                                              occurrence.recurring_entry.uuid,
                                          )
                                        : undefined
                                "
                            >
                                {{ t('transactions.recurring.actions.edit') }}
                            </Button>
                            <Link
                                v-if="occurrence.recurring_entry?.show_url"
                                :href="occurrence.recurring_entry.show_url"
                                class="inline-flex h-9 items-center rounded-full px-3 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900"
                            >
                                {{
                                    t('transactions.recurring.actions.openPlan')
                                }}
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
