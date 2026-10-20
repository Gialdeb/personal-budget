<script setup lang="ts">
import { BellRing } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import type { RecurringEntryFormOptions } from '@/types/transactions';
const props = defineProps<{
    options: RecurringEntryFormOptions['reminders'];
    error?: string;
}>();
const model = defineModel<number[]>({ required: true });
const { t } = useI18n();
const reminderDayOptions = computed(() =>
    Array.from(
        { length: props.options.max_days_before },
        (_, index) => index + 1,
    ),
);
const reminderLimitReached = computed(
    () => model.value.length >= props.options.max_custom_alerts,
);

function toggleReminderDay(daysBefore: number): void {
    if (model.value.includes(daysBefore)) {
        model.value = model.value.filter((value) => value !== daysBefore);

        return;
    }

    if (reminderLimitReached.value) {
        return;
    }

    model.value = [...model.value, daysBefore].sort(
        (left, right) => left - right,
    );
}
</script>
<template>
    <section
        class="space-y-4 rounded-[28px] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/40"
    >
        <div class="flex items-start gap-3">
            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
            >
                <BellRing class="size-4" />
            </div>
            <div class="space-y-1">
                <p
                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    {{ t('transactions.recurring.form.sections.reminders') }}
                </p>
                <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                    {{
                        t('transactions.recurring.form.helper.reminders', {
                            count: options.max_custom_alerts,
                        })
                    }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
            <button
                v-for="daysBefore in reminderDayOptions"
                :key="daysBefore"
                type="button"
                class="rounded-2xl border px-2 py-2.5 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-45"
                :class="
                    model.includes(daysBefore)
                        ? 'border-sky-400 bg-sky-100 text-sky-900 dark:border-sky-500/50 dark:bg-sky-500/15 dark:text-sky-100'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 hover:text-sky-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-300 dark:hover:border-sky-500/40 dark:hover:text-sky-300'
                "
                :aria-pressed="model.includes(daysBefore)"
                :disabled="reminderLimitReached && !model.includes(daysBefore)"
                @click="toggleReminderDay(daysBefore)"
            >
                {{
                    t(
                        daysBefore === 1
                            ? 'transactions.recurring.form.reminders.dayBefore'
                            : 'transactions.recurring.form.reminders.daysBefore',
                        { count: daysBefore },
                    )
                }}
            </button>
        </div>

        <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
            {{
                model.length > 0
                    ? t('transactions.recurring.form.reminders.selected', {
                          count: model.length,
                          max: options.max_custom_alerts,
                      })
                    : t('transactions.recurring.form.reminders.noneSelected')
            }}
        </p>
        <InputError :message="error" />
    </section>
</template>
