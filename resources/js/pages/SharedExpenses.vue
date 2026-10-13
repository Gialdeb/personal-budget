<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { destroyLedger } from '@/actions/App/Http/Controllers/SharedExpenseController';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import MobileQuickChoices from '@/components/transactions/MobileQuickChoices.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as sharedExpensesIndex } from '@/routes/shared-expenses';
import { store as storeSettlement } from '@/routes/shared-expenses/settlements';
import {
    destroy as destroySettlement,
    update as updateSettlement,
} from '@/routes/shared-expenses/settlements';
import { store as storeTrackedItem } from '@/routes/transactions/tracked-items';
import type { TransactionsNavigation } from '@/types';

const cancelLedgerEntry = ref<Entry | null>(null);
function cancelLedger(entry: Entry): void {
    cancelLedgerEntry.value = entry;
}
function confirmCancelLedger(): void {
    if (!cancelLedgerEntry.value) {
        return;
    }

    router.delete(destroyLedger.url(cancelLedgerEntry.value.uuid), {
        preserveScroll: true,
    });
}
const ui = useUiCopy();

type Entry = {
    can_cancel_payment?: boolean;
    uuid: string;
    date: string | null;
    title: string | null;
    counterparty: string | null;
    counterparty_uuid?: string | null;
    category: string | null;
    reference: string | null;
    origin: string;
    amount: number;
    currency: string;
    status: string;
    origin_url?: string | null;
    origin_label?: string | null;
    paid_by?: string;
    my_share_cents?: number;
    counterparty_share_cents?: number;
    effect_cents?: number;
    is_settlement?: boolean;
    settlement?: {
        account_uuid: string | null;
        category_uuid: string | null;
        reference: string | null;
        notes: string | null;
    };
};
type AccountOption = { uuid: string; name: string; currency: string };
type CategoryOption = {
    uuid: string;
    name: string;
    icon?: string | null;
    color?: string | null;
    id?: number;
    parent_id?: number | null;
};
type ReferenceOption = {
    value: string;
    name?: string;
    label?: string;
    logo_url?: string | null;
};
const props = defineProps<{
    selectedEntryUuid?: string | null;
    filters: {
        month: number;
        year: number;
        counterparty: string | null;
        search: string;
        effect: string;
    };
    counterparties: { uuid: string; name: string }[];
    visibleCounterparties: { uuid: string; name: string }[];
    historicalCounterparties: { uuid: string; name: string }[];
    entries: Entry[];
    balances: Record<string, number>;
    counterpartyEntryCounts: Record<string, number>;
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    settlementOptions: {
        accounts: AccountOption[];
        categories: Record<string, CategoryOption[]>;
        references?: Record<string, ReferenceOption[]>;
    };
}>();
watch(
    () => props.selectedEntryUuid,
    async (uuid) => {
        if (!uuid) {
            return;
        }

        await nextTick();
        document
            .getElementById(`shared-entry-${uuid}`)
            ?.scrollIntoView({ block: 'center' });
    },
    { immediate: true, flush: 'post' },
);
const page = usePage();
const isMobile = useMediaQuery('(max-width: 767px)');
const newReferenceOpen = ref(false);
const newReferenceName = ref('');
const newReferenceWebsite = ref('');
const creatingReference = ref(false);
const localReferences = ref<Record<string, ReferenceOption[]>>({});
const { locale } = useI18n();
const search = ref(props.filters.search);
const counterparty = ref(props.filters.counterparty ?? '');
const effect = ref(props.filters.effect ?? '');
const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);
const availableYears = computed(
    () => navigation.value?.context.available_years ?? [props.filters.year],
);
const selectedYear = computed(() => String(props.filters.year));
const selectedMonth = computed(() => String(props.filters.month));
let timeout: ReturnType<typeof setTimeout> | undefined;
watch([search, counterparty, effect], () => {
    clearTimeout(timeout);
    timeout = setTimeout(
        () =>
            router.get(
                sharedExpensesIndex.url(),
                {
                    search: search.value || undefined,
                    month: props.filters.month,
                    year: props.filters.year,
                    counterparty: counterparty.value || undefined,
                    effect: effect.value || undefined,
                },
                { preserveState: true, replace: true },
            ),
        250,
    );
});
function monthLabel(month: number): string {
    return new Intl.DateTimeFormat(locale.value, { month: 'long' }).format(
        new Date(Date.UTC(props.filters.year, month - 1, 1)),
    );
}
function updatePeriod(year: number, month: number): void {
    if (year === props.filters.year && month === props.filters.month) {
        return;
    }

    router.get(
        sharedExpensesIndex.url(),
        {
            search: search.value || undefined,
            year,
            month,
            counterparty: counterparty.value || undefined,
            effect: effect.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
function updateYear(value: string): void {
    const year = Number(value);

    if (Number.isInteger(year)) {
        updatePeriod(year, props.filters.month);
    }
}
function updateMonth(value: string): void {
    const month = Number(value);

    if (Number.isInteger(month) && month >= 1 && month <= 12) {
        updatePeriod(props.filters.year, month);
    }
}
const money = (entry: Entry) =>
    new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: entry.currency,
    }).format(entry.amount);
const cents = (value: number, currency = 'EUR') =>
    new Intl.NumberFormat(locale.value, { style: 'currency', currency }).format(
        value / 100,
    );
const settlementCounterparty = ref<{
    uuid: string;
    name: string;
    balance: number;
} | null>(null);
const editingSettlement = ref<Entry | null>(null);
const settlementForm = useForm({
    amount: '',
    settled_at: new Date().toISOString().slice(0, 10),
    account_uuid: '',
    category_uuid: '',
    reference: '',
    notes: '',
});
const receivableCents = computed(() =>
    Object.values(props.balances)
        .filter((balance) => balance > 0)
        .reduce((total, balance) => total + balance, 0),
);
const payableCents = computed(() =>
    Object.values(props.balances)
        .filter((balance) => balance < 0)
        .reduce((total, balance) => total + Math.abs(balance), 0),
);
const netCents = computed(() => receivableCents.value - payableCents.value);
const receivablePeople = computed(
    () => Object.values(props.balances).filter((balance) => balance > 0).length,
);
const payablePeople = computed(
    () => Object.values(props.balances).filter((balance) => balance < 0).length,
);
const entryDate = (entry: Entry) =>
    entry.date
        ? new Intl.DateTimeFormat(locale.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(new Date(`${entry.date}T12:00:00`))
        : ui('Data non disponibile');
const entriesByDay = computed(() => {
    const groups = new Map<string, { label: string; entries: Entry[] }>();

    for (const entry of props.entries) {
        const key = entry.date ?? 'unknown-date';
        const group = groups.get(key) ?? {
            label: entryDate(entry).toLocaleUpperCase(locale.value),
            entries: [],
        };

        group.entries.push(entry);
        groups.set(key, group);
    }

    return [...groups.entries()].map(([key, group]) => ({ key, ...group }));
});
const effectLabel = (entry: Entry) =>
    entry.effect_cents === undefined || entry.effect_cents === 0
        ? ui('In pari')
        : entry.effect_cents > 0
          ? ui('{name} ti deve {amount}', {
                name: entry.counterparty ?? '',
                amount: cents(entry.effect_cents, entry.currency),
            })
          : ui('Devi {amount} a {name}', {
                name: entry.counterparty ?? '',
                amount: cents(-entry.effect_cents, entry.currency),
            });
const accountCategories = computed(
    () => props.settlementOptions.categories[settlementForm.account_uuid] ?? [],
);
const categoryChoices = computed(() =>
    accountCategories.value.map((item) => ({
        ...item,
        value: item.uuid,
        label: item.name,
    })),
);
const categoryChildren = computed(() => {
    const selected = accountCategories.value.find(
        (item) => item.uuid === settlementForm.category_uuid,
    );

    if (!selected) {
        return [];
    }

    const children = categoryChoices.value.filter(
        (item) => item.parent_id === selected.id,
    );

    return children.length
        ? children
        : categoryChoices.value.filter(
              (item) =>
                  selected.parent_id != null &&
                  item.parent_id === selected.parent_id,
          );
});
const referenceCatalog = computed(() => [
    ...(props.settlementOptions.references?.[settlementForm.account_uuid] ??
        []),
    ...(localReferences.value[settlementForm.account_uuid] ?? []),
]);
const referenceChoices = computed(() =>
    referenceCatalog.value.map((item) => ({
        ...item,
        value: item.name ?? item.label ?? '',
        label: item.name ?? item.label ?? '',
    })),
);
const quickReferences = computed(() => {
    const counts = new Map<string, number>();
    props.entries.forEach((entry) => {
        if (entry.reference) {
            counts.set(entry.reference, (counts.get(entry.reference) ?? 0) + 1);
        }
    });
    const ranked = [...referenceChoices.value].sort(
        (a, b) => (counts.get(b.value) ?? 0) - (counts.get(a.value) ?? 0),
    );
    const choices = ranked.slice(0, 8);
    const selected = ranked.find(
        (item) => item.value === settlementForm.reference,
    );

    if (selected && !choices.includes(selected)) {
        choices.unshift(selected);
    }

    return choices;
});
const accountChoices = computed(() =>
    props.settlementOptions.accounts.map((item) => ({
        value: item.uuid,
        label: `${item.name} (${item.currency})`,
    })),
);
async function createSettlementReference() {
    if (!newReferenceName.value.trim() || !settlementForm.category_uuid) {
        settlementForm.setError(
            'reference',
            ui('Seleziona una categoria e inserisci il nome.'),
        );

        return;
    }

    creatingReference.value = true;

    try {
        const response = await fetch(storeTrackedItem().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({
                name: newReferenceName.value.trim(),
                account_uuid: settlementForm.account_uuid,
                category_uuid: settlementForm.category_uuid,
                type_key:
                    settlementCounterparty.value &&
                    settlementCounterparty.value.balance > 0
                        ? 'income'
                        : 'debt',
                website_url: newReferenceWebsite.value || null,
            }),
        });
        const payload = await response.json();

        if (!response.ok) {
            settlementForm.setError(
                'reference',
                Object.values(payload.errors ?? {})
                    .flat()
                    .join(' ') || ui('Impossibile creare il riferimento.'),
            );

            return;
        }

        const option = payload.item as ReferenceOption;
        localReferences.value[settlementForm.account_uuid] = [
            ...(localReferences.value[settlementForm.account_uuid] ?? []),
            option,
        ];
        settlementForm.reference =
            option.name ?? option.label ?? newReferenceName.value.trim();
        newReferenceOpen.value = false;
        newReferenceName.value = '';
        newReferenceWebsite.value = '';
        settlementForm.clearErrors('reference');
    } catch {
        settlementForm.setError(
            'reference',
            ui('Impossibile creare il riferimento.'),
        );
    } finally {
        creatingReference.value = false;
    }
}
function openSettlement(counterpartyItem: {
    uuid: string;
    name: string;
}): void {
    const balance = props.balances[counterpartyItem.uuid];
    settlementCounterparty.value = { ...counterpartyItem, balance };
    settlementForm.reset();
    settlementForm.amount = (Math.abs(balance) / 100).toFixed(2);
    settlementForm.settled_at = new Date().toISOString().slice(0, 10);
    settlementForm.account_uuid =
        props.settlementOptions.accounts[0]?.uuid ?? '';
    settlementForm.clearErrors();
}
function submitSettlement(): void {
    if (!settlementCounterparty.value) {
        return;
    }

    const route = editingSettlement.value
        ? updateSettlement(editingSettlement.value.uuid).url
        : storeSettlement(settlementCounterparty.value.uuid).url;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            settlementCounterparty.value = null;
            editingSettlement.value = null;
        },
    };

    if (editingSettlement.value) {
        settlementForm.patch(route, options);
    } else {
        settlementForm.post(route, options);
    }
}
function editSettlement(entry: Entry): void {
    if (!entry.settlement || !entry.counterparty_uuid || !entry.counterparty) {
        return;
    }

    editingSettlement.value = entry;
    settlementCounterparty.value = {
        uuid: entry.counterparty_uuid,
        name: entry.counterparty,
        balance: props.balances[entry.counterparty_uuid] ?? 0,
    };
    settlementForm.amount = entry.amount.toFixed(2);
    settlementForm.settled_at = entry.date ?? '';
    settlementForm.account_uuid = entry.settlement.account_uuid ?? '';
    settlementForm.category_uuid = entry.settlement.category_uuid ?? '';
    settlementForm.reference = entry.settlement.reference ?? '';
    settlementForm.notes = entry.settlement.notes ?? '';
    settlementForm.clearErrors();
}
function deleteSettlement(entry: Entry): void {
    router.delete(destroySettlement(entry.uuid).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="ui('Spese condivise')" />
    <AppLayout
        :breadcrumbs="[
            { title: ui('Spese condivise'), href: '/shared-expenses' },
        ]"
    >
        <div
            class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 pb-28 md:p-6"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ ui('Spese condivise') }}
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    {{
                        ui(
                            'Consulta saldi, rimborsi e movimenti condivisi. Le spese si registrano dalle normali transazioni, ricorrenze e rate.',
                        )
                    }}
                </p>
            </div>

            <section class="grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/25 dark:bg-emerald-500/10"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-emerald-800 uppercase dark:text-emerald-200"
                    >
                        {{ ui('Da ricevere') }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ cents(receivableCents) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ ui('da') }} {{ receivablePeople }}
                        {{
                            receivablePeople === 1
                                ? ui('persona')
                                : ui('persone')
                        }}
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 dark:border-rose-500/25 dark:bg-rose-500/10"
                >
                    <p
                        class="text-xs font-semibold tracking-wide text-rose-800 uppercase dark:text-rose-200"
                    >
                        {{ ui('Da restituire') }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ cents(payableCents) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ ui('a') }} {{ payablePeople }}
                        {{
                            payablePeople === 1 ? ui('persona') : ui('persone')
                        }}
                    </p>
                </div>
                <div class="rounded-2xl border bg-card p-4">
                    <p
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ ui('Saldo netto') }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold">
                        {{ netCents > 0 ? '+' : netCents < 0 ? '−' : ''
                        }}{{ cents(Math.abs(netCents)) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            netCents === 0
                                ? ui('In pari')
                                : netCents > 0
                                  ? ui('Credito complessivo')
                                  : ui('Debito complessivo')
                        }}
                    </p>
                </div>
            </section>

            <section
                v-if="visibleCounterparties.length"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="item in visibleCounterparties"
                    :key="item.uuid"
                    class="flex min-w-0 flex-col rounded-2xl border bg-card p-4 text-sm"
                >
                    <p class="font-medium">{{ item.name }}</p>
                    <p class="mt-1 text-base font-semibold">
                        {{
                            balances[item.uuid] === 0
                                ? ui('In pari')
                                : balances[item.uuid] > 0
                                  ? ui('{name} ti deve {amount}', {
                                        name: item.name,
                                        amount: cents(balances[item.uuid]),
                                    })
                                  : ui('Devi {amount} a {name}', {
                                        name: item.name,
                                        amount: cents(-balances[item.uuid]),
                                    })
                        }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{ counterpartyEntryCounts[item.uuid] ?? 0 }}
                        {{ ui('movimenti nel periodo') }}
                    </p>
                    <Button
                        v-if="
                            balances[item.uuid] !== 0 &&
                            counterparties.some(
                                (counterparty) =>
                                    counterparty.uuid === item.uuid,
                            )
                        "
                        variant="outline"
                        size="sm"
                        class="mt-4 w-full sm:w-auto"
                        @click="openSettlement(item)"
                        >{{ ui('Registra rimborso') }}</Button
                    >
                </div>
            </section>
            <div
                class="grid gap-3 rounded-2xl border bg-card p-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <div class="grid gap-1.5">
                    <Label for="shared-search">{{ ui('Cerca') }}</Label
                    ><Input
                        id="shared-search"
                        v-model="search"
                        :placeholder="ui('Titolo, categoria, riferimento…')"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="shared-counterparty">{{
                        ui('Condivisa con')
                    }}</Label
                    ><SearchableSelect
                        id="shared-counterparty"
                        v-model="counterparty"
                        :options="[
                            { value: '', label: ui('Tutte le controparti') },
                            ...counterparties.map((item) => ({
                                value: item.uuid,
                                label: item.name,
                            })),
                        ]"
                        :placeholder="ui('Tutte le controparti')"
                        :search-placeholder="ui('Cerca controparte')"
                        clearable
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>{{ ui('Anno') }}</Label>
                    <Select
                        :model-value="selectedYear"
                        @update:model-value="updateYear(String($event ?? ''))"
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-border/80 bg-background/80 hover:bg-background"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="year in availableYears"
                                :key="year"
                                :value="String(year)"
                            >
                                {{ year }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>{{ ui('Mese') }}</Label>
                    <Select
                        :model-value="selectedMonth"
                        @update:model-value="updateMonth(String($event ?? ''))"
                    >
                        <SelectTrigger
                            class="h-11 rounded-2xl border-border/80 bg-background/80 hover:bg-background"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="month in 12"
                                :key="month"
                                :value="String(month)"
                            >
                                {{ monthLabel(month) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="shared-effect">{{ ui('Situazione') }}</Label
                    ><select
                        id="shared-effect"
                        v-model="effect"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">{{ ui('Tutti i movimenti') }}</option>
                        <option value="receivable">
                            {{ ui('Da ricevere') }}
                        </option>
                        <option value="payable">
                            {{ ui('Da restituire') }}
                        </option>
                        <option value="settled">
                            {{ ui('In pari / regolati') }}
                        </option>
                    </select>
                </div>
            </div>
            <section class="overflow-hidden rounded-2xl border bg-card">
                <div class="border-b px-4 py-3">
                    <h2 class="font-semibold">
                        {{ ui('Registro movimenti') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            ui(
                                "Chi ha pagato, chi deve ricevere e l'origine di ogni posizione.",
                            )
                        }}
                    </p>
                </div>
                <div
                    v-if="entries.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    {{
                        ui(
                            'Nessun movimento condiviso per i filtri selezionati.',
                        )
                    }}
                </div>
                <div v-else class="divide-y">
                    <div v-for="group in entriesByDay" :key="group.key">
                        <div
                            class="bg-muted/45 px-4 py-2 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            {{ group.label }}
                        </div>
                        <article
                            v-for="entry in group.entries"
                            :id="`shared-entry-${entry.uuid}`"
                            :key="entry.uuid"
                            class="m-3 grid min-w-0 gap-3 rounded-2xl border bg-card p-3 sm:m-4 sm:p-4"
                        >
                            <div class="min-w-0 space-y-1">
                                <div
                                    class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-start gap-3"
                                >
                                    <h3
                                        class="min-w-0 text-sm font-semibold break-words"
                                    >
                                        {{
                                            entry.title ||
                                            ui('Movimento condiviso')
                                        }}
                                    </h3>
                                    <div class="text-right">
                                        <span
                                            class="block text-[11px] text-muted-foreground"
                                            >{{ ui('Totale') }}</span
                                        ><span
                                            class="text-sm font-semibold whitespace-nowrap tabular-nums"
                                            >{{ money(entry) }}</span
                                        >
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ entry.counterparty || '—' }} ·
                                    {{ entry.origin }}
                                </p>
                                <p
                                    class="inline-flex max-w-full items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                                >
                                    {{
                                        entry.is_settlement
                                            ? entry.title
                                            : entry.paid_by === 'counterparty'
                                              ? ui('Ha pagato {name}', {
                                                    name:
                                                        entry.counterparty ??
                                                        '',
                                                })
                                              : ui('Hai pagato tu')
                                    }}
                                </p>
                                <dl
                                    v-if="entry.my_share_cents !== undefined"
                                    class="grid grid-cols-2 gap-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <dt
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ ui('Tua quota') }}
                                        </dt>
                                        <dd
                                            class="mt-0.5 text-sm font-semibold tabular-nums"
                                        >
                                            {{
                                                cents(
                                                    entry.my_share_cents,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt
                                            class="text-xs break-words text-muted-foreground"
                                        >
                                            {{ ui('Quota') }}
                                            {{ entry.counterparty }}
                                        </dt>
                                        <dd
                                            class="mt-0.5 text-sm font-semibold tabular-nums"
                                        >
                                            {{
                                                cents(
                                                    entry.counterparty_share_cents ??
                                                        0,
                                                    entry.currency,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <p
                                    class="border-t pt-2 text-sm font-semibold"
                                    :class="
                                        entry.effect_cents &&
                                        entry.effect_cents > 0
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : entry.effect_cents &&
                                                entry.effect_cents < 0
                                              ? 'text-rose-700 dark:text-rose-300'
                                              : ''
                                    "
                                >
                                    <template
                                        v-if="
                                            !entry.is_settlement &&
                                            entry.effect_cents
                                        "
                                    >
                                        <span class="block">{{
                                            entry.effect_cents > 0
                                                ? ui('{name} ti deve', {
                                                      name:
                                                          entry.counterparty ??
                                                          '',
                                                  })
                                                : ui('Devi a {name}', {
                                                      name:
                                                          entry.counterparty ??
                                                          '',
                                                  })
                                        }}</span>
                                        <span
                                            class="mt-0.5 block text-2xl font-bold tracking-tight tabular-nums"
                                            >{{
                                                cents(
                                                    Math.abs(
                                                        entry.effect_cents,
                                                    ),
                                                    entry.currency,
                                                )
                                            }}</span
                                        >
                                    </template>
                                    <template v-else>{{
                                        effectLabel(entry)
                                    }}</template>
                                </p>
                            </div>
                            <div
                                class="flex min-w-0 flex-wrap items-center justify-between gap-2 border-t pt-1"
                            >
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                                    >{{ entry.status }}</span
                                ><Link
                                    v-if="entry.origin_url"
                                    :href="entry.origin_url"
                                    class="inline-flex min-h-11 items-center text-xs font-medium text-primary hover:underline"
                                    >{{ entry.origin_label }} →</Link
                                ><Button
                                    v-if="entry.can_cancel_payment"
                                    size="sm"
                                    variant="outline"
                                    @click="cancelLedger(entry)"
                                    >Annulla pagamento</Button
                                ><Button
                                    v-if="entry.is_settlement"
                                    size="sm"
                                    variant="outline"
                                    @click="editSettlement(entry)"
                                    >{{ ui('Modifica') }}</Button
                                ><Button
                                    v-if="entry.is_settlement"
                                    size="sm"
                                    variant="destructive"
                                    @click="deleteSettlement(entry)"
                                    >{{ ui('Elimina') }}</Button
                                >
                            </div>
                        </article>
                    </div>
                </div>
                <nav
                    v-if="pagination.last_page > 1"
                    :aria-label="ui('Registro movimenti')"
                    class="flex items-center justify-between gap-2 border-t p-3 text-sm"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="min-h-11"
                        :disabled="!pagination.prev_page_url"
                        @click="
                            pagination.prev_page_url &&
                            router.visit(pagination.prev_page_url, {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        "
                    >
                        {{ ui('Precedente') }}
                    </Button>
                    <span
                        class="text-center text-muted-foreground"
                        aria-live="polite"
                    >
                        {{ pagination.current_page }} /
                        {{ pagination.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        class="min-h-11"
                        :disabled="!pagination.next_page_url"
                        @click="
                            pagination.next_page_url &&
                            router.visit(pagination.next_page_url, {
                                preserveState: true,
                                preserveScroll: true,
                            })
                        "
                    >
                        {{ ui('Successiva') }}
                    </Button>
                </nav>
            </section>
        </div>
        <Dialog
            :open="cancelLedgerEntry !== null"
            @update:open="
                (value) => {
                    if (!value) cancelLedgerEntry = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle
                        >Annulla pagamento della controparte</DialogTitle
                    >
                    <DialogDescription
                        >Il movimento condiviso verrà eliminato e la scadenza
                        tornerà da pagare. Non verrà creata una
                        transazione.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelLedgerEntry = null"
                        >Indietro</Button
                    >
                    <Button @click="confirmCancelLedger"
                        >Conferma annullamento</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="settlementCounterparty !== null"
            @update:open="
                (open) => {
                    if (!open) settlementCounterparty = null;
                }
            "
        >
            <DialogContent
                class="max-h-[90dvh] min-w-0 overflow-x-hidden overflow-y-auto sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle>{{ ui('Registra rimborso') }}</DialogTitle
                    ><DialogDescription v-if="settlementCounterparty"
                        >{{
                            settlementCounterparty.balance > 0
                                ? ui('{name} ti deve {amount}', {
                                      name: settlementCounterparty.name,
                                      amount: cents(
                                          settlementCounterparty.balance,
                                      ),
                                  })
                                : ui('Devi {amount} a {name}', {
                                      name: settlementCounterparty.name,
                                      amount: cents(
                                          -settlementCounterparty.balance,
                                      ),
                                  })
                        }}{{
                            ui(
                                '. Verrà creato un movimento reale sul conto scelto.',
                            )
                        }}</DialogDescription
                    ></DialogHeader
                >
                <form
                    class="grid min-w-0 gap-4 [&_input]:max-md:h-11 [&_input]:max-md:min-w-0 [&_input]:max-md:rounded-2xl [&_input]:max-md:text-base"
                    @submit.prevent="submitSettlement"
                >
                    <div class="grid gap-1.5">
                        <Label v-if="!isMobile" for="settlement-amount">{{
                            ui('Importo')
                        }}</Label
                        ><MobileAmountInput
                            v-if="isMobile"
                            id="settlement-amount"
                            v-model="settlementForm.amount"
                            :format-locale="locale"
                            :currency-code="
                                settlementOptions.accounts.find(
                                    (item) =>
                                        item.uuid ===
                                        settlementForm.account_uuid,
                                )?.currency
                            "
                            :label="ui('Importo')"
                        />
                        <Input
                            v-else
                            id="settlement-amount"
                            v-model="settlementForm.amount"
                            inputmode="decimal"
                        /><InputError :message="settlementForm.errors.amount" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-date">{{ ui('Data') }}</Label
                        ><Input
                            id="settlement-date"
                            v-model="settlementForm.settled_at"
                            type="date"
                        /><InputError
                            :message="settlementForm.errors.settled_at"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-account">{{
                            ui('Risorsa / conto')
                        }}</Label
                        ><MobileSearchableSelect
                            v-if="isMobile"
                            v-model="settlementForm.account_uuid"
                            :options="accountChoices"
                            :placeholder="ui('Seleziona un conto')"
                            :mobile-title="ui('Risorsa / conto')"
                            trigger-class="min-h-11 rounded-2xl"
                            :teleport="false"
                        />
                        <select
                            v-else
                            id="settlement-account"
                            v-model="settlementForm.account_uuid"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">
                                {{ ui('Seleziona un conto') }}
                            </option>
                            <option
                                v-for="account in settlementOptions.accounts"
                                :key="account.uuid"
                                :value="account.uuid"
                            >
                                {{ account.name }} ({{ account.currency }})
                            </option></select
                        ><InputError
                            :message="settlementForm.errors.account_uuid"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-category">{{
                            ui('Categoria (facoltativa)')
                        }}</Label>
                        <div v-if="isMobile" class="grid min-w-0 gap-2">
                            <MobileQuickChoices
                                v-model="settlementForm.category_uuid"
                                :options="categoryChoices.slice(0, 8)"
                                appearance="category"
                            />
                            <MobileQuickChoices
                                v-if="categoryChildren.length"
                                v-model="settlementForm.category_uuid"
                                :options="categoryChildren"
                                appearance="category"
                            />
                            <MobileSearchableSelect
                                v-model="settlementForm.category_uuid"
                                :options="categoryChoices"
                                :placeholder="ui('Tutte · Cerca')"
                                :mobile-title="ui('Categoria')"
                                clearable
                                trigger-class="min-h-11 rounded-2xl"
                                :teleport="false"
                            />
                        </div>
                        <select
                            v-else
                            id="settlement-category"
                            v-model="settlementForm.category_uuid"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">
                                {{ ui('Nessuna categoria') }}
                            </option>
                            <option
                                v-for="category in accountCategories"
                                :key="category.uuid"
                                :value="category.uuid"
                            >
                                {{ category.name }}
                            </option></select
                        ><InputError
                            :message="settlementForm.errors.category_uuid"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="settlement-reference">{{
                            ui('Riferimento (facoltativo)')
                        }}</Label>
                        <div v-if="isMobile" class="grid min-w-0 gap-2">
                            <MobileQuickChoices
                                v-if="referenceChoices.length"
                                v-model="settlementForm.reference"
                                :options="quickReferences"
                                appearance="reference"
                            />
                            <div class="flex min-w-0 gap-2">
                                <MobileSearchableSelect
                                    v-model="settlementForm.reference"
                                    :options="referenceChoices"
                                    :placeholder="ui('Tutti · Cerca')"
                                    :mobile-title="ui('Riferimento')"
                                    clearable
                                    trigger-class="min-h-11 rounded-2xl"
                                    class="min-w-0 flex-1"
                                    :teleport="false"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="min-h-11 rounded-2xl"
                                    @click="
                                        newReferenceOpen = !newReferenceOpen
                                    "
                                    >{{ ui('+ Nuovo') }}</Button
                                >
                            </div>
                            <div
                                v-if="newReferenceOpen"
                                class="grid gap-2 rounded-2xl border bg-muted/30 p-3"
                            >
                                <Label for="settlement-reference-name">{{
                                    ui('Nome riferimento')
                                }}</Label>
                                <Input
                                    id="settlement-reference-name"
                                    v-model="newReferenceName"
                                />
                                <Label for="settlement-reference-site">{{
                                    ui('Sito web per il logo (opzionale)')
                                }}</Label>
                                <Input
                                    id="settlement-reference-site"
                                    v-model="newReferenceWebsite"
                                    type="url"
                                    placeholder="https://…"
                                />
                                <Button
                                    type="button"
                                    :disabled="creatingReference"
                                    @click="createSettlementReference"
                                    >{{ ui('Crea e seleziona') }}</Button
                                >
                            </div>
                        </div>
                        <Input
                            v-else
                            id="settlement-reference"
                            v-model="settlementForm.reference"
                        />
                    </div>
                    <InputError :message="settlementForm.errors.reference" />
                    <div class="grid gap-1.5">
                        <Label for="settlement-notes">{{
                            ui('Nota (facoltativa)')
                        }}</Label
                        ><textarea
                            id="settlement-notes"
                            v-model="settlementForm.notes"
                            class="min-h-20 w-full min-w-0 rounded-md border bg-background px-3 py-2 text-sm max-md:rounded-2xl max-md:p-3 max-md:text-base"
                        ></textarea>
                    </div>
                    <DialogFooter
                        ><Button
                            type="button"
                            variant="outline"
                            @click="settlementCounterparty = null"
                            >{{ ui('Annulla') }}</Button
                        ><Button
                            type="submit"
                            :disabled="settlementForm.processing"
                            >{{ ui('Registra rimborso') }}</Button
                        ></DialogFooter
                    >
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
