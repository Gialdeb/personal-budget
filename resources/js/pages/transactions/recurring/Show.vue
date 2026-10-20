<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useStorage } from '@vueuse/core';
import {
    ArrowLeft,
    ArrowDownLeft,
    ArrowUpRight,
    CalendarClock,
    ChartNoAxesCombined,
    ChevronDown,
    Infinity as InfinityIcon,
    Check,
    LoaderCircle,
    Pause,
    Pencil,
    Play,
    Receipt,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import { archive as archiveComponent } from '@/actions/App/Http/Controllers/CompoundRecurringEntryController';
import {
    show as showRecurringEntry,
    cancel,
    destroy,
    pause,
    resume,
    interrupt,
    updateInstallmentDistribution,
} from '@/actions/App/Http/Controllers/RecurringEntryController';
import {
    paymentCandidates,
    allocatePayment,
    convert as convertOccurrence,
    undoConversion,
    skip as skipOccurrence,
    restore as restoreOccurrence,
    updateAmount,
    update as updateOccurrence,
} from '@/actions/App/Http/Controllers/RecurringEntryOccurrenceController';
import { refund as refundTransaction } from '@/actions/App/Http/Controllers/RecurringEntryTransactionController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import CompoundRecurringFormSheet from '@/components/recurring/CompoundRecurringFormSheet.vue';
import OccurrencePayments from '@/components/recurring/OccurrencePayments.vue';
import RecurringCostTrend from '@/components/recurring/RecurringCostTrend.vue';
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
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useForm } from '@/composables/useNotifiedForm';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import { requestedOccurrenceAction } from '@/lib/compound-recurring';
import { formatCurrency } from '@/lib/currency';
import {
    annualOccurrencesProgress,
    recurringPlanProgress,
} from '@/lib/recurring-plan-progress';
import type {
    Auth,
    BreadcrumbItem,
    RecurringEntryShowPageProps,
} from '@/types';

const ui = useUiCopy();

const props = defineProps<RecurringEntryShowPageProps>();
type Occurrence =
    RecurringEntryShowPageProps['recurringEntry']['occurrences'][number];
type YearChunk = {
    year: number;
    offset: number;
    next_offset: number | null;
    occurrences: Occurrence[];
};
const showCancelledYears = ref<Record<number, boolean>>({});
const yearRows = ref<Record<number, Occurrence[]>>({});
const yearNext = ref<Record<number, number | null>>({});
const yearFirst = ref<Record<number, number>>({});
const openYears = ref<number[]>([]);
const loadingYears = ref<number[]>([]);
const failedYears = ref<number[]>([]);
const retryOffsets = ref<Record<number, number>>({});
const distributionLoading = ref(false);
const distributionRows = ref<Occurrence[]>([]);
const historyOpen = ref(false);
const componentsOpen = ref(true);
const lifecycleOpen = ref(false);
const costTrendPanel = ref<InstanceType<typeof RecurringCostTrend> | null>(
    null,
);
const hasCostTrend = computed(() => entry.value.entry_type === 'recurring');
const addComponentOpen = ref(false);
const archiveComponentOpen = ref(false);
const archiveComponentForm = useForm({});
function removeSelectedComponent(): void {
    if (!props.compound || props.compound.is_archived) {
        return;
    }

    archiveComponentForm.patch(
        archiveComponent.url([
            props.compound.uuid,
            props.compound.component_uuid,
        ]),
        {
            preserveScroll: true,
            onSuccess: () => {
                archiveComponentOpen.value = false;
            },
        },
    );
}
async function toggleCancelled(year: number): Promise<void> {
    showCancelledYears.value[year] = !showCancelledYears.value[year];
    await loadYear(year, 0, true);
}
const pageTitle = computed(
    () => props.compound?.name ?? props.recurringEntry.entry.title,
);
function occurrenceOwner(uuid: string): string {
    const row = Object.values(yearRows.value)
        .flat()
        .find((occurrence) => occurrence.uuid === uuid);

    if (props.compound && !row?.entry_uuid) {
        throw new Error('Missing occurrence owner');
    }

    return row?.entry_uuid ?? props.recurringEntry.entry.uuid;
}
let loadGeneration = 0;
watch(
    () => props.recurringEntry,
    (payload) => {
        loadGeneration++;
        const chunk = payload.occurrence_page;
        showCancelledYears.value = {
            [chunk.year]: chunk.show_cancelled ?? false,
        };
        yearRows.value = { [chunk.year]: payload.occurrences };
        yearNext.value = { [chunk.year]: chunk.next_offset };
        yearFirst.value = { [chunk.year]: chunk.offset };
        const current = Number(props.dateOptions.today.slice(0, 4));
        openYears.value = [...new Set([current, chunk.year])];
        loadingYears.value = [];
        failedYears.value = [];

        if (current !== chunk.year) {
            if (
                payload.occurrence_years.some(
                    (group) => group.year === current && group.count > 0,
                )
            ) {
                void loadYear(current);
            } else {
                yearRows.value[current] = [];
            }
        }
    },
    { immediate: true },
);
const occurrenceYears = computed(() => {
    const current = Number(props.dateOptions.today.slice(0, 4));
    const years = props.recurringEntry.occurrence_years ?? [];

    return (
        years.some((group) => group.year === current)
            ? [...years]
            : [
                  ...years,
                  {
                      year: current,
                      count: 0,
                      overdue_count: 0,
                      overdue_amount: 0,
                  },
              ]
    ).sort((a, b) => {
        if (a.year === current) {
            return -1;
        }

        if (b.year === current) {
            return 1;
        }

        if (a.year > current && b.year > current) {
            return a.year - b.year;
        }

        if (a.year > current) {
            return -1;
        }

        if (b.year > current) {
            return 1;
        }

        return b.year - a.year;
    });
});
const loadedOccurrences = computed(() => Object.values(yearRows.value).flat());
const latestAmountChange = computed(() =>
    props.recurringEntry.amount_history.at(-1),
);
async function fetchYear(
    year: number,
    offset: number | null,
    highlight: string | null = null,
    includeCancelled: boolean = showCancelledYears.value[year] ?? false,
): Promise<YearChunk> {
    const response = await fetch(
        showRecurringEntry.url(
            props.compound?.uuid ?? props.recurringEntry.entry.uuid,
            {
                query: {
                    occurrence_year: year,
                    show_cancelled: includeCancelled ? 1 : 0,
                    ...(offset === null ? {} : { occurrence_offset: offset }),
                    ...(highlight === null ? {} : { highlight }),
                },
            },
        ),
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        throw new Error('Unable to load occurrences');
    }

    return response.json();
}
async function loadYear(
    year: number,
    offset: number | null = null,
    replace = false,
    highlight: string | null = null,
): Promise<void> {
    if (loadingYears.value.includes(year)) {
        return;
    }

    const generation = loadGeneration;
    loadingYears.value.push(year);
    failedYears.value = failedYears.value.filter((value) => value !== year);

    try {
        const chunk = await fetchYear(year, offset, highlight);

        if (generation !== loadGeneration) {
            return;
        }

        const existing = replace ? [] : (yearRows.value[year] ?? []);
        yearRows.value[year] = [
            ...new Map(
                [...existing, ...chunk.occurrences].map((row) => [
                    row.uuid,
                    row,
                ]),
            ).values(),
        ].sort(
            (a, b) =>
                (a.due_date ?? a.expected_date ?? '').localeCompare(
                    b.due_date ?? b.expected_date ?? '',
                ) || a.sequence_number - b.sequence_number,
        );

        if (!existing.length || chunk.offset >= (yearFirst.value[year] ?? 0)) {
            yearNext.value[year] = chunk.next_offset;
        }

        yearFirst.value[year] = Math.min(
            replace ? chunk.offset : (yearFirst.value[year] ?? chunk.offset),
            chunk.offset,
        );
    } catch {
        if (generation === loadGeneration) {
            retryOffsets.value[year] = offset ?? 0;
            failedYears.value.push(year);
        }
    } finally {
        if (generation === loadGeneration) {
            loadingYears.value = loadingYears.value.filter(
                (value) => value !== year,
            );
        }
    }
}
function toggleYear(year: number): void {
    if (openYears.value.includes(year)) {
        openYears.value = openYears.value.filter((value) => value !== year);
    } else {
        openYears.value.push(year);

        if (!yearRows.value[year]) {
            void loadYear(year);
        }
    }
}
function toggleSkippedOccurrence(
    occurrence: (typeof props.recurringEntry.occurrences)[number],
): void {
    if (!occurrence.can_skip && !occurrence.can_restore) {
        return;
    }

    const action = occurrence.can_restore ? restoreOccurrence : skipOccurrence;
    router.patch(
        action.url([occurrenceOwner(occurrence.uuid), occurrence.uuid]),
        {},
        { preserveScroll: true },
    );
}
const page = usePage();
const { t, locale } = useI18n();

const formOpen = ref(false);
const reactivationMode = ref(false);
function openReactivation(): void {
    reactivationMode.value = true;
    formOpen.value = true;
}

if (
    typeof window !== 'undefined' &&
    new URLSearchParams(window.location.search).get('reactivate') === '1' &&
    props.recurringEntry.actions.can_reactivate
) {
    openReactivation();
}

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
                category_uuid: occurrence.payment_defaults
                    ? occurrence.payment_defaults.category_uuid
                    : (entry.value.category?.uuid ?? null),
                tracked_item_uuid: occurrence.payment_defaults
                    ? occurrence.payment_defaults.tracked_item_uuid
                    : (entry.value.tracked_item?.uuid ?? null),
                direction: entry.value.direction as 'income' | 'expense',
                description:
                    props.compound && occurrence.component_name
                        ? `${props.compound.name} · ${occurrence.component_name}`
                        : entry.value.title,
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
function occurrenceCounterparty(occurrence: Occurrence) {
    return occurrence.payment_defaults
        ? occurrence.payment_defaults.shared_expense_counterparty
        : entry.value.shared_expense_counterparty;
}
const linkPaymentOpen = ref(false);
const linkPaymentOccurrence = ref<Occurrence | null>(null);
const linkPaymentLoading = ref(false);
const linkPaymentFailed = ref(false);
const candidatePage = ref(1);
const candidateHasMore = ref(false);
const candidateFrom = ref('');
const candidateTo = ref('');
const paymentCandidatesList = ref<
    Array<{
        uuid: string;
        date: string;
        amount: number;
        description: string | null;
        account: string | null;
    }>
