<script setup lang="ts">
import { useI18n } from 'vue-i18n';
const model = defineModel<boolean>({ required: true });
const { t } = useI18n();
</script>
<template>
    <div class="min-w-0 space-y-2">
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ t('transactions.recurring.form.labels.postingMode') }}
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
            <button
                type="button"
                class="rounded-[22px] border px-4 py-3 text-left transition-all"
                :class="
                    model
                        ? 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                        : 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-100'
                "
                @click="model = false"
            >
                <span class="block text-sm font-semibold">
                    {{ t('transactions.recurring.labels.manualPosting') }}
                </span>
                <span
                    class="mt-1 block text-xs text-slate-500 dark:text-slate-400"
                >
                    {{ t('transactions.recurring.form.helper.postingManual') }}
                </span>
            </button>
            <button
                type="button"
                class="rounded-[22px] border px-4 py-3 text-left transition-all"
                :class="
                    model
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100'
                        : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200'
                "
                @click="model = true"
            >
                <span class="block text-sm font-semibold">
                    {{ t('transactions.recurring.labels.autoPosting') }}
                </span>
                <span
                    class="mt-1 block text-xs text-slate-500 dark:text-slate-400"
                >
                    {{
                        t('transactions.recurring.form.helper.postingAutomatic')
                    }}
                </span>
            </button>
        </div>
    </div>
</template>
