<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { TransactionsNavigation } from '@/types';

const page = usePage();
const { state } = useSidebar();

const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);

const summaryToneClass = computed(() => {
    if (navigation.value?.summary.status_tone === 'data') {
        return 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    }

    return 'bg-slate-900/6 text-slate-600 dark:bg-white/8 dark:text-slate-300';
});

const lastRecordedAtLabel = computed(() => {
    const value = navigation.value?.summary.last_recorded_at;

    if (!value) {
        return 'Nessuna registrazione';
    }

    return new Intl.DateTimeFormat('it-IT', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
});

const periodEndAtLabel = computed(() =>
    new Intl.DateTimeFormat('it-IT', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(navigation.value?.summary.period_end_at ?? new Date())),
);

const now = new Date();

const periodProgress = computed(() => {
    if (!navigation.value) {
        return null;
    }

    const selectedYear = navigation.value.context.year;
    const selectedMonth = navigation.value.context.month;

    if (selectedMonth === null) {
        if (selectedYear !== now.getFullYear()) {
            return null;
        }

        const currentDay = now.getDate();
        const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
        const percentage = Math.round((currentDay / daysInMonth) * 100);

        return {
            label: new Intl.DateTimeFormat('it-IT', {
                month: 'long',
                year: 'numeric',
            }).format(now),
            percentage,
            complete: percentage >= 100,
        };
    }

    const selectedDate = new Date(selectedYear, selectedMonth - 1, 1);
    const selectedMonthStart = new Date(selectedYear, selectedMonth - 1, 1);
    const selectedMonthEnd = new Date(selectedYear, selectedMonth, 0);

    let percentage = 0;

    if (selectedMonthEnd < now || selectedYear < now.getFullYear()) {
        percentage = 100;
    } else if (
        selectedMonthStart.getFullYear() === now.getFullYear() &&
        selectedMonthStart.getMonth() === now.getMonth()
    ) {
        percentage = Math.round((now.getDate() / selectedMonthEnd.getDate()) * 100);
    }

    return {
        label: new Intl.DateTimeFormat('it-IT', {
            month: 'long',
            year: 'numeric',
        }).format(selectedDate),
        percentage,
        complete: percentage >= 100,
    };
});
</script>

<template>
    <aside
        v-if="navigation?.enabled && state !== 'collapsed'"
        class="border-t border-sidebar-border/70 px-2 pt-3 text-sidebar-foreground"
    >
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sidebar-foreground/70">
                    Transazioni
                </p>
                <span class="text-[11px] font-medium text-sidebar-foreground/60">
                    {{ navigation.context.year }}
                </span>
            </div>

            <div class="grid grid-cols-3 gap-1 text-xs">
                <Link
                    v-for="month in navigation.months"
                    :key="month.value"
                    :href="month.href"
                    prefetch
                    :class="
                        cn(
                            'flex h-8 items-center justify-center rounded-md px-1 font-medium transition-colors',
                            month.is_selected
                                ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                                : month.has_data
                                  ? 'text-sidebar-foreground hover:bg-sidebar-accent'
                                  : 'text-sidebar-foreground/45 hover:bg-sidebar-accent/60',
                        )
                    "
                >
                    {{ month.label }}
                </Link>
            </div>

            <div class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate font-medium text-sidebar-foreground">
                        {{ navigation.context.period_label }}
                    </p>
                    <span
                        class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                        :class="summaryToneClass"
                    >
                        {{ navigation.summary.status }}
                    </span>
                </div>

                <template v-if="navigation.context.is_month_selected">
                    <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                        <span>{{ navigation.summary.records_label }}</span>
                        <span class="font-semibold text-sidebar-foreground">
                            {{ navigation.summary.records_count }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                        <span>Ultima registrazione</span>
                        <span class="font-medium text-sidebar-foreground">
                            {{ lastRecordedAtLabel }}
                        </span>
                    </div>
                    <div
                        v-if="periodProgress"
                        class="space-y-1 pt-1"
                    >
                        <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                            <span class="capitalize">{{ periodProgress.label }}</span>
                            <span class="font-semibold text-sidebar-foreground">
                                {{ periodProgress.percentage }}%
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-sidebar-border/70">
                            <div
                                class="h-full rounded-full transition-[width]"
                                :class="periodProgress.complete ? 'bg-emerald-500' : 'bg-sky-500'"
                                :style="{
                                    width: `${periodProgress.percentage}%`,
                                }"
                            />
                        </div>
                        <p class="text-[11px] text-sidebar-foreground/60">
                            {{
                                periodProgress.complete
                                    ? 'Periodo completato'
                                    : 'Periodo in corso'
                            }}
                        </p>
                    </div>
                </template>

                <template v-else>
                    <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                        <span>Copertura</span>
                        <span class="font-semibold text-sidebar-foreground">
                            {{ navigation.summary.coverage_months_count }}/{{ navigation.summary.coverage_total_months }}
                        </span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-sidebar-border/70">
                        <div
                            class="h-full rounded-full bg-sky-500 transition-[width]"
                            :style="{
                                width: `${navigation.summary.coverage_percentage}%`,
                            }"
                        />
                    </div>
                    <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                        <span>Ultima registrazione</span>
                        <span class="font-medium text-sidebar-foreground">
                            {{ lastRecordedAtLabel }}
                        </span>
                    </div>
                    <div
                        v-if="periodProgress"
                        class="space-y-1 pt-1"
                    >
                        <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                            <span class="capitalize">{{ periodProgress.label }}</span>
                            <span class="font-semibold text-sidebar-foreground">
                                {{ periodProgress.percentage }}%
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-sidebar-border/70">
                            <div
                                class="h-full rounded-full transition-[width]"
                                :class="periodProgress.complete ? 'bg-emerald-500' : 'bg-sky-500'"
                                :style="{
                                    width: `${periodProgress.percentage}%`,
                                }"
                            />
                        </div>
                        <p class="text-[11px] text-sidebar-foreground/60">
                            {{
                                periodProgress.complete
                                    ? 'Periodo completato'
                                    : 'Periodo in corso'
                            }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-sidebar-foreground/70">
                        <span>Fino al</span>
                        <span class="font-medium text-sidebar-foreground">
                            {{ periodEndAtLabel }}
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </aside>
</template>