>([]);
const linkPaymentForm = useForm({ transaction_uuid: '', amount: '' });
async function loadPaymentCandidates(pageNumber = 1): Promise<void> {
    if (!linkPaymentOccurrence.value || linkPaymentLoading.value) {
        return;
    }

    linkPaymentLoading.value = true;
    linkPaymentFailed.value = false;

    try {
        const response = await fetch(
            paymentCandidates.url(
                [
                    occurrenceOwner(linkPaymentOccurrence.value.uuid),
                    linkPaymentOccurrence.value.uuid,
                ],
                {
                    query: {
                        page: pageNumber,
                        date_from: candidateFrom.value || undefined,
                        date_to: candidateTo.value || undefined,
                    },
                },
            ),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Unable to load payments');
        }

        const result = await response.json();
        paymentCandidatesList.value =
            pageNumber === 1
                ? result.transactions
                : [...paymentCandidatesList.value, ...result.transactions];
        candidateHasMore.value = result.has_more;
        candidatePage.value = pageNumber;
    } catch {
        linkPaymentFailed.value = true;
    } finally {
        linkPaymentLoading.value = false;
    }
}
function openLinkPayment(occurrence: Occurrence): void {
    linkPaymentOccurrence.value = occurrence;
    paymentCandidatesList.value = [];
    candidateFrom.value = '';
    candidateTo.value = '';
    linkPaymentForm.reset();
    linkPaymentForm.clearErrors();
    linkPaymentOpen.value = true;
    void loadPaymentCandidates();
}
function chooseExistingPayment(payment: {
    uuid: string;
    amount: number;
}): void {
    linkPaymentForm.transaction_uuid = payment.uuid;
    linkPaymentForm.amount = String(
        Math.min(
            payment.amount,
            linkPaymentOccurrence.value?.remaining_amount ?? 0,
        ),
    );
}
function submitLinkPayment(): void {
    if (!linkPaymentOccurrence.value) {
        return;
    }

    linkPaymentForm.post(
        allocatePayment.url([
            occurrenceOwner(linkPaymentOccurrence.value.uuid),
            linkPaymentOccurrence.value.uuid,
        ]),
        {
            preserveScroll: true,
            onSuccess: () => {
                linkPaymentOpen.value = false;
            },
        },
    );
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
const sharedPaymentCounterparty = computed(() =>
    sharedPaymentOccurrence.value
        ? occurrenceCounterparty(sharedPaymentOccurrence.value)
        : null,
);
const sharedPaymentForm = useForm({
    shared_expense_paid_by: 'self',
    amount: '',
    transaction_date: '',
    account_uuid: '',
    confirm_future_date: true,
});
function submitSharedPayment(): void {
    if (!sharedPaymentOccurrence.value || sharedPaymentForm.processing) {
        return;
    }

    sharedPaymentForm.post(
        convertOccurrence.url([
            occurrenceOwner(sharedPaymentOccurrence.value.uuid),
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
    loadedOccurrences.value.some(
        (occurrence) =>
            occurrence.uuid === undoConversionOccurrenceUuid.value &&
            occurrence.external_payment != null &&
            (!occurrence.converted_transaction ||
                occurrence.converted_transaction.is_refunded),
    ),
);
const entry = computed(() => props.recurringEntry.entry);
type AnnualComponent = NonNullable<
    RecurringEntryShowPageProps['compound']
>['components'][number];
type ComponentDetail = RecurringEntryShowPageProps['recurringEntry'];
type ComponentAction =
    | 'edit'
    | 'interrupt'
    | 'reactivate'
    | 'pause'
    | 'recover'
    | 'cancel'
    | 'remove';
const componentDetails = ref<Record<string, ComponentDetail>>({});
const componentLoadFailed = ref<string[]>([]);
const componentActionPending = ref<string | null>(null);
let componentParentUuid: string | null = null;
const componentRequests = new Set<string>();
async function loadComponentDetails(component: AnnualComponent): Promise<void> {
    if (componentRequests.has(component.entry_uuid)) {
        return;
    }

    const parentUuid = props.compound?.uuid;
    componentRequests.add(component.entry_uuid);
    componentLoadFailed.value = componentLoadFailed.value.filter(
        (uuid) => uuid !== component.entry_uuid,
    );

    try {
        const response = await fetch(component.url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load component');
        }

        const payload: RecurringEntryShowPageProps = await response.json();

        if (
            props.compound?.uuid === parentUuid &&
            payload.recurringEntry.entry.uuid === component.entry_uuid
        ) {
            componentDetails.value[component.entry_uuid] =
                payload.recurringEntry;
        }
    } catch {
        if (props.compound?.uuid === parentUuid) {
            componentLoadFailed.value.push(component.entry_uuid);
        }
    } finally {
        componentRequests.delete(component.entry_uuid);
    }
}
watch(
    () => props.recurringEntry,
    (payload) => {
        const parentUuid = props.compound?.uuid ?? null;

        if (parentUuid !== componentParentUuid) {
            componentDetails.value = {};
            componentLoadFailed.value = [];
            componentParentUuid = parentUuid;
        }

        if (!props.compound) {
            return;
        }

        componentDetails.value[payload.entry.uuid] = payload;

        for (const component of props.compound.components) {
            if (!componentDetails.value[component.entry_uuid]) {
                void loadComponentDetails(component);
            }
        }
    },
    { immediate: true },
);
function annualComponentDate(component: ComponentDetail['entry']): string {
    const rule = component.recurrence_rule as {
        mode?: string;
        month?: number;
        day?: number;
    } | null;

    if (rule?.day && rule.month) {
        return new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'long',
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(2000, rule.month - 1, rule.day)));
    }

    return component.start_date ?? '—';
}
function componentStatusLabel(status: string | null): string {
    return (
        props.formOptions.statuses.find((option) => option.value === status)
            ?.label ??
        status ??
        '—'
    );
}
function runComponentAction(
    component: AnnualComponent,
    action: ComponentAction,
): void {
    if (componentActionPending.value !== null) {
        return;
    }

    const perform = (): void => {
        if (
            entry.value.uuid !== component.entry_uuid ||
            !entry.value.can_edit ||
            props.compound?.is_archived
        ) {
            return;
        }

        const permissions = props.recurringEntry.actions;

        if (action === 'edit') {
            reactivationMode.value = false;
            formOpen.value = true;
        } else if (action === 'interrupt' && permissions.can_interrupt) {
            router.patch(
                interrupt.url(entry.value.uuid),
                {},
                { preserveScroll: true },
            );
        } else if (action === 'reactivate' && permissions.can_reactivate) {
            openReactivation();
        } else if (action === 'pause' && permissions.can_pause) {
            handlePause();
        } else if (action === 'recover' && permissions.can_recover_arrears) {
            resumeDialogOpen.value = true;
        } else if (action === 'cancel' && permissions.can_cancel) {
            cancelDialogOpen.value = true;
        } else if (action === 'remove') {
            archiveComponentOpen.value = true;
        }
    };

    if (entry.value.uuid === component.entry_uuid) {
        perform();

        return;
    }

    componentActionPending.value = component.entry_uuid;
    router.visit(component.url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: perform,
        onFinish: () => {
            componentActionPending.value = null;
        },
    });
}

const focusedOccurrenceUuid = ref<string | null>(null);
const highlightedOccurrenceUuid = computed(() => {
    const query = page.url.split('?')[1] ?? '';

    return (
        focusedOccurrenceUuid.value ??
        new URLSearchParams(query).get('highlight')
    );
});
const annualProgressRows = ref<Occurrence[] | null>(null);
const annualProgressFailed = ref(false);
const currentProgressYear = computed(() =>
    Number(props.dateOptions.today.slice(0, 4)),
);
const annualProgress = computed(() =>
    annualProgressRows.value === null
        ? null
        : annualOccurrencesProgress(
              annualProgressRows.value,
              currentProgressYear.value,
          ),
);
watch(
    () => props.recurringEntry,
    async (payload, _previous, onCleanup) => {
        if (!props.compound) {
            return;
        }

        let cancelled = false;
        onCleanup(() => {
            cancelled = true;
        });
        annualProgressRows.value = null;
        annualProgressFailed.value = false;
        const initial = payload.occurrence_page;

        if (
            initial.year === currentProgressYear.value &&
            initial.offset === 0 &&
            initial.next_offset === null
        ) {
            annualProgressRows.value = payload.occurrences;

            return;
        }

        try {
            const rows: Occurrence[] = [];
            let offset: number | null = 0;

            do {
                const chunk = await fetchYear(
                    currentProgressYear.value,
                    offset,
                    null,
                    false,
                );

                if (cancelled) {
                    return;
                }

                rows.push(...chunk.occurrences);
                offset = chunk.next_offset;
            } while (offset !== null);

            annualProgressRows.value = rows;
        } catch {
            if (!cancelled) {
                annualProgressFailed.value = true;
            }
        }
    },
    { immediate: true },
);
const progressSettled = computed(() =>
    props.compound
        ? (annualProgress.value?.paid ?? 0)
        : (props.recurringEntry.summary.current_period_settled_occurrences ??
          props.recurringEntry.summary.settled_occurrences),
);
const planProgress = computed(() =>
    props.compound
        ? {
              openEnded: false,
              total: annualProgress.value?.total ?? null,
              value: annualProgress.value?.value ?? '—',
              percentage: annualProgress.value?.percentage ?? null,
          }
        : recurringPlanProgress(entry.value, progressSettled.value),
);
const progressTotal = computed(() => planProgress.value.total);
const automaticNotePreferences = useStorage<Record<string, boolean>>(
    'recurring-automatic-note-preferences',
    {},
);
const automaticNoteKey = computed(
    () =>
        `${auth.value.user?.uuid ?? auth.value.user?.id ?? 'guest'}:${entry.value.uuid}`,
);
const automaticNoteOpen = computed(
    () => automaticNotePreferences.value[automaticNoteKey.value] ?? false,
);
function toggleAutomaticNote(event: Event): void {
    const expanded = (event.currentTarget as HTMLDetailsElement).open;

    if (expanded !== automaticNoteOpen.value) {
        automaticNotePreferences.value = {
            ...automaticNotePreferences.value,
            [automaticNoteKey.value]: expanded,
        };
    }
}

const automaticOccurrencesNoteVisible = computed(
    () =>
        planProgress.value.openEnded &&
        entry.value.auto_generate_occurrences &&
        entry.value.is_active &&
        entry.value.status === 'active',
);
const todayTargetYear = computed(() =>
    Number(
        (
            props.recurringEntry.summary.nearest_occurrence?.date ??
            props.dateOptions.today
        ).slice(0, 4),
    ),
);

async function focusOccurrence(uuid: string): Promise<void> {
    await nextTick();
    const rows = Array.from(
        document.querySelectorAll<HTMLElement>(
            `[data-occurrence-uuid="${uuid}"]`,
        ),
    );
    const visibleRow = rows.find((row) => row.offsetParent !== null);
    visibleRow?.focus({ preventScroll: true });
    visibleRow?.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto'
            : 'smooth',
        block: 'center',
    });
}

