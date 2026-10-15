<script setup lang="ts">
import { computed, ref } from 'vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { useUiCopy } from '@/i18n/ui-copy';
import { fetchWithNotifications } from '@/lib/notifications';
import { store as storeCounterparty } from '@/routes/shared-expenses/counterparties';

const ui = useUiCopy();
type Option = {
    value?: string;
    label?: string;
    uuid?: string | null;
    name?: string | null;
    is_selectable?: boolean;
};
type NormalizedOption = {
    value: string;
    label: string;
    is_selectable?: boolean;
};
const props = withDefaults(
    defineProps<{
        counterparties: Option[];
        disabled?: boolean;
        side?: 'top' | 'bottom';
        teleport?: boolean;
    }>(),
    { disabled: false, side: 'bottom', teleport: true },
);
const counterparty = defineModel<string>({ required: true });
const emit = defineEmits<{ created: [option: NormalizedOption] }>();
const createdCounterparties = ref<NormalizedOption[]>([]);
const creatingCounterparty = ref(false);
const counterpartyCreationError = ref<string | null>(null);
const counterparties = computed(() =>
    [...createdCounterparties.value, ...props.counterparties]
        .map(
            (item): NormalizedOption => ({
                value: item.value ?? ('uuid' in item ? (item.uuid ?? '') : ''),
                label: item.label ?? ('name' in item ? (item.name ?? '') : ''),
                is_selectable: item.is_selectable,
            }),
        )
        .filter(
            (item, index, items) =>
                item.value !== '' &&
                items.findIndex(
                    (candidate) => candidate.value === item.value,
                ) === index,
        )
        .sort((left, right) => left.label.localeCompare(right.label)),
);
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
        const response = await fetchWithNotifications(storeCounterparty.url(), {
            notification: 'Controparte creata correttamente.',
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

        if (
            !createdCounterparties.value.some(
                (item) => item.value === option.value,
            )
        ) {
            createdCounterparties.value.push(option);
        }

        counterparty.value = option.value;
        emit('created', option);
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
    <div class="grid min-w-0 gap-2">
        <MobileSearchableSelect
            v-model="counterparty"
            :options="counterparties"
            :disabled="disabled"
            :side="side"
            :teleport="teleport"
            clearable
            creatable
            :creating="creatingCounterparty"
            :placeholder="ui('Cerca una controparte')"
            :search-placeholder="ui('Cerca una controparte')"
            :mobile-title="ui('Scegli una controparte')"
            :empty-label="ui('Nessuna controparte')"
            :create-label="ui('Aggiungi controparte')"
            @create-option="createCounterparty"
        />
        <Button
            v-if="counterparty && !disabled"
            type="button"
            variant="link"
            class="h-auto justify-start p-0 text-xs"
            @click="counterparty = ''"
            >{{ ui('Nessuna controparte') }}</Button
        >
        <p
            v-if="counterpartyCreationError"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ counterpartyCreationError }}
        </p>
    </div>
</template>
