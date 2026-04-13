<script setup lang="ts">
import { CalendarDays, CornerDownRight, RefreshCcw } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import type { EntrySearchMonthGroup, EntrySearchResultItem } from '@/types';

const props = defineProps<{
    group: EntrySearchMonthGroup;
    localeOverride?: string | null;
}>();

const emit = defineEmits<{
    select: [item: EntrySearchResultItem];
}>();

const { locale, t } = useI18n();

function monthLabel(value: string): string {
    return new Intl.DateTimeFormat(props.localeOverride ?? locale.value, {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function itemDateLabel(value: string): string {
    return new Intl.DateTimeFormat(props.localeOverride ?? locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function resultKindLabel(kind: EntrySearchResultItem['kind']): string {
    return kind === 'transaction'
        ? t('entrySearch.resultKinds.transaction')
        : t('entrySearch.resultKinds.recurring');
}
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center gap-2 px-1">
            <CalendarDays class="size-4 text-slate-400" />
            <h3 class="text-sm font-semibold capitalize text-slate-700 dark:text-slate-200">
                {{ monthLabel(group.month_start) }}
            </h3>
        </div>

        <div class="space-y-2">
            <Button
                v-for="item in group.items"
                :key="`${item.kind}-${item.id}`"
                variant="ghost"
                class="flex h-auto w-full items-start justify-between rounded-[22px] border border-slate-200/80 bg-white/90 px-4 py-3 text-left shadow-none hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950/70 dark:hover:bg-slate-900"
                @click="emit('select', item)"
            >
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="secondary"
                            class="rounded-full border border-slate-200 bg-slate-100/80 text-[11px] capitalize text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                        >
                            {{
                                item.kind === 'transaction'
                                    ? t('entrySearch.resultKinds.transaction')
                                    : t('entrySearch.resultKinds.recurring')
                            }}
                        </Badge>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ itemDateLabel(item.date) }}
                        </span>
                    </div>
                    <p class="truncate text-sm font-semibold text-slate-950 dark:text-white">
                        {{ item.title }}
                    </p>
                    <p
                        v-if="item.subtitle"
                        class="line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        {{ item.subtitle }}
                    </p>
                </div>

                <div class="ml-3 flex shrink-0 flex-col items-end gap-2">
                    <p class="text-sm font-semibold text-slate-950 dark:text-white">
                        {{
                            item.amount !== null
                                ? formatCurrency(
                                      item.amount,
                                      item.currency_code ?? 'EUR',
                                      props.localeOverride ?? undefined,
                                  )
                                : '—'
                        }}
                    </p>
                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                        <component
                            :is="
                                item.kind === 'transaction'
                                    ? CornerDownRight
                                    : RefreshCcw
                            "
                            class="size-3.5"
                        />
                        {{ resultKindLabel(item.kind) }}
                    </span>
                </div>
            </Button>
        </div>
    </section>
</template>