async function goToToday(): Promise<void> {
    const target = props.recurringEntry.summary.nearest_occurrence;
    const year = todayTargetYear.value;

    if (loadingYears.value.includes(year)) {
        return;
    }

    openYears.value = [year];
    await loadYear(year, null, true, target?.uuid ?? null);

    if (target && !failedYears.value.includes(year)) {
        focusedOccurrenceUuid.value = target.uuid;
        await focusOccurrence(target.uuid);
    }
}

function occurrenceAmountLabel(label: string): string {
    if (label === 'amountPaid') {
        return entry.value.direction === 'income'
            ? t('recurringDetail.alreadyReceived')
            : ui('Già pagato');
    }

    return label === 'amountDue' ? ui('Totale') : ui('Residuo');
}

function summaryDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T12:00:00`));
}
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
        distributionRows.value.filter(
            (occurrence) => occurrence.converted_transaction !== null,
        ).length,
);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.recurring.title'),
        href: '/recurring-entries',
    },
    {
        title: pageTitle.value,
        href: props.compound?.url ?? entry.value.show_url,
    },
]);

const summaryCards = computed(() => {
    const summary = props.recurringEntry.summary;
    const income = entry.value.direction === 'income';
    const period =
        summary.paid_year === null
            ? t('recurringDetail.allTime')
            : t('recurringDetail.year', { year: summary.paid_year });

    return [
        {
            key: 'paid',
            label: t(
                income ? 'recurringDetail.received' : 'recurringDetail.paid',
            ),
            value: formatMoney(summary.paid_amount),
            money: true,
            description: period,
            detail: t('recurringDetail.settledCount', {
                count: summary.paid_count,
            }),
            tone: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            icon: income ? ArrowDownLeft : ArrowUpRight,
        },
        {
            key: 'remaining',
            label: t(
                income ? 'recurringDetail.toReceive' : 'recurringDetail.toPay',
            ),
            value: formatMoney(summary.remaining_amount),
            money: true,
            description: t('recurringDetail.pendingCount', {
                count: summary.pending_occurrences,
            }),
            detail:
                summary.overdue_count > 0
                    ? t('recurringDetail.overdue', {
                          count: summary.overdue_count,
                      })
                    : summary.generated_until
                      ? t('recurringDetail.preparedUntil', {
                            date: summaryDate(summary.generated_until),
                        })
                      : '',
            tone: income
                ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300'
                : 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
            icon: Receipt,
        },
        {
            key: 'progress',
            label: props.compound
                ? t('recurringDetail.annualOccurrences')
                : entry.value.has_activity_periods
                  ? ui('Avanzamento del periodo attuale')
                  : t('recurringDetail.progress'),
            value: planProgress.value.value,
            money: false,
            description: props.compound
                ? t('recurringDetail.year', { year: currentProgressYear.value })
                : planProgress.value.openEnded
                  ? t('recurringDetail.openEnded')
                  : progressTotal.value !== null
                    ? t('recurringDetail.finiteProgress', {
                          count: progressSettled.value,
                          total: progressTotal.value,
                      })
                    : entry.value.end_date
                      ? t('recurringDetail.untilDate', {
                            date: summaryDate(entry.value.end_date),
                        })
                      : '',
            detail: props.compound
                ? annualProgress.value
                    ? t('recurringDetail.annualPaid', {
                          count: annualProgress.value.paid,
                          total: annualProgress.value.total,
                      })
                    : annualProgressFailed.value
                      ? ui('Caricamento non riuscito. Riprova.')
                      : ui('Caricamento…')
                : !entry.value.is_active
                  ? ui(
                        'Generazione interrotta; le scadenze già dovute restano da gestire.',
                    )
                  : planProgress.value.openEnded
                    ? t('recurringDetail.ongoing')
                    : t('recurringDetail.allTime'),
            tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            icon: planProgress.value.openEnded ? InfinityIcon : ShieldCheck,
        },
        {
            key: 'next',
            label: t(
                income
                    ? 'recurringDetail.nextIncome'
                    : 'recurringDetail.nextExpense',
            ),
            value: summary.next_date ? summaryDate(summary.next_date) : '—',
            money: false,
            description:
                summary.next_amount !== null
                    ? formatMoney(summary.next_amount)
                    : t('recurringDetail.noUpcoming'),
            detail: '',
            tone: 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            icon: CalendarClock,
        },
    ];
});

function frequencyLabel(type: string, interval: number): string {
    return interval === 1
        ? t(`transactions.recurring.form.repeatPresets.${type}`)
        : `${interval} ${t(`transactions.recurring.pauseUnits.${type}`)}`;
}

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

    if (occurrence.status === 'cancelled') {
        return {
            label: ui('Annullata'),
            tone: 'bg-muted text-muted-foreground',
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
            label: ui('Parzialmente pagata'),
            tone: 'bg-primary/10 text-primary',
        };
    }

    if (occurrence.settlement_status === 'amount_confirmation') {
        return {
            label: ui('Importo da confermare'),
            tone: 'bg-primary/10 text-primary',
        };
    }

    if (occurrence.settlement_status === 'settled') {
        return {
            label:
                entry.value.direction === 'income'
                    ? t('recurringDetail.receivedStatus')
                    : ui('Pagata'),
            tone: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
        };
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
            label: occurrenceCounterparty(occurrence)
                ? 'Pagata da te'
                : t('transactions.recurring.table.convertedBadge'),
            tone: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        };
    }

    return {
        label: ui('Prevista'),
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

async function openInstallmentDistribution(): Promise<void> {
    if (distributionLoading.value) {
        return;
    }

    distributionLoading.value = true;

    try {
        const rows: Occurrence[] = [];

        for (const group of occurrenceYears.value) {
            let offset: number | null = 0;

            while (offset !== null) {
                const chunk: YearChunk = await fetchYear(group.year, offset);
                rows.push(...chunk.occurrences);
                offset = chunk.next_offset;
            }
        }

        distributionRows.value = rows.sort(
            (a, b) => a.sequence_number - b.sequence_number,
        );
        installmentAmounts.value = distributionRows.value.map((occurrence) => ({
            uuid: occurrence.uuid,
            amount: String(occurrence.expected_amount ?? ''),
        }));
        installmentDistributionDialogOpen.value = true;
    } catch {
        failedYears.value = [Number(props.dateOptions.today.slice(0, 4))];
    } finally {
        distributionLoading.value = false;
    }
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

const convertingOccurrenceUuid = ref<string | null>(null);

function handleConvert(occurrenceUuid: string): void {
    if (convertingOccurrenceUuid.value !== null) {
        return;
    }

    convertingOccurrenceUuid.value = occurrenceUuid;
    router.post(
        convertOccurrence.url([
            occurrenceOwner(occurrenceUuid),
            occurrenceUuid,
        ]),
        {
            confirm_future_date: true,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                convertingOccurrenceUuid.value = null;
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
    amountForm.patch(
        updateAmount.url([occurrenceOwner(occurrenceUuid), occurrenceUuid]),
        {
            preserveScroll: true,
            onSuccess: cancelAmountEdit,
        },
    );
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
        updateOccurrence.url([
            occurrenceOwner(editingOccurrence.value.uuid),
            editingOccurrence.value.uuid,
        ]),
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
        occurrenceCounterparty(occurrence) ||
        occurrence.converted_transaction?.is_refunded
    ) {
        sharedPaymentForm.clearErrors();
        sharedPaymentForm.shared_expense_paid_by = occurrence.external_payment
            ? 'counterparty'
            : occurrence.payment_defaults
              ? (occurrence.payment_defaults.shared_expense_paid_by ?? 'self')
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
            occurrenceOwner(undoConversionOccurrenceUuid.value),
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

const occurrenceActionsMounted = ref(false);
const handledOccurrenceAction = ref<string | null>(null);
watch(
    () =>
        [
            page.url,
            loadedOccurrences.value,
            occurrenceActionsMounted.value,
        ] as const,
    () => {
        if (
            !occurrenceActionsMounted.value ||
            handledOccurrenceAction.value === page.url
        ) {
            return;
        }

        const occurrence = loadedOccurrences.value.find(
            (row) => row.uuid === highlightedOccurrenceUuid.value,
        );
        const action = requestedOccurrenceAction(
            page.url,
            Boolean(props.compound),
            occurrence,
        );

        if (!action || !occurrence) {
            return;
        }

        handledOccurrenceAction.value = page.url;

        if (action === 'date') {
            startOccurrenceEdit(occurrence);
        }

        if (action === 'amount') {
            startAmountEdit(occurrence);
        }

        if (action === 'payment') {
            registerPayment(occurrence);
        }
    },
    { immediate: true, flush: 'post' },
);

onMounted(() => {
    occurrenceActionsMounted.value = true;
    window.addEventListener(
        'app:mobile-primary-action',
        handleMobilePrimaryAction as EventListener,
    );

    if (highlightedOccurrenceUuid.value) {
        void focusOccurrence(highlightedOccurrenceUuid.value);
    }
});

onBeforeUnmount(() => {
    loadGeneration++;
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
        :title="`${pageTitle} · ${t('transactions.recurring.detail.title')}`"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 px-4 py-5 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-[30px] border border-white/70 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_36%),linear-gradient(135deg,rgba(255,255,255,0.97),rgba(248,250,252,0.93))] shadow-sm dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.12),_transparent_36%),linear-gradient(135deg,rgba(2,6,23,0.95),rgba(15,23,42,0.91))]"
            >
                <div class="grid gap-6 p-4 sm:p-5 lg:p-7">
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
                                v-if="
                                    ['cancelled', 'paused'].includes(
                                        entry.status ?? '',
                                    )
                                "
                                variant="outline"
                                class="rounded-full px-3 py-1"
                                >{{
                                    entry.status === 'cancelled'
                                        ? ui('Piano annullato')
                                        : ui('Interruzione')
                                }}</Badge
                            >
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
                                data-onboarding="recurring-detail.step1"
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ pageTitle }}
                            </h1>
                            <p
                                v-if="!compound"
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

                        <div
                            v-if="compound"
                            class="flex flex-wrap gap-2"
                            data-compound-plan-actions
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                @click="costTrendPanel?.open()"
                            >
                                <ChartNoAxesCombined class="mr-2 size-4" />
                                {{ t('recurringDetail.costTrend.action') }}
                            </Button>
                            <Button
                                v-if="
                                    Object.values(componentDetails).some(
                                        (detail) => detail.entry.can_edit,
                                    )
                                "
                                variant="outline"
                                size="sm"
                                @click="addComponentOpen = true"
                                >{{ t('recurringDetail.compound.add') }}</Button
                            >
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <Button
                                v-if="hasCostTrend"
                                variant="outline"
                                size="sm"
                                class="rounded-2xl"
                                @click="costTrendPanel?.open()"
                            >
                                <ChartNoAxesCombined class="mr-2 size-4" />
                                {{ t('recurringDetail.costTrend.action') }}
                            </Button>
                            <Button
                                v-if="entry.can_edit"
                                class="rounded-2xl"
                                @click="
                                    reactivationMode = false;
                                    formOpen = true;
                                "
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
                                :disabled="distributionLoading"
                                @click="openInstallmentDistribution"
                            >
                                {{ ui('Modifica importi rate') }}
                            </Button>
                            <Button
                                v-if="
                                    props.recurringEntry.actions.can_reactivate
                                "
                                variant="outline"
                                class="rounded-2xl"
                                @click="openReactivation"
                                ><Play class="mr-2 size-4" />{{
                                    ui('Riattiva dalla data scelta')
                                }}</Button
                            >
                            <Button
                                v-if="
                                    props.recurringEntry.actions.can_interrupt
                                "
                                variant="outline"
                                class="rounded-2xl"
                                @click="
                                    router.patch(
                                        interrupt.url(entry.uuid),
                                        {},
                                        { preserveScroll: true },
                                    )
                                "
                                >{{
                                    ui('Interrompi fino alla riattivazione')
                                }}</Button
                            >
                            <Button
                                v-if="props.recurringEntry.actions.can_pause"
                                variant="outline"
                                class="rounded-2xl"
                                @click="handlePause"
                            >
                                <Pause class="mr-2 size-4" />
                                {{
                                    entry.entry_type === 'recurring'
                                        ? ui('Salta prossime scadenze')
                                        : t(
                                              'transactions.recurring.actions.pause',
                                          )
                                }}
                            </Button>
                            <Button
                                v-if="
                                    props.recurringEntry.actions
                                        .can_recover_arrears
                                "
                                variant="outline"
                                class="rounded-2xl"
                                @click="resumeDialogOpen = true"
                            >
                                <Play class="mr-2 size-4" />
                                {{ ui('Recupera arretrati (legacy)') }}
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

                    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.key"
                            class="relative flex min-w-0 flex-col rounded-3xl border border-white/70 bg-white/90 p-3 shadow-sm sm:p-5 dark:border-white/10 dark:bg-slate-950/70"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p
                                        class="pr-8 text-xs font-medium text-slate-500 sm:pr-12 sm:text-sm dark:text-slate-400"
                                    >
                                        {{ card.label }}
                                    </p>
                                    <p
                                        class="pt-2 text-lg font-semibold tracking-tight whitespace-nowrap text-slate-950 tabular-nums sm:text-2xl dark:text-white"
                                    >
                                        <SensitiveValue
                                            v-if="card.money"
                                            variant="veil"
                                            :value="card.value"
                                        />
                                        <template v-else>
                                            {{ card.value }}
                                        </template>
                                    </p>
                                    <p
                                        class="pt-1 text-xs font-medium text-slate-600 sm:text-sm dark:text-slate-300"
                                    >
                                        <SensitiveValue
                                            v-if="
                                                card.key === 'next' &&
                                                recurringEntry.summary
                                                    .next_amount !== null
                                            "
                                            :value="card.description"
                                        />
                                        <template v-else>{{
                                            card.description
                                        }}</template>
                                    </p>
                                </div>
                                <div
                                    class="absolute top-3 right-3 flex size-7 items-center justify-center rounded-xl sm:top-5 sm:right-5 sm:size-10 sm:rounded-2xl"
                                    :class="card.tone"
                                >
                                    <component
                                        :is="card.icon"
                                        class="size-4 sm:size-5"
                                    />
                                </div>
                            </div>
                            <div
                                v-if="
                                    card.key === 'progress' &&
                                    planProgress.percentage !== null
                                "
                                class="mt-4 h-1.5 overflow-hidden rounded-full bg-sky-100 dark:bg-sky-950"
                                role="progressbar"
                                :aria-label="card.label"
                                :aria-valuenow="progressSettled"
                                :aria-valuemin="0"
                                :aria-valuemax="progressTotal ?? undefined"
                            >
                                <div
                                    class="h-full rounded-full bg-sky-500 transition-[width] duration-500"
                                    :style="{
                                        width: `${planProgress.percentage}%`,
                                    }"
                                />
                            </div>
                            <p
                                v-if="card.detail"
                                class="mt-4 border-t border-slate-100 pt-3 text-xs leading-relaxed text-muted-foreground dark:border-white/10"
                            >
                                {{ card.detail }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <details
                v-if="compound"
                class="group/components space-y-3 rounded-2xl border bg-card p-4 sm:p-6"
                :open="componentsOpen"
                @toggle="
                    componentsOpen = ($event.target as HTMLDetailsElement).open
                "
                aria-labelledby="annual-components-title"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                >
                    <div class="min-w-0">
                        <h2
                            id="annual-components-title"
                            class="text-lg font-semibold"
                        >
                            {{ t('recurringDetail.compound.annualComponents') }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >({{ compound.components.length }})</span
                            >
                        </h2>
                        <p
                            class="mt-1 text-sm text-primary"
                            role="status"
                            aria-live="polite"
                        >
                            {{
                                t('recurringDetail.compound.viewingComponent', {
                                    name: compound.active_component,
                                })
                            }}
                        </p>
                    </div>
                    <ChevronDown
                        aria-hidden="true"
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-open/components:rotate-180 motion-reduce:transition-none"
                    />
                </summary>
                <p class="text-sm text-muted-foreground">
                    {{ t('recurringDetail.compound.selectionHint') }}
                </p>
                <div class="divide-y">
                    <div
                        v-for="component in compound.components"
                        :key="component.entry_uuid"
                        class="flex flex-wrap items-center gap-x-4 gap-y-3 px-3 py-3 transition-colors motion-reduce:transition-none"
                        :class="
                            entry.uuid === component.entry_uuid
                                ? 'rounded-xl bg-primary/5 ring-1 ring-primary/30'
                                : ''
                        "
                        :data-component-uuid="component.entry_uuid"
                    >
                        <Link
                            :href="component.url"
                            preserve-scroll
                            preserve-state
                            @success="lifecycleOpen = true"
                            :aria-current="
                                entry.uuid === component.entry_uuid
                                    ? 'page'
                                    : undefined
                            "
                            class="min-w-0 flex-1 basis-40 rounded-lg focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{
                                    component.name
                                }}</span>
                                <Badge
                                    v-if="entry.uuid === component.entry_uuid"
                                    variant="outline"
                                    class="border-primary/30 text-primary"
                                    ><Check
                                        class="mr-1 size-3"
                                        aria-hidden="true"
                                    />{{
                                        t('recurringDetail.compound.selected')
                                    }}</Badge
                                >
                            </div>
                            <p
                                v-if="componentDetails[component.entry_uuid]"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    annualComponentDate(
                                        componentDetails[component.entry_uuid]
                                            .entry,
                                    )
                                }}
                                ·
                                <SensitiveValue
                                    :value="
                                        formatMoney(
                                            componentDetails[
                                                component.entry_uuid
                                            ].entry.expected_amount ?? 0,
                                            componentDetails[
                                                component.entry_uuid
                                            ].entry.currency,
                                        )
                                    "
                                />
                            </p>
                            <p
                                v-else
                                class="mt-1 text-sm text-muted-foreground"
                                role="status"
                            >
                                {{
                                    componentLoadFailed.includes(
                                        component.entry_uuid,
                                    )
                                        ? t(
                                              'recurringDetail.compound.loadFailed',
                                          )
                                        : ui('Caricamento…')
                                }}
                            </p>
                            <span
                                class="mt-1 block text-xs text-primary underline-offset-4 hover:underline"
                                >{{
                                    t('recurringDetail.compound.viewHistory')
                                }}</span
                            >
                        </Link>
                        <template v-if="componentDetails[component.entry_uuid]">
                            <Badge variant="secondary">{{
                                componentStatusLabel(
                                    componentDetails[component.entry_uuid].entry
                                        .status,
                                )
                            }}</Badge>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="
                                        componentDetails[component.entry_uuid]
                                            .entry.can_edit
                                    "
                                    variant="outline"
                                    size="sm"
                                    :disabled="componentActionPending !== null"
                                    @click="
                                        runComponentAction(component, 'edit')
                                    "
                                    >{{ ui('Modifica') }}</Button
                                >
                                <DropdownMenu
                                    v-if="
                                        componentDetails[component.entry_uuid]
                                            .entry.can_edit
                                    "
                                >
                                    <DropdownMenuTrigger as-child
                                        ><Button
                                            variant="ghost"
                                            size="sm"
                                            :disabled="
                                                componentActionPending !== null
                                            "
                                            :aria-label="
                                                t(
                                                    'recurringDetail.compound.moreActions',
                                                ) +
                                                ' · ' +
                                                component.name
                                            "
                                            >{{
                                                t(
                                                    'recurringDetail.compound.moreActions',
                                                )
                                            }}</Button
                                        ></DropdownMenuTrigger
                                    >
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            v-if="
                                                componentDetails[
                                                    component.entry_uuid
                                                ].actions.can_interrupt
                                            "
                                            @select="
                                                runComponentAction(
                                                    component,
                                                    'interrupt',
                                                )
                                            "
                                            >{{
                                                ui(
                                                    'Interrompi fino alla riattivazione',
                                                )
                                            }}</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            v-if="
                                                componentDetails[
                                                    component.entry_uuid
                                                ].actions.can_reactivate
                                            "
                                            @select="
                                                runComponentAction(
                                                    component,
                                                    'reactivate',
                                                )
                                            "
                                            >{{
                                                ui('Riattiva dalla data scelta')
                                            }}</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            v-if="
                                                componentDetails[
                                                    component.entry_uuid
                                                ].actions.can_pause
                                            "
                                            @select="
                                                runComponentAction(
                                                    component,
                                                    'pause',
                                                )
                                            "
                                            >{{
                                                ui('Salta prossime scadenze')
                                            }}</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            v-if="
                                                componentDetails[
                                                    component.entry_uuid
                                                ].actions.can_recover_arrears
                                            "
                                            @select="
                                                runComponentAction(
                                                    component,
                                                    'recover',
                                                )
                                            "
                                            >{{
                                                ui(
                                                    'Recupera arretrati (legacy)',
                                                )
                                            }}</DropdownMenuItem
                                        >
                                        <DropdownMenuItem
                                            v-if="
                                                componentDetails[
                                                    component.entry_uuid
                                                ].actions.can_cancel
                                            "
                                            @select="
                                                runComponentAction(
                                                    component,
                                                    'cancel',
                                                )
                                            "
                                            >{{
                                                t(
                                                    'recurringDetail.compound.cancelIndividual',
                                                )
                                            }}</DropdownMenuItem
                                        >
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            @select="
                                                runComponentAction(
                                                    component,
                                                    'remove',
                                                )
                                            "
                                            >{{
                                                t(
                                                    'recurringDetail.compound.removeComponent',
                                                )
                                            }}</DropdownMenuItem
                                        >
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </template>
                        <Button
                            v-else-if="
                                componentLoadFailed.includes(
                                    component.entry_uuid,
                                )
                            "
                            variant="ghost"
                            size="sm"
                            @click="loadComponentDetails(component)"
                            >{{ t('app.pwa.retry') }}</Button
                        >
                    </div>
                </div>
                <details
                    v-if="compound.archived_components.length"
                    class="text-sm"
                >
                    <summary class="cursor-pointer py-2 text-muted-foreground">
                        {{
                            t('recurringDetail.compound.archivedComponents', {
                                count: compound.archived_components.length,
                            })
                        }}
                    </summary>
                    <div class="flex flex-wrap gap-2 py-2">
                        <Link
                            v-for="component in compound.archived_components"
                            :key="component.entry_uuid"
                            :href="component.url"
                            class="rounded-xl border px-3 py-2"
                            >{{ component.name }}</Link
                        >
                    </div>
                </details>
                <p
                    v-if="compound.is_archived"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        t('recurringDetail.compound.archivedReadOnly', {
                            name: compound.active_component,
                        })
                    }}
                </p>
            </details>

            <details
                v-if="recurringEntry.activity_periods?.length"
                class="group/lifecycle space-y-3 rounded-2xl border p-4 sm:p-6"
                :open="!compound || lifecycleOpen"
                @toggle="
                    lifecycleOpen = ($event.target as HTMLDetailsElement).open
                "
                aria-labelledby="plan-lifecycle-title"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden"
                >
                    <div>
                        <h2
                            data-onboarding="recurring-detail.step2"
                            id="plan-lifecycle-title"
                            class="font-semibold"
                        >
                            {{
                                compound
                                    ? t(
                                          'recurringDetail.compound.historyComponent',
                                          {
                                              name: compound.active_component,
                                          },
                                      )
                                    : ui('Cronologia del piano')
                            }}
                        </h2>
                        <p
                            v-if="compound"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{
                                t('recurringDetail.compound.historyHint', {
                                    name: compound.active_component,
                                })
                            }}
                        </p>
                    </div>
                    <ChevronDown
                        aria-hidden="true"
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-open/lifecycle:rotate-180 motion-reduce:transition-none"
                    />
                </summary>
                <article
                    v-for="(
                        period, periodIndex
                    ) in recurringEntry.activity_periods"
                    :key="period.id"
                    class="space-y-2 border-l-2 border-primary/30 pl-4 text-sm"
                >
                    <p
                        v-if="period.starts_on"
                        class="flex flex-wrap items-center gap-1.5"
                    >
                        <Badge variant="outline" class="bg-primary/5">{{
                            period.kind === 'created'
                                ? ui('Piano creato')
                                : ui('Piano riattivato')
                        }}</Badge>
                        <time
                            class="whitespace-nowrap"
                            :datetime="period.starts_on"
                            >{{ summaryDate(period.starts_on) }}</time
                        >
                        ·
                        {{
                            formatMoney(
                                Number(period.terms.expected_amount),
                                period.terms.currency,
                            )
                        }}
                        ·
                        {{
                            frequencyLabel(
                                period.terms.recurrence_type,
                                period.terms.recurrence_interval,
                            )
                        }}
                    </p>
                    <p
                        v-else-if="period.kind === 'recovered'"
                        class="text-muted-foreground"
                    >
                        {{ ui('Recupera arretrati (legacy)') }} ·
                        {{ period.recorded_on }}
                    </p>
                    <p v-else class="text-muted-foreground">
                        {{
                            ui(
                                'Periodo precedente: decorrenza storica non ricostruita',
                            )
                        }}
                    </p>
                    <p
                        v-if="period.ended_on"
                        class="flex flex-wrap items-center gap-1.5"
                    >
                        <Badge variant="outline">{{
                            period.ended_reason === 'cancelled'
                                ? ui('Piano annullato')
                                : ui('Interruzione')
                        }}</Badge>
                        <time
                            class="whitespace-nowrap"
                            :datetime="period.ended_on"
                            >{{ summaryDate(period.ended_on) }}</time
                        >
                        ·
                        {{ ui('Periodo di interruzione') }}
                        <span class="whitespace-nowrap">{{
                            summaryDate(period.ended_on)
                        }}</span>
                        →
                        {{
                            recurringEntry.activity_periods?.[periodIndex + 1]
                                ?.starts_on
                                ? summaryDate(
                                      recurringEntry.activity_periods[
                                          periodIndex + 1
                                      ].starts_on!,
                                  )
                                : ui('In corso')
                        }}
                    </p>
                </article>
                <p class="text-xs text-muted-foreground">
                    {{
                        ui(
                            'Le scadenze già dovute e i pagamenti precedenti restano nello storico.',
                        )
                    }}
                </p>
            </details>

            <section
                v-if="recurringEntry.amount_history?.length"
                data-testid="plan-amount-history"
                class="space-y-3 rounded-2xl border bg-muted/30 p-4"
            >
                <button
                    type="button"
                    class="flex w-full flex-wrap items-center justify-between gap-3 text-left"
                    :aria-expanded="historyOpen"
                    aria-controls="amount-history-details"
                    @click="historyOpen = !historyOpen"
                >
                    <span class="space-y-1">
                        <span class="block text-base font-semibold">{{
                            t('transactions.recurring.detail.amountHistory')
                        }}</span>
                        <span class="block text-sm"
                            >{{
                                entry.is_active
                                    ? ui('Importo corrente')
                                    : ui('Ultimo prezzo conosciuto')
                            }}
                            <SensitiveValue
                                class="font-semibold"
                                :value="formatMoney(entry.expected_amount ?? 0)"
                            />
                            ·
                            {{
                                ui('{count} variazioni', {
                                    count: recurringEntry.amount_history.length,
                                })
                            }}</span
                        >
                        <span
                            v-if="latestAmountChange"
                            class="block text-xs text-muted-foreground"
                            >{{ ui('Ultima variazione') }}:
                            <SensitiveValue
                                :value="`${latestAmountChange.difference > 0 ? '+' : ''}${formatMoney(latestAmountChange.difference, latestAmountChange.currency)}`"
                            />
                            ·
                            {{
                                new Date(
                                    `${latestAmountChange.effective_from}T00:00:00`,
                                ).toLocaleDateString(locale)
                            }}</span
                        >
                    </span>
                    <span class="text-sm font-medium text-primary">{{
                        ui(
                            historyOpen
                                ? 'Chiudi storico importi'
                                : 'Vedi storico importi',
                        )
                    }}</span>
                </button>
                <div
                    v-if="historyOpen"
                    id="amount-history-details"
                    class="space-y-3 border-t pt-3"
                >
                    <article
                        v-for="change in recurringEntry.amount_history"
                        :key="change.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-background p-3 text-sm"
                    >
                        <div class="space-y-1">
                            <p
                                class="flex flex-wrap items-center gap-2 font-semibold tabular-nums"
                            >
                                <SensitiveValue
                                    :value="
                                        formatMoney(
                                            change.previous_amount,
                                            change.currency,
                                        )
                                    "
                                /><span aria-hidden="true">→</span
                                ><SensitiveValue
                                    :value="
                                        formatMoney(
                                            change.new_amount,
                                            change.currency,
                                        )
                                    "
                                />
                            </p>
                            <p
                                v-if="
                                    change.recurrence_comparison &&
                                    !change.recurrence_comparison.comparable
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    frequencyLabel(
                                        change.recurrence_comparison
                                            .previous_type,
                                        change.recurrence_comparison
                                            .previous_interval,
                                    )
                                }}
                                →
                                {{
                                    frequencyLabel(
                                        change.recurrence_comparison.new_type,
                                        change.recurrence_comparison
                                            .new_interval,
                                    )
                                }}
                                ·
                                {{
                                    ui(
                                        'Periodicità diverse: percentuale non confrontabile',
                                    )
                                }}
                            </p>
                            <time
                                class="text-xs text-muted-foreground"
                                :datetime="change.effective_from"
                                >{{
                                    new Date(
                                        `${change.effective_from}T00:00:00`,
                                    ).toLocaleDateString(locale)
                                }}</time
                            >
                        </div>
                        <p
                            class="rounded-lg px-3 py-2 font-semibold tabular-nums"
                            :class="
                                change.difference > 0
                                    ? 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                                    : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                            "
                        >
                            <span aria-hidden="true">{{
                                change.difference > 0
                                    ? '↑'
                                    : change.difference < 0
                                      ? '↓'
                                      : '→'
                            }}</span>
                            <SensitiveValue
                                :value="`${change.difference > 0 ? '+' : ''}${formatMoney(change.difference, change.currency)}`"
                            />
                            <SensitiveValue
                                v-if="
                                    change.previous_amount !== 0 &&
                                    change.percentage !== null
                                "
                                :value="` (${change.percentage > 0 ? '+' : ''}${change.percentage.toLocaleString(locale, { maximumFractionDigits: 2 })}%)`"
                            />
                        </p>
                    </article>
                </div>
            </section>

            <RecurringCostTrend
                v-if="hasCostTrend"
                :key="compound?.uuid ?? entry.uuid"
                ref="costTrendPanel"
                :direction="entry.direction"
                :points="compound ? [] : recurringEntry.cost_trend"
                :annual-summaries="
                    compound
                        ? (recurringEntry.compound_cost_trend ?? [])
                        : undefined
                "
                :recurring-entry-uuid="entry.uuid"
                :manual-costs="compound ? [] : recurringEntry.manual_costs"
                :price-history="compound ? [] : recurringEntry.amount_history"
                :fixed="!compound && !entry.is_amount_variable"
                :can-edit="!compound && entry.can_edit"
                :currency="
                    entry.currency ?? auth.user?.base_currency_code ?? 'EUR'
                "
            />

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
                        data-onboarding="recurring-detail.step3"
                        class="text-xl font-semibold text-slate-950 dark:text-white"
                    >
                        {{ t('recurringDetail.timeline') }}
                    </h2>
                    <div
                        class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            {{ t('recurringDetail.timelineHint') }}
                        </p>
                        <Button
                            variant="outline"
                            class="shrink-0 rounded-xl"
                            :disabled="loadingYears.includes(todayTargetYear)"
                            @click="goToToday"
                            >{{ t('recurringDetail.today') }}</Button
                        >
                    </div>
                </div>

                <div
                    v-for="group in occurrenceYears"
                    :key="group.year"
                    class="border-t first:border-t-0"
                    :data-year="group.year"
                >
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center justify-between gap-3 px-5 py-4 text-left hover:bg-muted/40"
                        :aria-expanded="openYears.includes(group.year)"
                        :aria-controls="`occurrences-${group.year}`"
                        @click="toggleYear(group.year)"
                    >
                        <span class="min-w-0 flex-1 space-y-1.5">
                            <span
                                class="flex flex-wrap items-center gap-x-3 gap-y-1"
                            >
                                <span
                                    class="text-lg font-semibold tabular-nums"
                                    >{{ group.year }}</span
                                >
                                <span
                                    v-if="
                                        group.year ===
                                        Number(dateOptions.today.slice(0, 4))
                                    "
                                    class="rounded-full bg-primary/5 px-2 py-0.5 text-xs font-medium text-primary"
                                    >{{
                                        t('recurringDetail.currentYear')
                                    }}</span
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    ui('{count} movimenti', {
                                        count: group.count,
                                    })
                                }}</span>
                            </span>
                            <span
                                v-if="group.overdue_count > 0"
                                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-amber-800 dark:text-amber-200"
                                :data-overdue-year="group.year"
                            >
                                <span
                                    aria-hidden="true"
                                    class="size-1.5 rounded-full bg-amber-500"
                                ></span>
                                <span>{{ ui('Importi non corrisposti') }}</span>
                                <SensitiveValue
                                    class="font-semibold tabular-nums"
                                    :value="formatMoney(group.overdue_amount)"
                                />
                                <span
                                    class="text-amber-700/80 dark:text-amber-300/80"
                                    >·
                                    {{
                                        ui('{count} scadenze arretrate', {
                                            count: group.overdue_count,
                                        })
                                    }}</span
                                >
                            </span>
                        </span>
                        <span
                            aria-hidden="true"
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted/60 text-lg text-muted-foreground"
                            >{{
                                openYears.includes(group.year) ? '−' : '+'
                            }}</span
                        >
                    </button>
                    <div
                        v-if="openYears.includes(group.year)"
                        :id="`occurrences-${group.year}`"
                        :aria-busy="loadingYears.includes(group.year)"
                    >
                        <div v-if="group.cancelled_count" class="px-4 py-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                :aria-pressed="
                                    showCancelledYears[group.year] ?? false
                                "
                                :disabled="loadingYears.includes(group.year)"
                                @click="toggleCancelled(group.year)"
                            >
                                {{
                                    t(
                                        showCancelledYears[group.year]
                                            ? 'recurringDetail.compound.hideCancelled'
                                            : 'recurringDetail.compound.showCancelled',
                                        { count: group.cancelled_count },
                                    )
                                }}
                            </Button>
                        </div>
                        <div v-if="yearFirst[group.year] > 0" class="px-4 pb-2">
                            <Button
                                variant="ghost"
                                :disabled="loadingYears.includes(group.year)"
                                @click="
                                    loadYear(
                                        group.year,
                                        Math.max(0, yearFirst[group.year] - 12),
                                    )
                                "
                                >{{ t('recurringDetail.previous') }}</Button
                            >
                        </div>
                        <p
                            v-if="yearRows[group.year]?.length"
                            class="px-5 py-2 text-xs text-muted-foreground"
                            aria-live="polite"
                        >
                            {{
                                t('recurringDetail.window', {
                                    from: (yearFirst[group.year] ?? 0) + 1,
                                    to:
                                        (yearFirst[group.year] ?? 0) +
                                        yearRows[group.year].length,
                                    total:
                                        group.count -
                                        (showCancelledYears[group.year]
                                            ? 0
                                            : (group.cancelled_count ?? 0)),
                                })
                            }}
                        </p>
                        <div class="space-y-4 p-4 sm:hidden">
                            <article
                                v-for="occurrence in yearRows[group.year] ?? []"
                                :key="occurrence.uuid"
                                :data-occurrence-uuid="occurrence.uuid"
                                tabindex="-1"
                                class="space-y-4 rounded-[24px] border bg-white/90 p-4 transition-all dark:bg-slate-950/70"
                                :class="
                                    highlightedOccurrenceUuid ===
                                    occurrence.uuid
                                        ? 'border-violet-400 ring-2 ring-violet-300/60 dark:border-violet-500 dark:ring-violet-500/25'
                                        : 'border-slate-200/80 dark:border-white/10'
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <p
                                            v-if="occurrence.component_name"
                                            class="font-semibold"
                                        >
                                            {{ occurrence.component_name }}
                                        </p>
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
                                    <form
                                        v-if="
                                            amountEditingOccurrenceUuid ===
                                            occurrence.uuid
                                        "
                                        class="w-40 space-y-2"
                                        @submit.prevent="
                                            submitAmount(occurrence.uuid)
                                        "
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <MobileAmountInput
                                                v-model="amountForm.amount"
                                                :currency-code="entry.currency"
                                                min="0.01"
                                                class="h-9 rounded-xl text-right"
                                            />
                                            <Button
                                                type="submit"
                                                size="icon"
                                                class="size-9 shrink-0 rounded-xl"
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
                                    <div
                                        v-else
                                        class="flex items-center gap-1.5"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-950 dark:text-white"
                                        >
                                            <SensitiveValue
                                                :value="
                                                    formatMoney(
                                                        occurrence.expected_amount ??
                                                            0,
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
                                    class="grid min-w-0 grid-cols-3 gap-2 rounded-xl bg-muted/50 p-3 text-xs"
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
                                        class="min-w-0"
                                    >
                                        <dt class="text-muted-foreground">
                                            {{ occurrenceAmountLabel(label) }}
                                        </dt>
                                        <dd
                                            class="mt-1 font-semibold break-words tabular-nums"
                                            :class="
                                                label === 'amountPaid'
                                                    ? 'text-base text-emerald-700 dark:text-emerald-300'
                                                    : label ===
                                                            'amountRemaining' &&
                                                        (amount ?? 0) > 0
                                                      ? 'text-base text-foreground'
                                                      : ''
                                            "
                                        >
                                            <SensitiveValue
                                                :value="
                                                    formatMoney(amount ?? 0)
                                                "
                                            />
                                        </dd>
                                    </div>
                                </dl>

                                <div class="space-y-1 text-sm">
                                    <p
                                        class="text-slate-700 dark:text-slate-200"
                                    >
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
                                            t(
                                                'transactions.recurring.labels.noAccount',
                                            )
                                        }}
                                        <span
                                            v-if="
                                                occurrence.resource
                                                    ?.is_overridden
                                            "
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
                                            occurrence.converted_transaction
                                                ?.show_url
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
                                                ?.refund_transaction
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
                                    <Link
                                        v-if="
                                            !occurrence.converted_transaction &&
                                            occurrence.payments?.length
                                        "
                                        :href="
                                            occurrence.payments[
                                                occurrence.payments.length - 1
                                            ].show_url
                                        "
                                        class="inline-flex min-h-9 items-center text-sm font-medium text-sky-700 dark:text-sky-300"
                                        >{{ ui('Apri ultimo pagamento') }}</Link
                                    >
                                    <Link
                                        v-if="
                                            !occurrence.converted_transaction &&
                                            !occurrence.payments?.length &&
                                            occurrence.payment_history?.length
                                        "
                                        :href="
                                            occurrence.payment_history[
                                                occurrence.payment_history
                                                    .length - 1
                                            ].show_url
                                        "
                                        class="inline-flex min-h-9 items-center text-sm font-medium text-sky-700 dark:text-sky-300"
                                        >{{ ui('Apri ultimo pagamento') }}</Link
                                    >
                                    <p
                                        v-if="
                                            !occurrence.converted_transaction &&
                                            !occurrence.external_payment &&
                                            !occurrence.payments?.length &&
                                            !occurrence.payment_history?.length
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

                                <details
                                    v-if="
                                        occurrence.payments?.length ||
                                        occurrence.external_payment ||
                                        occurrence.payment_history?.length
                                    "
                                    class="min-w-0 text-sm"
                                >
                                    <summary
                                        class="cursor-pointer py-2 text-muted-foreground"
                                    >
                                        {{ ui('Dettagli pagamenti') }}
                                    </summary>
                                    <OccurrencePayments
                                        :payments="occurrence.payments"
                                        :external-payment="
                                            occurrence.external_payment
                                        "
                                        :history="occurrence.payment_history"
                                        :currency="entry.currency"
                                    />
                                </details>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="occurrence.can_register_payment"
                                        variant="outline"
                                        class="h-11 rounded-full px-3 text-xs"
                                        :disabled="paymentLoading"
                                        @click="registerPayment(occurrence)"
                                        >{{ ui('Registra pagamento') }}</Button
                                    ><Button
                                        v-if="occurrence.can_register_payment"
                                        variant="ghost"
                                        class="min-h-9 rounded-full px-3 text-xs"
                                        @click="openLinkPayment(occurrence)"
                                        >{{
                                            ui('Collega pagamento esistente')
                                        }}</Button
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
                                        @click="
                                            toggleSkippedOccurrence(occurrence)
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
                                            occurrenceCounterparty(occurrence)
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
                                            occurrence.converted_transaction
                                                ?.can_refund
                                        "
                                        variant="outline"
                                        class="h-9 rounded-full px-3 text-xs"
                                        @click="
                                            refundDialogTransactionUuid =
                                                occurrence.converted_transaction
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
                                                t(
                                                    'transactions.recurring.labels.date',
                                                )
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
                                        v-for="occurrence in yearRows[
                                            group.year
                                        ] ?? []"
                                        :key="occurrence.uuid"
                                        :data-occurrence-uuid="occurrence.uuid"
                                        tabindex="-1"
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
                                                <p
                                                    v-if="
                                                        occurrence.component_name
                                                    "
                                                    class="font-semibold"
                                                >
                                                    {{
                                                        occurrence.component_name
                                                    }}
                                                </p>
                                                <p>
                                                    {{
                                                        occurrence.due_date ??
                                                        occurrence.expected_date
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        occurrence.has_date_override
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
                                                    submitAmount(
                                                        occurrence.uuid,
                                                    )
                                                "
                                            >
                                                <div
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <MobileAmountInput
                                                        v-model="
                                                            amountForm.amount
                                                        "
                                                        min="0.01"
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
                                                            v-if="
                                                                amountForm.processing
                                                            "
                                                            class="size-4 animate-spin"
                                                        />
                                                        <Check
                                                            v-else
                                                            class="size-4"
                                                        />
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
                                                        @click="
                                                            cancelAmountEdit
                                                        "
                                                    >
                                                        <X class="size-4" />
                                                    </Button>
                                                </div>
                                                <InputError
                                                    :message="
                                                        amountForm.errors.amount
                                                    "
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
                                                    v-if="
                                                        occurrence.can_update_amount
                                                    "
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    class="size-8 rounded-xl text-violet-700 dark:text-violet-300"
                                                    :aria-label="
                                                        t(
                                                            'transactions.recurring.actions.editAmount',
                                                        )
                                                    "
                                                    @click="
                                                        startAmountEdit(
                                                            occurrence,
                                                        )
                                                    "
                                                >
                                                    <Pencil class="size-3.5" />
                                                </Button>
                                            </div>
                                            <dl class="mt-2 space-y-1 text-xs">
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
                                                    <dt
                                                        class="text-muted-foreground"
                                                    >
                                                        {{
                                                            occurrenceAmountLabel(
                                                                label,
                                                            )
                                                        }}
                                                    </dt>
                                                    <dd
                                                        class="font-semibold tabular-nums"
                                                        :class="
                                                            label ===
                                                            'amountPaid'
                                                                ? 'text-base text-emerald-700 dark:text-emerald-300'
                                                                : label ===
                                                                        'amountRemaining' &&
                                                                    (amount ??
                                                                        0) > 0
                                                                  ? 'text-base text-foreground'
                                                                  : ''
                                                        "
                                                    >
                                                        <SensitiveValue
                                                            :value="
                                                                formatMoney(
                                                                    amount ?? 0,
                                                                )
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
                                                        occurrence.resource
                                                            ?.name ??
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
                                                        occurrenceState(
                                                            occurrence,
                                                        ).tone
                                                    "
                                                >
                                                    {{
                                                        occurrenceState(
                                                            occurrence,
                                                        ).label
                                                    }}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div
                                                v-if="
                                                    occurrence.converted_transaction
                                                "
                                                class="space-y-1"
                                            >
                                                <p
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        occurrence
                                                            .converted_transaction
                                                            .transaction_date
                                                    }}
                                                </p>
                                                <Link
                                                    v-if="
                                                        occurrence
                                                            .converted_transaction
                                                            .show_url
                                                    "
                                                    :href="
                                                        occurrence
                                                            .converted_transaction
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
                                                        occurrence
                                                            .converted_transaction
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
                                            <Link
                                                v-else-if="
                                                    occurrence.payments?.length
                                                "
                                                :href="
                                                    occurrence.payments[
                                                        occurrence.payments
                                                            .length - 1
                                                    ].show_url
                                                "
                                                class="inline-flex min-h-9 items-center text-sm font-medium text-sky-700 dark:text-sky-300"
                                                >{{
                                                    ui('Apri ultimo pagamento')
                                                }}</Link
                                            >
                                            <Link
                                                v-else-if="
                                                    occurrence.payment_history
                                                        ?.length
                                                "
                                                :href="
                                                    occurrence.payment_history[
                                                        occurrence
                                                            .payment_history
                                                            .length - 1
                                                    ].show_url
                                                "
                                                class="inline-flex min-h-9 items-center text-sm font-medium text-sky-700 dark:text-sky-300"
                                                >{{
                                                    ui('Apri ultimo pagamento')
                                                }}</Link
                                            >
                                            <span
                                                v-else-if="
                                                    !occurrence.external_payment
                                                "
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'transactions.recurring.table.noLinkedTransaction',
                                                    )
                                                }}
                                            </span>
                                            <details
                                                v-if="
                                                    occurrence.payments
                                                        ?.length ||
                                                    occurrence.external_payment ||
                                                    occurrence.payment_history
                                                        ?.length
                                                "
                                                class="min-w-0 text-sm"
                                            >
                                                <summary
                                                    class="cursor-pointer py-2 text-muted-foreground"
                                                >
                                                    {{
                                                        ui('Dettagli pagamenti')
                                                    }}
                                                </summary>
                                                <OccurrencePayments
                                                    :history="
                                                        occurrence.payment_history
                                                    "
                                                    :payments="
                                                        occurrence.payments
                                                    "
                                                    :external-payment="
                                                        occurrence.external_payment
                                                    "
                                                    :currency="entry.currency"
                                                />
                                            </details>
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
                                                    @click="
                                                        registerPayment(
                                                            occurrence,
                                                        )
                                                    "
                                                    >{{
                                                        ui('Registra pagamento')
                                                    }}</Button
                                                ><Button
                                                    v-if="
                                                        occurrence.can_register_payment
                                                    "
                                                    variant="ghost"
                                                    class="min-h-9 rounded-full px-3 text-xs"
                                                    @click="
                                                        openLinkPayment(
                                                            occurrence,
                                                        )
                                                    "
                                                    >{{
                                                        ui(
                                                            'Collega pagamento esistente',
                                                        )
                                                    }}</Button
                                                >
                                                <Button
                                                    v-if="
                                                        occurrence.can_update_occurrence
                                                    "
                                                    variant="outline"
                                                    class="h-9 rounded-full px-3 text-xs"
                                                    @click="
                                                        startOccurrenceEdit(
                                                            occurrence,
                                                        )
                                                    "
                                                >
                                                    <Pencil
                                                        class="mr-1.5 size-3.5"
                                                    />
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
                                                        occurrence.status !==
                                                            'skipped'
                                                    "
                                                    variant="outline"
                                                    class="h-9 rounded-full px-3 text-xs"
                                                    @click="
                                                        requestConvert(
                                                            occurrence,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        occurrenceCounterparty(
                                                            occurrence,
                                                        )
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
                                                        occurrence
                                                            .converted_transaction
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
                        <div class="p-4 pt-2">
                            <p
                                v-if="group.count === 0"
                                class="text-sm text-muted-foreground"
                            >
                                {{ ui('Nessun movimento in questo anno') }}
                            </p>
                            <p
                                v-if="failedYears.includes(group.year)"
                                role="alert"
                                class="mb-2 text-sm text-destructive"
                            >
                                {{ ui('Caricamento non riuscito. Riprova.') }}
                            </p>
                            <Button
                                v-if="
                                    loadingYears.includes(group.year) ||
                                    yearNext[group.year] != null ||
                                    failedYears.includes(group.year)
                                "
                                variant="outline"
                                class="min-h-11 w-full rounded-xl sm:w-auto"
                                :disabled="loadingYears.includes(group.year)"
                                @click="
                                    loadYear(
                                        group.year,
                                        failedYears.includes(group.year)
                                            ? (retryOffsets[group.year] ?? 0)
                                            : (yearNext[group.year] ?? 0),
                                    )
                                "
                            >
                                <LoaderCircle
                                    v-if="loadingYears.includes(group.year)"
                                    class="mr-2 size-4 animate-spin"
                                />
                                {{
                                    loadingYears.includes(group.year)
                                        ? ui('Caricamento…')
                                        : t('recurringDetail.next')
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
                <details
                    v-if="automaticOccurrencesNoteVisible"
                    :key="automaticNoteKey"
                    :open="automaticNoteOpen"
                    data-testid="automatic-occurrences-note"
                    class="m-4 overflow-hidden rounded-2xl border border-sky-200/70 bg-sky-50/50 text-sky-950 sm:m-5 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-100"
                    @toggle="toggleAutomaticNote"
                >
                    <summary
                        class="flex min-h-12 cursor-pointer list-none items-center gap-3 p-4 text-sm font-medium [&::-webkit-details-marker]:hidden"
                    >
                        <InfinityIcon
                            class="size-5 shrink-0 text-sky-600 dark:text-sky-300"
                            aria-hidden="true"
                        />
                        <span class="flex-1">{{
                            t('recurringDetail.automaticTitle')
                        }}</span>
                        <span
                            class="shrink-0 text-xs text-sky-700 dark:text-sky-300"
                            >{{
                                t(
                                    automaticNoteOpen
                                        ? 'recurringDetail.hideExplanation'
                                        : 'recurringDetail.showExplanation',
                                )
                            }}</span
                        >
                        <span aria-hidden="true">{{
                            automaticNoteOpen ? '−' : '+'
                        }}</span>
                    </summary>
                    <p
                        class="border-t border-sky-200/50 px-4 py-4 text-sm leading-relaxed text-sky-900/80 dark:border-sky-500/15 dark:text-sky-100/75"
                    >
                        {{ t('recurringDetail.automaticDescription') }}
                    </p>
                </details>
            </section>
        </div>

        <CompoundRecurringFormSheet
            v-if="compound"
            v-model:open="addComponentOpen"
            :compound-uuid="compound.uuid"
            :currency="entry.currency ?? 'EUR'"
            :reminders="formOptions.reminders"
        />
        <RecurringEntryFormSheet
            v-model:open="formOpen"
            :entry="entry"
            :reactivation="reactivationMode"
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
                        <AppDatePicker v-model="occurrenceForm.due_date" />
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
                        ui(
                            'Recupera anche le scadenze trascorse durante l’interruzione. Le date passate richiedono registrazione manuale. Per ripartire senza arretrati usa Riattiva dalla data scelta.',
                        )
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
        <Dialog v-model:open="archiveComponentOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        t('recurringDetail.compound.removeTitle', {
                            name: compound?.active_component,
                        })
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('recurringDetail.compound.removeDescription')
                    }}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="archiveComponentForm.processing"
                        @click="archiveComponentOpen = false"
                        >{{ t('app.common.cancel') }}</Button
                    >
                    <Button
                        :disabled="archiveComponentForm.processing"
                        @click="removeSelectedComponent"
                        >{{
                            t('recurringDetail.compound.removeComponent')
                        }}</Button
                    >
                </DialogFooter>
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
                    <DialogTitle> {{ ui('Registra pagamento') }} </DialogTitle>
                    <DialogDescription>
                        {{
                            ui(
                                'Indica chi ha pagato questa scadenza. L’anticipo della controparte viene registrato nelle Spese condivise.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSharedPayment">
                    <fieldset
                        v-if="sharedPaymentCounterparty"
                        class="space-y-2"
                    >
                        <legend class="text-sm font-medium">
                            {{ ui('Pagato da') }}
                        </legend>
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
                                >{{ sharedPaymentCounterparty?.name }}</Button
                            >
                        </div>
                    </fieldset>
                    <div class="space-y-2">
                        <label
                            for="shared-occurrence-amount"
                            class="text-sm font-medium"
                        >
                            {{ ui('Importo pagato') }}
                        </label>
                        <MobileAmountInput
                            id="shared-occurrence-amount"
                            v-model="sharedPaymentForm.amount"
                            :currency-code="entry.currency"
                            min="0.01"
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
                        >
                            {{ ui('Data del pagamento') }}
                        </label>
                        <AppDatePicker
                            id="shared-occurrence-date"
                            v-model="sharedPaymentForm.transaction_date"
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
                        >
                            {{ ui('Conto del pagamento') }}
                        </label>
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
                        >
                            {{ ui('Annulla') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="sharedPaymentForm.processing"
                        >
                            {{ ui('Salva pagamento') }}
                        </Button>
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
                                    occurrenceState(distributionRows[index])
                                        .label
                                }}
                                ·
                                {{
                                    occurrenceDateValue(distributionRows[index])
                                }}
                            </p>
                        </div>
                        <MobileAmountInput
                            v-model="installment.amount"
                            :currency-code="entry.currency"
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
        <Dialog v-model:open="linkPaymentOpen">
            <DialogContent class="max-h-[85dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader
                    ><DialogTitle>{{
                        ui('Collega pagamento esistente')
                    }}</DialogTitle
                    ><DialogDescription>{{
                        ui(
                            'Seleziona un movimento già registrato. Non verrà creato un nuovo pagamento.',
                        )
                    }}</DialogDescription></DialogHeader
                >
                <form
                    class="flex flex-wrap items-end gap-2"
                    @submit.prevent="loadPaymentCandidates()"
                >
                    <label class="min-w-0 flex-1 text-xs"
                        >{{ ui('Dal giorno')
                        }}<AppDatePicker v-model="candidateFrom"
                    /></label>
                    <label class="min-w-0 flex-1 text-xs"
                        >{{ ui('Al giorno')
                        }}<AppDatePicker v-model="candidateTo"
                    /></label>
                    <Button variant="outline" :disabled="linkPaymentLoading">{{
                        ui('Cerca pagamenti')
                    }}</Button>
                </form>
                <p
                    v-if="linkPaymentFailed"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ ui('Caricamento non riuscito. Riprova.') }}
                </p>
                <div class="space-y-2">
                    <button
                        v-for="payment in paymentCandidatesList"
                        :key="payment.uuid"
                        type="button"
                        class="flex min-h-14 w-full flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-left text-sm"
                        :class="
                            linkPaymentForm.transaction_uuid === payment.uuid
                                ? 'border-sky-400 bg-sky-50 dark:bg-sky-950/50'
                                : 'hover:bg-muted'
                        "
                        :aria-pressed="
                            linkPaymentForm.transaction_uuid === payment.uuid
                        "
                        @click="chooseExistingPayment(payment)"
                    >
                        <span class="min-w-0 break-words"
                            >{{ payment.date }} ·
                            {{ payment.description || payment.account
                            }}<span
                                class="block text-xs text-muted-foreground"
                                >{{ payment.account }}</span
                            ></span
                        ><SensitiveValue
                            class="font-semibold"
                            :value="formatMoney(payment.amount)"
                        />
                    </button>
                    <p
                        v-if="
                            !linkPaymentLoading && !paymentCandidatesList.length
                        "
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            ui('Nessun pagamento disponibile in questo periodo')
                        }}
                    </p>
                    <Button
                        v-if="candidateHasMore || linkPaymentLoading"
                        variant="ghost"
                        :disabled="linkPaymentLoading"
                        @click="loadPaymentCandidates(candidatePage + 1)"
                        ><LoaderCircle
                            v-if="linkPaymentLoading"
                            class="mr-2 size-4 animate-spin"
                        />{{ ui('Mostra altre') }}</Button
                    >
                </div>
                <form
                    v-if="linkPaymentForm.transaction_uuid"
                    class="space-y-3 border-t pt-3"
                    @submit.prevent="submitLinkPayment"
                >
                    <label class="block space-y-1 text-sm"
                        >{{ ui('Importo da collegare')
                        }}<MobileAmountInput
                            v-model="linkPaymentForm.amount"
                            :currency-code="entry.currency"
                            min="0.01"
                            :max="linkPaymentOccurrence?.remaining_amount"
                    /></label>
                    <InputError
                        :message="
                            linkPaymentForm.errors.amount ||
                            linkPaymentForm.errors.transaction_uuid
                        "
                    />
                    <DialogFooter
                        ><Button
                            type="submit"
                            :disabled="linkPaymentForm.processing"
                            >{{ ui('Conferma collegamento') }}</Button
                        ></DialogFooter
                    >
                </form>
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
