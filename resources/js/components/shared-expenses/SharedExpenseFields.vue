<script setup lang="ts">
import { UsersRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUiCopy } from '@/i18n/ui-copy';
import { store as storeCounterparty } from '@/routes/shared-expenses/counterparties';

const ui = useUiCopy();

type Option = {
    value?: string;
    label?: string;
    uuid?: string | null;
    name?: string | null;
};
type NormalizedOption = { value: string; label: string };
const props = withDefaults(
    defineProps<{
        counterparties: Option[];
        amount: string;
        errors?: Record<string, string | undefined>;
        dropdownSide?: 'top' | 'bottom';
        dropdownTeleport?: boolean;
        decorated?: boolean;
    }>(),
    {
        dropdownSide: 'bottom',
        dropdownTeleport: true,
        decorated: false,
    },
);
const enabled = defineModel<boolean>('enabled', { required: true });
const counterparty = defineModel<string>('counterparty', { required: true });
const paidBy = defineModel<'self' | 'counterparty'>('paidBy', {
    required: true,
});
const myShare = defineModel<string>('myShare', { required: true });
const counterpartyShare = defineModel<string>('counterpartyShare', {
    required: true,
});
const custom = defineModel<boolean>('custom', { default: false });
const createdCounterparties = ref<NormalizedOption[]>([]);
const creatingCounterparty = ref(false);
const counterpartyCreationError = ref<string | null>(null);
const counterparties = computed(() =>
    [...props.counterparties, ...createdCounterparties.value]
        .map(
            (item): NormalizedOption => ({
                value: item.value ?? ('uuid' in item ? (item.uuid ?? '') : ''),
                label: item.label ?? ('name' in item ? (item.name ?? '') : ''),
            }),
        )
        .filter((item) => item.value !== '' && item.label !== '')
        .filter(
            (item, index, items) =>
                items.findIndex(
                    (candidate) => candidate.value === item.value,
                ) === index,
        )
        .sort((left, right) => left.label.localeCompare(right.label, 'it')),
);
const selected = computed(() =>
    counterparties.value.find((item) => item.value === counterparty.value),
);
const hasCounterparty = computed(() => selected.value !== undefined);
const counterpartyLabel = computed(
    () => selected.value?.label ?? ui('Controparte'),
);
function cents(value: string): number | null {
    const normalized = value.replace(',', '.');

    return /^\d+(\.\d{0,2})?$/.test(normalized)
        ? Math.round(Number(normalized || 0) * 100)
        : null;
}
function format(centsValue: number): string {
    return (centsValue / 100).toFixed(2);
}
function split(): void {
    const total = cents(props.amount);

    if (total === null) {
        return;
    }

    myShare.value = format(Math.floor(total / 2));
    counterpartyShare.value = format(total - Math.floor(total / 2));
}
function hasCustomAllocation(): boolean {
    const total = cents(props.amount);
    const mine = cents(myShare.value);
    const other = cents(counterpartyShare.value);

    if (
        total === null ||
        mine === null ||
        other === null ||
        mine + other !== total
    ) {
        return false;
    }

    return (
        mine !== Math.floor(total / 2) ||
        other !== total - Math.floor(total / 2)
    );
}
function setMine(value: string): void {
    myShare.value = value;
    const total = cents(props.amount);
    const mine = cents(value);

    if (total !== null && mine !== null && mine >= 0 && mine <= total) {
        counterpartyShare.value = format(total - mine);
    }
}
watch(
    [enabled, counterparty, () => props.amount, custom],
    () => {
        if (!enabled.value || !hasCounterparty.value || custom.value) {
            return;
        }

        if (hasCustomAllocation()) {
            custom.value = true;

            return;
        }

        split();
    },
    { immediate: true },
);

watch(counterparty, () => {
    if (hasCounterparty.value) {
        return;
    }

    paidBy.value = 'self';
    custom.value = false;
    myShare.value = '';
    counterpartyShare.value = '';
});

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function createCounterparty(name: string): Promise<void> {
    const normalizedName = name.trim();

    if (normalizedName === '') {
        return;
    }

    creatingCounterparty.value = true;
    counterpartyCreationError.value = null;

    try {
        const response = await fetch(storeCounterparty.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ name: normalizedName }),
        });
        const payload = (await response.json()) as {
            uuid?: string;
            name?: string;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || !payload.uuid || !payload.name) {
            counterpartyCreationError.value =
                payload.errors?.name?.[0] ??
                payload.message ??
                ui('Impossibile creare la controparte.');

            return;
        }

        const option = { value: payload.uuid, label: payload.name };

        if (!counterparties.value.some((item) => item.value === option.value)) {
            createdCounterparties.value.push(option);
        }

        counterparty.value = option.value;
        paidBy.value = 'self';
    } catch {
        counterpartyCreationError.value = ui(
            'Impossibile creare la controparte.',
        );
    } finally {
        creatingCounterparty.value = false;
    }
}
</script>

