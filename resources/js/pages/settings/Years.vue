<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarRange,
    CircleCheckBig,
    Lock,
    LockOpen,
    Plus,
    ShieldAlert,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, store, update, activate, destroy } from '@/routes/years';
import type { BreadcrumbItem, UserYearItem, YearsPageProps } from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<YearsPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.yearsPage.title'),
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const feedback = ref<FeedbackState | null>(null);
const deletingYear = ref<UserYearItem | null>(null);
const form = useForm({
    year: props.years.meta.next_year,
});
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.years.meta.next_year,
    (value) => {
        if (!form.isDirty) {
            form.year = value;
        }
    },
);

watch(
    () => flash.value.success,
    (message) => {
        if (message) {
            feedback.value = {
                variant: 'default',
                title: t('settings.yearsPage.feedback.successTitle'),
                message,
            };
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.year ?? errors.delete;

        if (message) {
            feedback.value = {
                variant: 'destructive',
                title: t('settings.yearsPage.feedback.unavailableTitle'),
                message,
            };
        }
    },
    { immediate: true, deep: true },
);

watch(feedback, (value) => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
        feedbackTimeout = null;
    }

    if (!value) {
        return;
    }

    feedbackTimeout = setTimeout(() => {
        feedback.value = null;
        feedbackTimeout = null;
    }, 4000);
});

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

const summaryCards = computed(() => [
    {
        label: t('settings.yearsPage.summary.total'),
        value: props.years.summary.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: t('settings.yearsPage.summary.open'),
        value: props.years.summary.open_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: t('settings.yearsPage.summary.closed'),
        value: props.years.summary.closed_count,
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        label: t('settings.yearsPage.summary.used'),
        value: props.years.summary.used_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
]);

const deleteReasons = computed(() => {
    if (!deletingYear.value) {
        return [];
    }

    const reasons: string[] = [];

    if (props.years.summary.total_count <= 1) {
        reasons.push(t('settings.years.delete_reasons.keep_one'));
    }

    if (deletingYear.value.is_active) {
        reasons.push(t('settings.years.delete_reasons.active_current'));
    }

    if (deletingYear.value.counts.budgets > 0) {
        reasons.push(t('settings.years.delete_reasons.budgets'));
    }

    if (deletingYear.value.counts.transactions > 0) {
        reasons.push(t('settings.years.delete_reasons.transactions'));
    }

    if (deletingYear.value.counts.scheduled_entries > 0) {
        reasons.push(t('settings.years.delete_reasons.scheduled_entries'));
    }

    if (deletingYear.value.counts.recurring_occurrences > 0) {
        reasons.push(t('settings.years.delete_reasons.recurring_occurrences'));
    }

    if (deletingYear.value.counts.recurring_entries > 0) {
        reasons.push(t('settings.years.delete_reasons.recurring_entries'));
    }

    return reasons;
});

function submitYear(year: number): void {
    form.transform(() => ({
        year,
    })).post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.defaults({
                year: props.years.meta.next_year,
            });
            form.year = props.years.meta.next_year;
        },
    });
}

function setActiveYear(item: UserYearItem): void {
    router.patch(activate(item.uuid), {}, { preserveScroll: true });
}

function toggleClosed(item: UserYearItem): void {
    router.patch(
        update(item.uuid),
        {
            is_closed: !item.is_closed,
        },
        {
            preserveScroll: true,
        },
    );
}

