<script setup lang="ts">
import { CalendarClock, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { formatCurrency } from '@/lib/currency';
import type {
    DashboardMerchantBreakdownItem,
    DashboardNotificationSummary,
    DashboardRecurringSummary,
    DashboardScheduledSummary,
} from '@/types/dashboard';

const props = defineProps<{
    notifications: DashboardNotificationSummary;
    recurring: DashboardRecurringSummary;
    scheduled: DashboardScheduledSummary;
    merchants: DashboardMerchantBreakdownItem[];
    currency: string;
}>();
const { locale, t } = useI18n();
const upcoming = computed(() => props.scheduled.upcoming.slice(0, 4));
const payees = computed(() => props.merchants.slice(0, 4));

function money(value: number): string {
    return formatCurrency(value, props.currency);
}
function date(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${value}T12:00:00`));
}
function transactionLabel(count: number): string {
    return t(
        count === 1
            ? 'dashboard.agenda.transactionOne'
            : 'dashboard.agenda.transactionMany',
        { count },
    );
}
</script>

<template>
    <section class="rounded-2xl border bg-card p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold">{{ t('dashboard.agenda.title') }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ t('dashboard.agenda.description') }}
        </p>

        <dl class="mt-5 grid grid-cols-3 gap-2 sm:gap-3">
            <div class="rounded-xl bg-muted/45 p-3">
                <dt
                    class="text-[10px] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                >
                    {{ t('dashboard.agenda.dueSoon') }}
                </dt>
                <dd class="mt-1 text-xl font-semibold">
                    {{ notifications.due_scheduled_count }}
                </dd>
            </div>
            <div class="rounded-xl bg-muted/45 p-3">
                <dt
                    class="text-[10px] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                >
                    {{ t('dashboard.agenda.recurring') }}
                </dt>
                <dd class="mt-1 text-xl font-semibold">
                    {{ recurring.planned_count }}
                </dd>
            </div>
            <div class="rounded-xl bg-muted/45 p-3">
                <dt
                    class="text-[10px] font-semibold tracking-[0.1em] text-muted-foreground uppercase"
                >
                    {{ t('dashboard.agenda.review') }}
                </dt>
                <dd class="mt-1 text-xl font-semibold">
                    {{ notifications.review_needed_count }}
                </dd>
            </div>
        </dl>

        <div class="mt-5">
            <h3 class="flex items-center gap-2 text-sm font-medium">
                <CalendarClock class="size-4 text-primary" />{{
                    t('dashboard.agenda.upcomingPlanned')
                }}
            </h3>
            <div v-if="upcoming.length" class="mt-3 grid gap-2">
                <article
                    v-for="entry in upcoming"
                    :key="entry.id"
                    class="flex items-center justify-between gap-3 rounded-xl bg-muted/45 px-3.5 py-3"
                >
                    <div class="min-w-0">
                        <h4 class="truncate text-sm font-medium">
                            {{ entry.display_label }}
                        </h4>
                        <p class="text-xs text-muted-foreground">
                            {{ date(entry.scheduled_date) }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <SensitiveValue
                            class="text-sm font-semibold"
                            :value="money(entry.expected_amount_raw)"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    `dashboard.agenda.entryKinds.${entry.entry_kind}`,
                                )
                            }}
                        </p>
                    </div>
                </article>
            </div>
            <p
                v-else
                class="mt-3 rounded-xl bg-muted/45 px-4 py-5 text-sm text-muted-foreground"
            >
                {{ t('dashboard.agenda.upcomingEmpty') }}
            </p>
        </div>

        <div class="mt-5">
            <h3 class="flex items-center gap-2 text-sm font-medium">
                <TrendingUp
                    class="size-4 text-[var(--dashboard-emerald,var(--chart-2))]"
                />{{ t('dashboard.agenda.topPayees') }}
            </h3>
            <div v-if="payees.length" class="mt-3 grid gap-2">
                <article
                    v-for="payee in payees"
                    :key="payee.display_label"
                    class="flex items-center justify-between gap-3 rounded-xl bg-muted/45 px-3.5 py-3"
                >
                    <div class="min-w-0">
                        <h4 class="truncate text-sm font-medium">
                            {{ payee.display_label }}
                        </h4>
                        <p class="text-xs text-muted-foreground">
                            {{ transactionLabel(payee.transactions_count) }}
                        </p>
                    </div>
                    <SensitiveValue
                        class="shrink-0 text-sm font-semibold"
                        :value="money(payee.total_amount_raw)"
                    />
                </article>
            </div>
            <p
                v-else
                class="mt-3 rounded-xl bg-muted/45 px-4 py-5 text-sm text-muted-foreground"
            >
                {{ t('dashboard.agenda.payeesEmpty') }}
            </p>
        </div>
    </section>
</template>