<template>
    <div
        class="grid min-w-0 gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-800"
        :class="
            decorated
                ? enabled
                    ? '!border-primary/30 bg-primary/5 !p-4'
                    : '!border-border bg-background !p-4'
                : ''
        "
    >
        <label
            class="flex min-w-0 items-center gap-3"
            :class="decorated ? 'min-h-11 cursor-pointer' : ''"
            ><span
                v-if="decorated"
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300"
                ><UsersRound class="size-5" aria-hidden="true"
            /></span>
            <span
                :class="
                    decorated
                        ? 'min-w-0 flex-1 text-sm font-semibold'
                        : 'order-2'
                "
                >{{ ui('Spesa condivisa') }}</span
            >
            <span
                :class="
                    decorated
                        ? 'relative flex h-6 w-11 shrink-0 items-center'
                        : 'contents'
                "
            >
                <input
                    v-model="enabled"
                    type="checkbox"
                    :class="
                        decorated
                            ? 'peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0'
                            : 'size-4 shrink-0'
                    "
                />
                <template v-if="decorated">
                    <span
                        aria-hidden="true"
                        class="h-6 w-11 rounded-full bg-muted transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2"
                    />
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-background shadow-sm transition-transform peer-checked:translate-x-5"
                    />
                </template> </span
        ></label>
        <template v-if="enabled">
            <div class="grid min-w-0 gap-2">
                <Label>{{ ui('Condivisa con') }}</Label>
                <p
                    v-if="counterparties.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        ui(
                            'Nessuna controparte abilitata. Cerca un nome e usa Aggiungi controparte, oppure abilita un Riferimento esistente.',
                        )
                    }}
                </p>
                <div class="hidden md:block">
                    <SearchableSelect
                        v-model="counterparty"
                        :options="counterparties"
                        :placeholder="ui('Cerca una controparte')"
                        clearable
                        creatable
                        :creating="creatingCounterparty"
                        :create-label="ui('Aggiungi controparte')"
                        :side="dropdownSide"
                        :teleport="dropdownTeleport"
                        @create-option="createCounterparty"
                    />
                </div>
                <div class="md:hidden">
                    <MobileSearchableSelect
                        v-model="counterparty"
                        :options="counterparties"
                        :placeholder="ui('Cerca una controparte')"
                        :search-placeholder="ui('Cerca una controparte')"
                        :empty-label="ui('Nessuna controparte')"
                        :mobile-title="ui('Scegli una controparte')"
                        :mobile-description="
                            ui(
                                'Cerca o seleziona la persona con cui condividi la spesa.',
                            )
                        "
                        creatable
                        :creating="creatingCounterparty"
                        :create-label="ui('Aggiungi controparte')"
                        @create-option="createCounterparty"
                    />
                </div>
                <p
                    v-if="counterpartyCreationError"
                    class="text-sm text-destructive"
                >
                    {{ counterpartyCreationError }}
                </p>
                <p
                    v-if="errors?.shared_expense_counterparty_uuid"
                    class="text-sm text-destructive"
                >
                    {{ errors.shared_expense_counterparty_uuid }}
                </p>
            </div>
            <div class="grid gap-2">
                <Label>{{ ui('Pagato da') }}</Label>
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        class="min-h-10 min-w-0 px-3 text-center whitespace-normal"
                        :variant="paidBy === 'self' ? 'default' : 'outline'"
                        @click="paidBy = 'self'"
                        >{{ ui('Io') }}</Button
                    ><Button
                        type="button"
                        class="min-h-10 min-w-0 px-3 text-center whitespace-normal"
                        :disabled="!hasCounterparty"
                        :variant="
                            paidBy === 'counterparty' ? 'default' : 'outline'
                        "
                        @click="paidBy = 'counterparty'"
                        >{{ counterpartyLabel }}</Button
                    >
                </div>
            </div>
            <template v-if="hasCounterparty">
                <div
                    class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                >
                    <span class="text-sm"
                        >{{ ui('Divisione:') }}
                        {{ custom ? ui('Personalizzata') : '50% / 50%' }}</span
                    ><Button
                        v-if="!custom"
                        type="button"
                        variant="link"
                        class="h-auto justify-start p-0 sm:justify-end"
                        @click="custom = true"
                        >{{ ui('Modifica quote') }}</Button
                    ><Button
                        v-else
                        type="button"
                        variant="link"
                        class="h-auto justify-start p-0 sm:justify-end"
                        @click="
                            custom = false;
                            split();
                        "
                        >{{ ui('Ripristina 50/50') }}</Button
                    >
                </div>
                <div v-if="custom" class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <Label>{{ ui('Mia quota') }}</Label
                        ><Input
                            :model-value="myShare"
                            inputmode="decimal"
                            @update:model-value="setMine(String($event))"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label>{{ ui('Quota') }} {{ counterpartyLabel }}</Label
                        ><Input
                            v-model="counterpartyShare"
                            inputmode="decimal"
                        />
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