function deleteYear(): void {
    if (!deletingYear.value) {
        return;
    }

    router.delete(destroy(deletingYear.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            deletingYear.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.yearsPage.title')" />

        <SettingsLayout>
            <section class="space-y-6">
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-br from-slate-950 via-slate-900 to-sky-900 px-5 py-6 text-slate-50 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div class="space-y-3">
                                <Badge
                                    class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] tracking-[0.2em] text-white uppercase"
                                >
                                    {{ t('settings.yearsPage.badge') }}
                                </Badge>
                                <div class="space-y-2">
                                    <h1
                                        class="text-2xl font-semibold tracking-tight"
                                    >
                                        {{ t('settings.yearsPage.title') }}
                                    </h1>
                                    <p
                                        class="max-w-3xl text-sm leading-6 text-slate-300"
                                    >
                                        {{ t('settings.yearsPage.description') }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div
                                    v-for="card in summaryCards"
                                    :key="card.label"
                                    class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3"
                                >
                                    <p
                                        class="text-[11px] tracking-[0.16em] text-slate-300 uppercase"
                                    >
                                        {{ card.label }}
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-semibold text-white"
                                    >
                                        {{ card.value }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <Alert
                            v-if="feedback"
                            :class="
                                feedback.variant === 'destructive'
                                    ? 'border-rose-200/70 bg-rose-50 text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-100'
                                    : 'border-emerald-200/70 bg-emerald-50 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-100'
                            "
                        >
                            <CircleCheckBig
                                v-if="feedback.variant === 'default'"
                                class="h-4 w-4"
                            />
                            <ShieldAlert v-else class="h-4 w-4" />
                            <AlertTitle>{{ feedback.title }}</AlertTitle>
                            <AlertDescription>
                                {{ feedback.message }}
                            </AlertDescription>
                        </Alert>

                        <div
                            class="grid gap-4 rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 lg:grid-cols-[minmax(0,1fr)_auto] dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <CalendarRange
                                        class="h-4 w-4 text-sky-500"
                                    />
                                    <p
                                        class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{ t('settings.yearsPage.create.title') }}
                                    </p>
                                </div>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{ t('settings.yearsPage.create.description') }}
                                </p>
                            </div>

                            <form
                                class="flex flex-col gap-3 sm:flex-row"
                                @submit.prevent="submitYear(Number(form.year))"
                            >
                                <Input
                                    v-model="form.year"
                                    type="number"
                                    min="1900"
                                    max="2200"
                                    inputmode="numeric"
                                    class="h-11 min-w-32 rounded-2xl bg-white dark:bg-slate-950"
                                    :placeholder="t('settings.yearsPage.create.placeholder')"
                                />
                                <div class="flex gap-2">
                                    <Button
                                        type="submit"
                                        class="h-11 rounded-2xl"
                                        :disabled="form.processing"
                                    >
                                        <Plus class="mr-2 h-4 w-4" />
                                        {{ t('settings.yearsPage.create.submit') }}
                                    </Button>
                                    <Button
                                        v-if="props.years.data.length > 0"
                                        type="button"
                                        variant="outline"
                                        class="h-11 rounded-2xl"
                                        :disabled="form.processing"
                                        @click="
                                            submitYear(
                                                props.years.meta.next_year,
                                            )
                                        "
                                    >
                                        <Sparkles class="mr-2 h-4 w-4" />
                                        {{
                                            t('settings.yearsPage.create.quickCreate', {
                                                year: props.years.meta.next_year,
                                            })
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </div>

                        <div
                            v-if="props.years.data.length === 0"
                            class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/70 px-5 py-10 text-center dark:border-slate-700 dark:bg-slate-900/40"
                        >
                            <p
                                class="text-base font-medium text-slate-950 dark:text-slate-50"
                            >
                                {{ t('settings.yearsPage.empty.title') }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                {{ t('settings.yearsPage.empty.description') }}
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="hidden overflow-hidden lg:block">
                                <div
                                    class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 dark:border-slate-800"
                                >
                                    <table
                                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-800"
                                    >
                                        <thead
                                            class="bg-slate-50/80 dark:bg-slate-900/80"
                                        >
                                            <tr
                                                class="text-left text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                <th class="px-5 py-4">{{ t('settings.yearsPage.table.year') }}</th>
                                                <th class="px-5 py-4">{{ t('settings.yearsPage.table.status') }}</th>
                                                <th class="px-5 py-4">
                                                    {{ t('settings.yearsPage.table.usage') }}
                                                </th>
                                                <th class="px-5 py-4">
                                                    {{ t('settings.yearsPage.table.actions') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950/70"
                                        >
                                            <tr
                                                v-for="item in props.years.data"
                                                :key="item.uuid"
                                            >
                                                <td class="px-5 py-4 align-top">
                                                    <div class="space-y-2">
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <p
                                                                class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                                                            >
                                                                {{ item.year }}
                                                            </p>
                                                            <Badge
                                                                v-if="
                                                                    item.is_active
                                                                "
                                                                class="rounded-full bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                            >
                                                                {{ t('settings.yearsPage.status.active') }}
                                                            </Badge>
                                                            <Badge
                                                                v-if="
                                                                    item.is_closed
                                                                "
                                                                class="rounded-full bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                                                            >
                                                                {{ t('settings.yearsPage.status.closed') }}
                                                            </Badge>
                                                        </div>
                                                        <p
                                                            class="text-sm text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{
                                                                item.is_closed
                                                                    ? t('settings.yearsPage.status.closedDescription')
                                                                    : t('settings.yearsPage.status.openDescription')
                                                            }}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4 align-top">
                                                    <div
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <Badge
                                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                                        >
                                                            {{ t('settings.yearsPage.metrics.budgets') }}
                                                            {{
                                                                item.counts
                                                                    .budgets
                                                            }}
                                                        </Badge>
                                                        <Badge
                                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                                        >
                                                            {{ t('settings.yearsPage.metrics.transactions') }}
                                                            {{
                                                                item.counts
                                                                    .transactions
                                                            }}
                                                        </Badge>
                                                        <Badge
                                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                                        >
                                                            {{ t('settings.yearsPage.metrics.scheduled') }}
                                                            {{
                                                                item.counts
                                                                    .scheduled_entries
                                                            }}
                                                        </Badge>
                                                        <Badge
                                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                                        >
                                                            {{ t('settings.yearsPage.metrics.recurring') }}
                                                            {{
                                                                item.counts
                                                                    .recurring_occurrences +
                                                                item.counts
                                                                    .recurring_entries
                                                            }}
                                                        </Badge>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4 align-top">
                                                    <div class="space-y-2">
                                                        <p
                                                            class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                                        >
                                                            {{
                                                                item.used
                                                                    ? t('settings.yearsPage.metrics.usedMany', {
                                                                          count: item.usage_count,
                                                                      })
                                                                    : t('settings.yearsPage.metrics.noUsage')
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-sm text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{
                                                                item.is_deletable
                                                                    ? t('settings.yearsPage.metrics.deletableHint')
                                                                    : t('settings.yearsPage.metrics.lockedHint')
                                                            }}
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4 align-top">
                                                    <div
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <Button
                                                            v-if="
                                                                !item.is_active
                                                            "
                                                            variant="outline"
                                                            class="rounded-2xl"
                                                            @click="
                                                                setActiveYear(
                                                                    item,
                                                                )
                                                            "
                                                        >
                                                            {{ t('settings.yearsPage.actions.setActive') }}
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            class="rounded-2xl"
                                                            @click="
                                                                toggleClosed(
                                                                    item,
                                                                )
                                                            "
                                                        >
                                                            <Lock
                                                                v-if="
                                                                    !item.is_closed
                                                                "
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            <LockOpen
                                                                v-else
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            {{
                                                                item.is_closed
                                                                    ? t('settings.yearsPage.actions.open')
                                                                    : t('settings.yearsPage.actions.close')
                                                            }}
                                                        </Button>
                                                        <Button
                                                            v-if="
                                                                item.is_deletable
                                                            "
                                                            variant="outline"
                                                            class="rounded-2xl text-rose-600 dark:text-rose-300"
                                                            @click="
                                                                deletingYear =
                                                                    item
                                                            "
                                                        >
                                                            <Trash2
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            {{ t('settings.yearsPage.actions.delete') }}
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="grid gap-3 lg:hidden">
                                <article
                                    v-for="item in props.years.data"
                                    :key="item.uuid"
                                    class="rounded-[1.5rem] border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="space-y-2">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <h2
                                                    class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                                                >
                                                    {{ item.year }}
                                                </h2>
                                                <Badge
                                                    v-if="item.is_active"
                                                    class="rounded-full bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                >
                                                    {{ t('settings.yearsPage.status.active') }}
                                                </Badge>
                                                <Badge
                                                    v-if="item.is_closed"
                                                    class="rounded-full bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                                                >
                                                    {{ t('settings.yearsPage.status.closed') }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                            >
                                                {{
                                                    item.is_closed
                                                        ? t('settings.yearsPage.status.mobileClosedDescription')
                                                        : t('settings.yearsPage.status.mobileOpenDescription')
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-2xl bg-slate-100 px-3 py-2 text-right dark:bg-slate-900"
                                        >
                                            <p
                                                class="text-[11px] tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{ t('settings.yearsPage.metrics.usageTitle') }}
                                            </p>
                                            <p
                                                class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ item.usage_count }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <Badge
                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                        >
                                            {{ t('settings.yearsPage.metrics.budgets') }} {{ item.counts.budgets }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                        >
                                            {{ t('settings.yearsPage.metrics.transactions') }}
                                            {{ item.counts.transactions }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                        >
                                            {{ t('settings.yearsPage.metrics.scheduled') }}
                                            {{ item.counts.scheduled_entries }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full bg-slate-900/7 text-slate-700 dark:bg-white/8 dark:text-slate-200"
                                        >
                                            {{ t('settings.yearsPage.metrics.recurring') }}
                                            {{
                                                item.counts
                                                    .recurring_occurrences +
                                                item.counts.recurring_entries
                                            }}
                                        </Badge>
                                    </div>

                                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                        <Button
                                            v-if="!item.is_active"
                                            variant="outline"
                                            class="rounded-2xl"
                                            @click="setActiveYear(item)"
                                        >
                                            {{ t('settings.yearsPage.actions.setActive') }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            class="rounded-2xl"
                                            @click="toggleClosed(item)"
                                        >
                                            <Lock
                                                v-if="!item.is_closed"
                                                class="mr-2 h-4 w-4"
                                            />
                                            <LockOpen
                                                v-else
                                                class="mr-2 h-4 w-4"
                                            />
                                            {{
                                                item.is_closed
                                                    ? t('settings.yearsPage.actions.open')
                                                    : t('settings.yearsPage.actions.close')
                                            }}
                                        </Button>
                                        <Button
                                            v-if="item.is_deletable"
                                            variant="outline"
                                            class="rounded-2xl text-rose-600 sm:col-span-2 dark:text-rose-300"
                                            @click="deletingYear = item"
                                        >
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            {{ t('settings.yearsPage.actions.deleteYear') }}
                                        </Button>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <Dialog
                :open="deletingYear !== null"
                @update:open="(open) => !open && (deletingYear = null)"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {{ t('settings.yearsPage.deleteDialog.title', { year: deletingYear?.year }) }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t('settings.yearsPage.deleteDialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <Alert
                            v-if="deleteReasons.length > 0"
                            class="border-amber-200/70 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                        >
                            <ShieldAlert class="h-4 w-4" />
                            <AlertTitle>{{ t('settings.yearsPage.deleteDialog.blockedTitle') }}</AlertTitle>
                            <AlertDescription>
                                <span
                                    v-for="reason in deleteReasons"
                                    :key="reason"
                                    class="block"
                                >
                                    {{ reason }}
                                </span>
                            </AlertDescription>
                        </Alert>
                        <p
                            v-else
                            class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('settings.yearsPage.deleteDialog.confirm') }}
                            {{ t('settings.yearsPage.deleteDialog.confirmSuffix') }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            class="rounded-2xl"
                            @click="deletingYear = null"
                        >
                            {{ t('settings.yearsPage.deleteDialog.cancel') }}
                        </Button>
                        <Button
                            class="rounded-2xl"
                            :disabled="deleteReasons.length > 0"
                            @click="deleteYear"
                        >
                            {{ t('settings.yearsPage.deleteDialog.confirmAction') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
