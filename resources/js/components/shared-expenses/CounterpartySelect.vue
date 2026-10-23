<script setup lang="ts">
import { computed, ref } from 'vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { useUiCopy } from '@/i18n/ui-copy';
import { fetchWithNotifications } from '@/lib/notifications';
import { store as storeCounterparty } from '@/routes/shared-expenses/counterparties';

const ui = useUiCopy();
type Option = {
    reference_name?: string;
    logo_url?: string | null;
    value?: string;
    label?: string;
    uuid?: string | null;
    name?: string | null;
    is_selectable?: boolean;
    search_terms?: string[];
};
type NormalizedOption = {
    reference_name?: string;
    logo_url?: string | null;
    value: string;
    label: string;
    is_selectable?: boolean;
    search_terms?: string[];
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
                reference_name:
                    item.reference_name ??
                    ('name' in item ? (item.name ?? undefined) : undefined),
                logo_url: item.logo_url,
                is_selectable: item.is_selectable,
                search_terms: item.search_terms,
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

async function createCounterparty(
    name: string,
    trackedItemUuid?: string,
): Promise<void> {
    const normalizedName = name.trim();

    if (normalizedName === '') {
        return;
    }

    creatingCounterparty.value = true;
    counterpartyCreationError.value = null;

    try {
        const response = await fetchWithNotifications(storeCounterparty.url(), {
            notification: 'Riferimento selezionato.',
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name: trackedItemUuid ? undefined : normalizedName,
                tracked_item_uuid: trackedItemUuid,
            }),
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
            !trackedItemUuid &&
            !createdCounterparties.value.some(
                (item) => item.value === option.value,
            )
        ) {
            createdCounterparties.value.push(option);
        }

        counterparty.value = option.value;

        if (!trackedItemUuid) {
            emit('created', option);
        }
    } catch {
        counterpartyCreationError.value = ui(
            'Impossibile creare la controparte.',
        );
    } finally {
        creatingCounterparty.value = false;
    }
}
function selectCounterparty(uuid: string): void {
    if (!uuid) {
        counterparty.value = '';

        return;
    }

    const option = counterparties.value.find((item) => item.value === uuid);

    if (option) {
        counterparty.value = uuid;
        counterpartyCreationError.value = null;
    }
}
</script>
<template>
    <div class="grid min-w-0 gap-2">
        <MobileSearchableSelect
            :model-value="counterparty"
            @update:model-value="selectCounterparty"
            :options="counterparties"
            :disabled="disabled || creatingCounterparty"
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
