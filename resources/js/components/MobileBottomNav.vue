<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronRight,
    House,
    PiggyBank,
    Plus,
    Settings2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { budgetPlanning, dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { index as recurringEntriesIndex } from '@/routes/recurring-entries';
import { show as transactionsShow } from '@/routes/transactions';
import type { TransactionsNavigation } from '@/types';

type RouteSection =
    | 'dashboard'
    | 'planning'
    | 'transactions'
    | 'recurring'
    | 'settings'
    | 'generic';

const page = usePage();
const { t } = useI18n();
const isDestinationsOpen = ref(false);

const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);

const currentPath = computed(() => {
    const url = String(page.url ?? '/');

    return url.split('?')[0] || '/';
});

const currentSection = computed<RouteSection>(() => {
    const path = currentPath.value;

    if (path === '/') {
        return 'dashboard';
    }

    if (path.startsWith('/budgets/planning')) {
        return 'planning';
    }

    if (path.startsWith('/transactions')) {
        return 'transactions';
    }

    if (path.startsWith('/recurring-entries')) {
        return 'recurring';
    }

    if (path.startsWith('/settings')) {
        return 'settings';
    }

    return 'generic';
});

const transactionsHref = computed(() => {
    if (navigation.value?.context.year && navigation.value?.context.month) {
        return transactionsShow({
            year: navigation.value.context.year,
            month: navigation.value.context.month,
        });
    }

    const now = new Date();

    return transactionsShow({
        year: now.getFullYear(),
        month: now.getMonth() + 1,
    });
});

const recurringHref = computed(() => recurringEntriesIndex());

function isSectionActive(section: RouteSection | RouteSection[]): boolean {
    const sections = Array.isArray(section) ? section : [section];

    return sections.includes(currentSection.value);
}

function handlePrimaryAction(): void {
    const kind =
        currentSection.value === 'recurring' ? 'recurring' : 'transaction';
    const event = new CustomEvent('app:mobile-primary-action', {
        cancelable: true,
        detail: { kind },
    });
    const isHandled = !window.dispatchEvent(event);

    if (isHandled) {
        return;
    }

    router.visit(
        kind === 'recurring'
            ? recurringHref.value.url
            : transactionsHref.value.url,
    );
}
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-40 px-4 pb-[calc(env(safe-area-inset-bottom)+0.9rem)] md:hidden"
    >
        <div
            class="pointer-events-auto mx-auto flex max-w-md items-end justify-between rounded-[2rem] border border-slate-200/80 bg-white/96 px-3 py-3 shadow-[0_-14px_48px_-30px_rgba(15,23,42,0.38)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/94"
        >
            <Link
                :href="dashboard()"
                class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-medium transition"
                :class="
                    isSectionActive('dashboard')
                        ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
                        : 'text-slate-500 dark:text-slate-400'
                "
            >
                <House class="size-5" />
                <span>Home</span>
            </Link>

            <Sheet v-model:open="isDestinationsOpen">
                <SheetTrigger as-child>
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-medium transition"
                        :class="
                            isSectionActive(['transactions', 'recurring'])
                                ? 'bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-slate-100'
                                : 'text-slate-500 dark:text-slate-400'
                        "
                    >
                        <Wallet class="size-5" />
                        <span>Movimenti</span>
                    </button>
                </SheetTrigger>
                <SheetContent
                    side="bottom"
                    class="rounded-t-[2rem] px-5 pt-5 pb-8"
                >
                    <SheetHeader class="text-left">
                        <SheetTitle>{{
                            t('transactions.index.title')
                        }}</SheetTitle>
                        <SheetDescription>
                            Accesso rapido a transazioni e ricorrenze.
                        </SheetDescription>
                    </SheetHeader>

                    <div class="mt-5 grid gap-3">
                        <Link
                            :href="transactionsHref"
                            class="flex items-center justify-between rounded-[1.5rem] border border-slate-200 bg-white px-4 py-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-950"
                            @click="isDestinationsOpen = false"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
                                >
                                    <Wallet class="size-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        Transazioni
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Registrazioni del mese attivo
                                    </p>
                                </div>
                            </div>
                            <ChevronRight class="size-4 text-slate-400" />
                        </Link>

                        <Link
                            :href="recurringHref"
                            class="flex items-center justify-between rounded-[1.5rem] border border-slate-200 bg-white px-4 py-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-950"
                            @click="isDestinationsOpen = false"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                                >
                                    <CalendarDays class="size-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        Ricorrenze
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Piani e scadenze programmate
                                    </p>
                                </div>
                            </div>
                            <ChevronRight class="size-4 text-slate-400" />
                        </Link>
                    </div>
                </SheetContent>
            </Sheet>

            <Button
                type="button"
                size="icon"
                class="mb-3 h-14 w-14 shrink-0 rounded-[1.6rem] bg-slate-700 text-white shadow-[0_16px_32px_-18px_rgba(15,23,42,0.85)] hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600"
                @click="handlePrimaryAction"
            >
                <Plus class="size-6" />
            </Button>

            <Link
                :href="budgetPlanning()"
                class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-medium transition"
                :class="
                    isSectionActive('planning')
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                        : 'text-slate-500 dark:text-slate-400'
                "
            >
                <PiggyBank class="size-5" />
                <span>Budget</span>
            </Link>

            <Link
                :href="editProfile()"
                class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-2xl px-2 py-2 text-[11px] font-medium transition"
                :class="
                    isSectionActive('settings')
                        ? 'bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-slate-100'
                        : 'text-slate-500 dark:text-slate-400'
                "
            >
                <Settings2 class="size-5" />
                <span>Impost.</span>
            </Link>
        </div>
    </div>
</template>
