<script setup lang="ts">
import { TrendingUp, TrendingDown, ShieldCheck, Info } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { update } from '@/actions/App/Http/Controllers/RecurringEntryPriceConditionsController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useForm } from '@/composables/useNotifiedForm';
import type { RecurringPriceConditions } from '@/types/transactions';

type Choice = 'lock' | 'increase' | 'decrease';
const props = defineProps<{
    open?: boolean;
    collapsibleSummary?: boolean;
    recurringEntryUuid: string;
    conditions?: RecurringPriceConditions | null;
    currentAmount: number | null;
    currency: string;
    canEdit: boolean;
    direction?: string | null;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const { t: translate, te, locale } = useI18n();
function t(
    key: string,
    values: Record<string, string | number | null> = {},
): string {
    const incomeKey = key.replace(
        'recurringDetail.',
        'recurringDetail.income.',
    );

    return translate(
        props.direction === 'income' && te(incomeKey) ? incomeKey : key,
        values,
    );
}
const key = 'recurringDetail.priceConditions.guide.';
function amount(value: string | number | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(String(value).replace(',', '.'));

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}
function normalize(
    value?: RecurringPriceConditions | null,
): RecurringPriceConditions {
    const lockedUntil =
        !value?.type || value.type === 'locked_until'
            ? (value?.locked_until ?? null)
            : null;
    const newAmount =
        !value?.type || value.type === 'announced'
            ? (value?.new_amount ?? null)
            : null;

    return {
        list_price: value?.list_price ?? null,
        personal_price: value?.personal_price ?? null,
        lock_mode:
            value?.lock_mode ??
            (value?.type === 'locked'
                ? 'indefinite'
                : lockedUntil
                  ? 'until'
                  : null),
        locked_until: lockedUntil,
        change_scope:
            value?.change_scope ??
            (amount(newAmount) !== null ? 'personal' : null),
        change_direction: value?.change_direction ?? null,
        new_list_price: value?.new_list_price ?? null,
        new_amount: newAmount,
        effective_from: value?.effective_from ?? null,
    };
}
const form = useForm<RecurringPriceConditions>(normalize());
const active = ref<Choice | null>(null);
const duration = ref<'' | 'indefinite' | 'until'>('');
const applies = ref<'' | 'personal' | 'list_only'>('');
const nextAmount = ref('');
const effectiveDate = ref('');
const untilDate = ref('');
watch(
    () => [props.open, props.recurringEntryUuid] as const,
    () => {
        if (props.open) {
            form.defaults(normalize(props.conditions));
            form.reset();
            form.clearErrors();
            active.value = null;
        }
    },
    { immediate: true },
);
const currentPrice = computed(
    () => amount(form.personal_price) ?? props.currentAmount,
);
function money(value: number): string {
    return new Intl.NumberFormat(locale.value, {
        style: 'currency',
        currency: props.currency,
    }).format(value);
}
function date(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString(locale.value);
}
function change(base: number, next: number): string {
    const cents = Math.round(base * 100);
    const delta = Math.round(next * 100) - cents;
    const absolute = `${delta > 0 ? '+' : ''}${money(delta / 100)}`;

    return cents === 0
        ? `${absolute} (${t(key + 'noPercentage')})`
        : `${absolute} (${delta > 0 ? '+' : ''}${((delta / cents) * 100).toLocaleString(locale.value, { maximumFractionDigits: 2 })}%)`;
}
function rows(
    values: RecurringPriceConditions,
): { id: 'lock' | 'change'; text: string }[] {
    const result: { id: 'lock' | 'change'; text: string }[] = [];
    const current = amount(values.personal_price) ?? props.currentAmount;

    if (values.lock_mode) {
        result.push({
            id: 'lock',
            text: t(
                key +
                    (values.lock_mode === 'indefinite'
                        ? 'lockedForever'
                        : 'lockedUntil'),
                {
                    price: current !== null ? money(current) : '—',
                    date: values.locked_until ? date(values.locked_until) : '—',
                },
            ),
        });
    }

    if (values.change_scope) {
        const mine = values.change_scope === 'personal';
        const next = amount(mine ? values.new_amount : values.new_list_price);
        const previous = mine ? current : amount(values.list_price);
        const direction =
            values.change_direction ??
            (next !== null && previous !== null && next !== previous
                ? next > previous
                    ? 'increase'
                    : 'decrease'
                : 'changed');

        if (next !== null) {
            let text = t(key + (mine ? 'yourChange' : 'serviceChange'), {
                action: t(key + `verbs.${direction}`),
                price: money(next),
            });

            if (previous !== null) {
                text += ` ${t(key + 'from', { price: money(previous) })} · ${change(previous, next)}`;
            } else {
                text += ` · ${t(key + 'unknownPrevious')}`;
            }

            text += ` · ${values.effective_from ? t(key + 'fromDate', { date: date(values.effective_from) }) : t(key + 'noDate')}`;

            if (!mine) {
                text += ` · ${t(key + 'unaffected')}`;
            }

            result.push({ id: 'change', text });
        }

        if (mine && amount(values.new_list_price) !== null) {
            const previousService = amount(values.list_price);
            const nextService = amount(values.new_list_price)!;
            let text = t(key + 'serviceAlso', { price: money(nextService) });

            if (previousService !== null) {
                text += ` · ${change(previousService, nextService)}`;
            }

            if (result.length > 0) {
                result[result.length - 1].text += ` ${text}`;
            }
        }
    }

    return result;
}
function knownDate(value?: string | null): string | null {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const parsed = new Date(`${value}T00:00:00Z`);

    return Number.isFinite(parsed.getTime()) &&
        parsed.toISOString().slice(0, 10) === value
        ? date(value)
        : null;
}
const cardKey = 'recurringDetail.priceConditions.cards.';
const tones = {
    increase:
        'border-orange-200/80 bg-orange-50/60 text-orange-950 dark:border-orange-900/60 dark:bg-orange-950/20 dark:text-orange-100',
    decrease:
        'border-emerald-200/80 bg-emerald-50/60 text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-100',
    guarantee:
        'border-sky-200/80 bg-sky-50/60 text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/20 dark:text-sky-100',
    unchanged: 'border-border bg-muted/30 text-foreground',
};
const savedCards = computed(() => {
    const values = normalize(props.conditions);
    const current = amount(values.personal_price) ?? props.currentAmount;
    const cards: {
        id: string;
        kind: keyof typeof tones;
        title: string;
        price: number | null;
        previous: number | null;
        delta: number | null;
        percentage: number | null;
        timing: string;
        unaffected: boolean;
        extra: number | null;
        limit: string | null;
    }[] = [];

    if (values.lock_mode) {
        cards.push({
            id: 'lock',
            kind: 'guarantee',
            title: t(cardKey + 'guaranteed'),
            price: current,
            previous: null,
            delta: null,
            percentage: null,
            timing:
                values.lock_mode === 'indefinite'
                    ? t(cardKey + 'noExpiry')
                    : knownDate(values.locked_until)
                      ? t(cardKey + 'until', {
                            date: knownDate(values.locked_until),
                        })
                      : t(cardKey + 'noDate'),
            unaffected: false,
            extra: null,
            limit: null,
        });
    }

    if (values.change_scope) {
        const mine = values.change_scope === 'personal';
        const next = amount(mine ? values.new_amount : values.new_list_price);
        const previous = mine ? current : amount(values.list_price);

        if (next !== null) {
            const delta =
                previous === null
                    ? null
                    : Math.round(next * 100) - Math.round(previous * 100);
            const kind =
                delta === null
                    ? (values.change_direction ?? 'unchanged')
                    : delta > 0
                      ? 'increase'
                      : delta < 0
                        ? 'decrease'
                        : 'unchanged';
            const base = previous === null ? null : Math.round(previous * 100);
            cards.push({
                id: 'change',
                kind,
                title: t(cardKey + (mine ? 'your.' : 'service.') + kind),
                price: next,
                previous,
                delta: delta === null ? null : delta / 100,
                percentage:
                    base !== null && base > 0 && delta !== null
                        ? (delta / base) * 100
                        : null,
                timing: knownDate(values.effective_from)
                    ? t(cardKey + 'fromDate', {
                          date: knownDate(values.effective_from),
                      })
                    : t(cardKey + 'noDate'),
                unaffected: !mine,
                extra: mine ? amount(values.new_list_price) : null,
                limit: mine
                    ? null
                    : t(
                          cardKey +
                              (knownDate(values.effective_from)
                                  ? 'historyLimit'
                                  : 'missingDate'),
                      ),
            });
        }
    }

    return cards;
});
const summaryExpanded = ref(false);
const summaryButton = computed(
    () =>
        savedCards.value.find((card) => card.id === 'change') ??
        savedCards.value[0] ??
        null,
);
function toggleSummary(): void {
    summaryExpanded.value = !summaryExpanded.value;
}
watch(
    () => props.recurringEntryUuid,
    () => {
        summaryExpanded.value = false;
    },
);
watch(
    () => savedCards.value.length,
    (length) => {
        if (!length) {
            summaryExpanded.value = false;
        }
    },
);
defineExpose({ summaryButton, summaryExpanded, toggleSummary });
const savedCurrentPrice = computed(
    () => amount(props.conditions?.personal_price) ?? props.currentAmount,
);
function signedMoney(value: number): string {
    return `${value > 0 ? '+' : ''}${money(value)}`;
}
function signedPercentage(value: number): string {
    return `${value > 0 ? '+' : ''}${value.toLocaleString(locale.value, { maximumFractionDigits: 2 })}%`;
}
const draftRows = computed(() => rows(form.data()));
function choose(choice: Choice): void {
    active.value = choice;
    form.clearErrors();

    if (choice === 'lock') {
        duration.value = form.lock_mode ?? '';
        untilDate.value = form.locked_until ?? '';
    } else {
        applies.value = form.change_scope ?? '';
        nextAmount.value = String(
            (applies.value === 'list_only'
                ? form.new_list_price
                : form.new_amount) ?? '',
        );
        effectiveDate.value = form.effective_from ?? '';
    }
}
const directionError = computed(() => {
    if (
        !active.value ||
        active.value === 'lock' ||
        !applies.value ||
        amount(nextAmount.value) === null
    ) {
        return null;
    }

    const base =
        applies.value === 'personal'
            ? currentPrice.value
            : amount(form.list_price);

    if (base === null) {
        return null;
    }

    const previous = Math.round(base * 100),
        next = Math.round(amount(nextAmount.value)! * 100);

    return (active.value === 'increase' ? next <= previous : next >= previous)
        ? t(key + 'directionError')
        : null;
});
const compatibilityError = computed(() => {
    if (!active.value) {
        return null;
    }

    const values = editedValues();

    if (
        values.change_scope !== 'personal' ||
        amount(values.new_amount) === null
    ) {
        return null;
    }

    if (values.lock_mode === 'indefinite') {
        return t(key + 'incompatibleLock');
    }

    if (
        values.lock_mode === 'until' &&
        values.locked_until &&
        values.effective_from &&
        values.effective_from <= values.locked_until
    ) {
        return t(key + 'incompatibleDate');
    }

    return null;
});
const ready = computed(
    () =>
        !compatibilityError.value &&
        (active.value === 'lock'
            ? Boolean(
                  duration.value &&
                  (duration.value !== 'until' || untilDate.value),
              )
            : Boolean(
                  active.value &&
                  applies.value &&
                  amount(nextAmount.value) !== null &&
                  !directionError.value,
              )),
);
function editedValues(): RecurringPriceConditions {
    if (active.value === 'lock') {
        return {
            ...form.data(),
            lock_mode: duration.value || null,
            locked_until: duration.value === 'until' ? untilDate.value : null,
        };
    }

    return {
        ...form.data(),
        change_scope: applies.value || null,
        change_direction:
            active.value === 'increase' || active.value === 'decrease'
                ? active.value
                : null,
        new_amount: applies.value === 'personal' ? nextAmount.value : null,
        new_list_price:
            applies.value === 'list_only'
                ? nextAmount.value
                : form.new_list_price,
        effective_from: effectiveDate.value || null,
    };
}
const previewRows = computed(() => (ready.value ? rows(editedValues()) : []));
function apply(): void {
    if (!ready.value) {
        return;
    }

    Object.assign(form, editedValues());

    if (form.personal_price === null && props.currentAmount !== null) {
        form.personal_price = props.currentAmount;
    }

    active.value = null;
}
function edit(id: 'lock' | 'change'): void {
    if (id === 'lock') {
        choose('lock');

        return;
    }

    const base =
        form.change_scope === 'personal'
            ? currentPrice.value
            : amount(form.list_price);
    const next = amount(
        form.change_scope === 'personal'
            ? form.new_amount
            : form.new_list_price,
    );
    choose(
        form.change_direction ??
            (next !== null && base !== null && next < base
                ? 'decrease'
                : 'increase'),
    );
}
function remove(id: 'lock' | 'change'): void {
    active.value = null;
    form.clearErrors();

    if (id === 'lock') {
        form.lock_mode = null;
        form.locked_until = null;
    } else {
        form.change_scope = null;
        form.change_direction = null;
        form.new_amount = null;
        form.new_list_price = null;
        form.effective_from = null;
    }
}
function save(): void {
    if (active.value) {
        return;
    }

    form.patch(update.url(props.recurringEntryUuid), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <div class="contents">
        <Collapsible
            v-if="savedCards.length"
            class="contents"
            :open="!collapsibleSummary || summaryExpanded"
        >
            <CollapsibleContent
                :class="
                    collapsibleSummary
                        ? 'price-summary-content min-w-0'
                        : undefined
                "
            >
                <div
                    :id="`recurring-price-summary-${recurringEntryUuid}`"
                    role="region"
                    :aria-label="t('recurringDetail.panels.priceSummary')"
                    class="grid min-w-0 gap-3"
                    :class="savedCards.length > 1 ? 'sm:grid-cols-2' : ''"
                    data-price-conditions-summary
                >
                    <article
                        v-for="card in savedCards"
                        :key="card.id"
                        :data-price-summary-card="card.kind"
                        class="min-w-0 space-y-3 rounded-2xl border p-4 shadow-xs sm:p-5"
                        :class="
                            tones[
                                direction === 'income' &&
                                (card.kind === 'increase' ||
                                    card.kind === 'decrease')
                                    ? card.kind === 'increase'
                                        ? 'decrease'
                                        : 'increase'
                                    : card.kind
                            ]
                        "
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-background/70"
                                aria-hidden="true"
                                ><component
                                    :is="
                                        card.kind === 'guarantee'
                                            ? ShieldCheck
                                            : card.kind === 'increase'
                                              ? TrendingUp
                                              : card.kind === 'decrease'
                                                ? TrendingDown
                                                : Info
                                    "
                                    class="size-4"
                            /></span>
                            <div class="min-w-0 space-y-1">
                                <h3 class="text-sm leading-snug font-semibold">
                                    {{ card.title }}
                                </h3>
                                <p class="text-xs opacity-75">
                                    {{ card.timing }}
                                </p>
                            </div>
                        </div>
                        <p v-if="card.unaffected" class="text-xs font-medium">
                            {{ t(cardKey + 'servicePrice') }}
                        </p>
                        <div
                            class="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1"
                        >
                            <SensitiveValue
                                v-if="card.price !== null"
                                class="max-w-full min-w-0 text-2xl font-semibold tracking-tight [overflow-wrap:anywhere] tabular-nums sm:text-3xl"
                                :value="money(card.price)"
                            />
                            <span v-else class="text-sm">{{
                                t(cardKey + 'unknownPrice')
                            }}</span>
                            <span
                                v-if="card.previous !== null"
                                class="flex flex-wrap gap-1 text-sm opacity-75"
                                >{{ t(cardKey + 'before') }}
                                <SensitiveValue :value="money(card.previous)"
                            /></span>
                        </div>
                        <p
                            v-if="card.delta !== null"
                            class="flex flex-wrap items-center gap-2 text-sm font-medium"
                            data-price-difference
                        >
                            <span
                                class="basis-full text-xs font-normal opacity-75"
                                >{{
                                    t(
                                        cardKey +
                                            (card.unaffected
                                                ? 'serviceDifference'
                                                : direction === 'income'
                                                  ? 'yourDifference.' +
                                                    card.kind
                                                  : 'yourDifference'),
                                    )
                                }}</span
                            >
                            <SensitiveValue
                                :value="signedMoney(card.delta)"
                            /><span
                                v-if="card.percentage !== null"
                                class="rounded-full bg-background/70 px-2 py-0.5"
                                ><SensitiveValue
                                    :value="signedPercentage(card.percentage)"
                            /></span>
                        </p>
                        <p
                            v-else-if="card.id === 'change'"
                            class="text-xs opacity-75"
                        >
                            {{ t(cardKey + 'unknownPrevious') }}
                        </p>
                        <div
                            v-if="card.unaffected"
                            class="space-y-1 rounded-xl border border-current/10 bg-background/70 p-3"
                            data-price-unaffected
                        >
                            <p class="text-sm font-semibold">
                                {{ t(cardKey + 'unaffected') }}
                            </p>
                            <p
                                v-if="savedCurrentPrice !== null"
                                class="flex flex-wrap gap-1 text-sm"
                            >
                                {{ t(cardKey + 'youPay') }}
                                <SensitiveValue
                                    class="font-semibold tabular-nums"
                                    :value="money(savedCurrentPrice)"
                                />
                            </p>
                        </div>
                        <p
                            v-if="card.extra !== null"
                            class="flex flex-wrap gap-1 text-xs"
                        >
                            {{ t(cardKey + 'servicePrice') }}
                            <SensitiveValue :value="money(card.extra)" />
                        </p>
                    </article>
                </div>
            </CollapsibleContent>
        </Collapsible>
        <Dialog
            v-if="canEdit"
            :open="open"
            @update:open="emit('update:open', $event)"
        >
            <DialogContent
                class="flex max-h-[90dvh] flex-col gap-0 overflow-hidden rounded-2xl p-0 sm:max-w-lg"
            >
                <DialogHeader class="shrink-0 px-6 pt-6 pb-3">
                    <DialogTitle>{{
                        t('recurringDetail.priceConditions.manage')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('recurringDetail.priceConditions.informational')
                    }}</DialogDescription>
                </DialogHeader>
                <form
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                    @submit.prevent="active ? apply() : save()"
                >
                    <div class="space-y-4 overflow-y-auto px-6 py-3">
                        <p class="text-sm">
                            {{ t(key + 'today') }}
                            <SensitiveValue
                                v-if="currentPrice !== null"
                                :value="money(currentPrice)"
                            /><span v-else>—</span>
                        </p>
                        <div v-if="!active" class="space-y-4">
                            <div
                                v-if="draftRows.length"
                                class="space-y-2"
                                data-price-registered
                            >
                                <h3 class="text-sm font-medium">
                                    {{
                                        t(
                                            key +
                                                (form.isDirty
                                                    ? 'preview'
                                                    : 'registered'),
                                        )
                                    }}
                                </h3>
                                <div
                                    v-for="row in draftRows"
                                    :key="row.id"
                                    class="rounded-xl border p-3 text-sm"
                                >
                                    <p><SensitiveValue :value="row.text" /></p>
                                    <div class="mt-2 flex gap-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            :disabled="form.processing"
                                            :aria-label="
                                                t(key + 'edit') +
                                                ' · ' +
                                                t(key + row.id)
                                            "
                                            @click="edit(row.id)"
                                            >{{ t(key + 'edit') }}</Button
                                        >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive"
                                            :disabled="form.processing"
                                            :aria-label="
                                                t(key + 'remove') +
                                                ' · ' +
                                                t(key + row.id)
                                            "
                                            @click="remove(row.id)"
                                            >{{ t(key + 'remove') }}</Button
                                        >
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm font-medium">
                                {{
                                    t(
                                        key +
                                            (draftRows.length
                                                ? 'addMore'
                                                : 'whatHappened'),
                                    )
                                }}
                            </p>
                            <div class="grid gap-2">
                                <Button
                                    v-for="choice in [
                                        'lock',
                                        'increase',
                                        'decrease',
                                    ] as const"
                                    :key="choice"
                                    type="button"
                                    variant="outline"
                                    class="h-auto justify-start py-3 text-left whitespace-normal"
                                    :disabled="form.processing"
                                    @click="choose(choice)"
                                    >{{ t(key + 'actions.' + choice) }}</Button
                                >
                            </div>
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <h3 class="text-sm font-semibold">
                                    {{ t(key + 'actions.' + active) }}
                                </h3>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="active = null"
                                    >{{ t(key + 'back') }}</Button
                                >
                            </div>
                            <template v-if="active === 'lock'">
                                <p class="text-sm font-medium">
                                    {{ t(key + 'howLong') }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="mode in [
                                            'indefinite',
                                            'until',
                                        ] as const"
                                        :key="mode"
                                        type="button"
                                        :variant="
                                            duration === mode
                                                ? 'default'
                                                : 'outline'
                                        "
                                        :aria-pressed="duration === mode"
                                        @click="duration = mode"
                                        >{{
                                            t(key + 'durations.' + mode)
                                        }}</Button
                                    >
                                </div>
                                <div
                                    v-if="duration === 'until'"
                                    class="space-y-1"
                                >
                                    <Label for="price-locked-until">{{
                                        t(key + 'untilQuestion')
                                    }}</Label
                                    ><AppDatePicker
                                        id="price-locked-until"
                                        v-model="untilDate"
                                        required
                                    />
                                </div>
                                <p
                                    v-if="duration"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        t(
                                            'recurringDetail.priceConditions.expiryHint',
                                        )
                                    }}
                                </p>
                            </template>
                            <template v-else>
                                <p class="text-sm font-medium">
                                    {{ t(key + 'appliesQuestion') }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="scope in [
                                            'personal',
                                            'list_only',
                                        ] as const"
                                        :key="scope"
                                        type="button"
                                        :variant="
                                            applies === scope
                                                ? 'default'
                                                : 'outline'
                                        "
                                        :aria-pressed="applies === scope"
                                        @click="applies = scope"
                                        >{{
                                            t(key + 'applies.' + scope)
                                        }}</Button
                                    >
                                </div>
                                <template v-if="applies">
                                    <p
                                        v-if="applies === 'list_only'"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ t(key + 'unaffected') }}
                                    </p>
                                    <div class="space-y-1">
                                        <Label for="price-next-amount">{{
                                            t(key + 'amountQuestion')
                                        }}</Label
                                        ><MobileAmountInput
                                            id="price-next-amount"
                                            v-model="nextAmount"
                                            :currency-code="currency"
                                            required
                                        /><InputError
                                            :message="
                                                directionError ?? undefined
                                            "
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label for="price-effective-from">{{
                                            t(key + 'dateQuestion')
                                        }}</Label
                                        ><AppDatePicker
                                            id="price-effective-from"
                                            v-model="effectiveDate"
                                            :placeholder="t(key + 'noDate')"
                                        />
                                    </div>
                                    <p
                                        v-if="
                                            (applies === 'personal'
                                                ? currentPrice
                                                : amount(form.list_price)) ===
                                            null
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ t(key + 'unknownPrevious') }}
                                    </p>
                                </template>
                            </template>
                            <InputError
                                :message="compatibilityError ?? undefined"
                            />
                            <div
                                v-if="ready"
                                class="space-y-1 rounded-xl bg-muted/50 p-3 text-sm"
                                aria-live="polite"
                                data-price-preview
                            >
                                <h3 class="font-medium">
                                    {{ t(key + 'preview') }}
                                </h3>
                                <p v-for="row in previewRows" :key="row.id">
                                    <SensitiveValue :value="row.text" />
                                </p>
                            </div>
                        </div>
                        <InputError
                            v-for="(error, field) in form.errors"
                            :key="field"
                            :message="error"
                        />
                    </div>
                    <DialogFooter
                        class="shrink-0 gap-2 border-t bg-background p-4"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="emit('update:open', false)"
                            >{{
                                t('recurringDetail.priceConditions.cancel')
                            }}</Button
                        >
                        <Button
                            v-if="active"
                            type="submit"
                            :disabled="!ready || form.processing"
                            >{{ t(key + 'addToSummary') }}</Button
                        >
                        <Button
                            v-else
                            type="submit"
                            :disabled="!form.isDirty || form.processing"
                            >{{
                                t('recurringDetail.priceConditions.save')
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.price-summary-content {
    overflow: hidden;
}
.price-summary-content[data-state='open'] {
    animation: price-summary-open 180ms ease-out;
}
.price-summary-content[data-state='closed'] {
    animation: price-summary-close 140ms ease-in;
}
@keyframes price-summary-open {
    from {
        height: 0;
        opacity: 0;
    }
    to {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
}
@keyframes price-summary-close {
    from {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
    to {
        height: 0;
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .price-summary-content {
        animation: none !important;
    }
}
</style>
