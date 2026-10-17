<script setup lang="ts">
import {
    ArrowDown,
    ArrowRight,
    Check,
    LoaderCircle,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    canMoveMovements,
    isCurrentMovementRequest,
    movementDestinations,
    searchMovementDestinations,
} from '@/lib/tracked-item-movements';
import type {
    MovementOption,
    MovementPreview,
} from '@/lib/tracked-item-movements';
import {
    options as loadOptions,
    preview as loadPreview,
    store,
} from '@/routes/tracked-items/movements';

const props = defineProps<{
    source: { uuid: string; name: string; full_path?: string } | null;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ moved: [] }>();
const options = ref<MovementOption[]>([]);
const destination = ref('');
const search = ref('');
const preview = ref<MovementPreview | null>(null);
const loading = ref(false);
const checking = ref(false);
const moving = ref(false);
const error = ref('');
const result = ref('');
let revision = 0;
const sourceUuid = computed(() => props.source?.uuid ?? '');
const destinations = computed(() =>
    movementDestinations(options.value, sourceUuid.value),
);
const matches = computed(() =>
    searchMovementDestinations(options.value, sourceUuid.value, search.value),
);
const selected = computed(() =>
    destinations.value.find((option) => option.value === destination.value),
);
const canConfirm = computed(
    () =>
        !!selected.value &&
        canMoveMovements(
            preview.value,
            moving.value || checking.value || loading.value,
        ),
);

async function json<T>(url: string, init?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        ...init,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...init?.headers,
        },
    });
    const payload = await response.json();

    if (!response.ok) {
        throw new Error(
            Object.values(payload.errors ?? {})
                .flat()
                .join(' ') ||
                payload.message ||
                'Operazione non disponibile.',
        );
    }

    return payload as T;
}
watch([open, sourceUuid], async ([visible]) => {
    const current = ++revision;

    if (!visible) {
        return;
    }

    destination.value = '';
    search.value = '';
    preview.value = null;
    result.value = '';
    error.value = '';
    options.value = [];
    checking.value = false;
    loading.value = true;

    try {
        const payload = await json<{ items: MovementOption[] }>(
            loadOptions.url(),
        );

        if (isCurrentMovementRequest(current, revision, open.value)) {
            options.value = payload.items;
        }
    } catch (failure) {
        if (isCurrentMovementRequest(current, revision, open.value)) {
            error.value = (failure as Error).message;
        }
    } finally {
        if (isCurrentMovementRequest(current, revision, open.value)) {
            loading.value = false;
        }
    }
});
async function selectDestination(value: string): Promise<void> {
    if (
        moving.value ||
        !destinations.value.some((option) => option.value === value)
    ) {
        return;
    }

    destination.value = value;
    const current = ++revision;
    preview.value = null;
    error.value = '';
    checking.value = true;

    try {
        const payload = await json<MovementPreview>(
            loadPreview.url({
                query: {
                    source_uuid: sourceUuid.value,
                    destination_uuid: value,
                },
            }),
        );

        if (isCurrentMovementRequest(current, revision, open.value)) {
            preview.value = payload;
        }
    } catch (failure) {
        if (isCurrentMovementRequest(current, revision, open.value)) {
            error.value = (failure as Error).message;
        }
    } finally {
        if (isCurrentMovementRequest(current, revision, open.value)) {
            checking.value = false;
        }
    }
}
async function move(): Promise<void> {
    if (!canConfirm.value || !preview.value) {
        return;
    }

    moving.value = true;
    error.value = '';

    try {
        const payload = await json<{ message: string }>(store.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ??
                        '',
                ),
            },
            body: JSON.stringify({
                source_uuid: sourceUuid.value,
                destination_uuid: destination.value,
                preview_token: preview.value.preview_token,
                confirmed: true,
            }),
        });
        result.value = payload.message;
        preview.value = null;
        emit('moved');
    } catch (failure) {
        error.value = (failure as Error).message;
        preview.value = null;
    } finally {
        moving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90dvh] gap-5 overflow-y-auto rounded-3xl sm:max-w-lg"
            :show-close-button="!moving"
            @interact-outside="moving && $event.preventDefault()"
            @escape-key-down="moving && $event.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle>Sposta movimenti</DialogTitle>
                <DialogDescription
                    >Sposta tutti i collegamenti dal riferimento di origine alla
                    destinazione, incluse ricorrenze e pianificazioni. Entrambi
                    i riferimenti resteranno disponibili.</DialogDescription
                >
            </DialogHeader>
            <div class="min-w-0 space-y-4">
                <div
                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                >
                    <p class="text-xs font-medium text-muted-foreground">Da</p>
                    <p class="mt-1 font-semibold break-words">
                        {{ source?.name }}
                    </p>
                    <p
                        v-if="
                            source?.full_path &&
                            source.full_path !== source.name
                        "
                        class="mt-1 text-xs break-words text-muted-foreground"
                    >
                        {{ source.full_path }}
                    </p>
                </div>
                <template v-if="!result">
                    <ArrowDown
                        class="mx-auto size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div class="space-y-2">
                        <Label for="movement-destination-search"
                            >Destinazione</Label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                id="movement-destination-search"
                                v-model="search"
                                placeholder="Cerca un riferimento per nome"
                                class="h-11 rounded-xl pl-9"
                                autocomplete="off"
                                :disabled="loading || moving"
                            />
                        </div>
                        <p
                            v-if="loading"
                            role="status"
                            class="py-4 text-sm text-muted-foreground"
                        >
                            Caricamento riferimenti…
                        </p>
                        <div
                            v-else
                            class="max-h-52 space-y-1 overflow-y-auto rounded-2xl border border-slate-200 p-1.5 dark:border-slate-800"
                            aria-label="Riferimenti di destinazione"
                        >
                            <button
                                v-for="item in matches"
                                :key="item.value"
                                type="button"
                                :disabled="moving"
                                :aria-pressed="destination === item.value"
                                :class="[
                                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:outline-none disabled:opacity-50',
                                    destination === item.value
                                        ? 'bg-sky-50 text-sky-900 dark:bg-sky-500/15 dark:text-sky-100'
                                        : 'hover:bg-slate-50 dark:hover:bg-slate-900',
                                ]"
                                @click="selectDestination(item.value)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block text-sm font-medium break-words"
                                        >{{ item.label }}</span
                                    >
                                    <span
                                        class="mt-0.5 block text-xs break-words text-muted-foreground"
                                        >{{ item.full_path }}</span
                                    >
                                </span>
                                <Check
                                    v-if="destination === item.value"
                                    class="size-4 shrink-0 text-sky-600 dark:text-sky-400"
                                    aria-hidden="true"
                                />
                            </button>
                            <p
                                v-if="matches.length === 0"
                                class="p-4 text-sm text-muted-foreground"
                            >
                                {{
                                    destinations.length === 0
                                        ? 'Nessun altro riferimento disponibile.'
                                        : 'Nessun riferimento trovato.'
                                }}
                            </p>
                        </div>
                    </div>
                    <p v-if="selected" class="text-sm text-muted-foreground">
                        Destinazione:
                        <strong class="font-medium text-foreground">{{
                            selected.label
                        }}</strong>
                    </p>
                    <p
                        v-if="checking"
                        role="status"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <LoaderCircle class="size-4 animate-spin" />Verifica
                        movimenti…
                    </p>
                    <div v-else-if="preview" class="space-y-3">
                        <p role="status" class="text-sm font-medium">
                            {{ preview.count }}
                            {{
                                preview.block_reason
                                    ? 'collegamenti rilevati. L’operazione è bloccata.'
                                    : 'collegamenti interessati. Verranno spostati tutti, senza selezione individuale.'
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Cambiano soltanto i riferimenti. Nomi, descrizioni,
                            importi e date restano invariati. Le ricorrenze
                            useranno la destinazione anche per i prossimi
                            movimenti. Il ripristino entro 30 giorni riguarda
                            solo i record di questa operazione; quelli creati
                            dopo restano invariati.
                        </p>
                        <section
                            v-for="group in preview.groups"
                            :key="group.key"
                            class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                        >
                            <h3 class="text-sm font-semibold">
                                {{ group.count }} {{ group.label }}
                            </h3>
                            <ul
                                class="mt-2 max-h-40 space-y-2 overflow-y-auto text-xs text-muted-foreground"
                            >
                                <li
                                    v-for="item in group.items"
                                    :key="item.key"
                                    class="break-words"
                                >
                                    {{ item.title
                                    }}<span v-if="item.date">
                                        · {{ item.date.slice(0, 10) }}</span
                                    ><span v-if="item.archived">
                                        · eliminato</span
                                    >
                                </li>
                            </ul>
                        </section>
                        <p
                            v-if="preview.block_reason"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ preview.block_reason }} Nessun collegamento verrà
                            modificato.
                        </p>
                        <p v-else class="text-sm">
                            Confermi lo spostamento di tutti questi
                            collegamenti?
                        </p>
                    </div>
                    <p
                        v-if="error"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </template>
                <p v-else role="status" class="text-sm">{{ result }}</p>
            </div>
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    class="h-11 rounded-xl"
                    :disabled="moving"
                    @click="open = false"
                    >{{ result ? 'Chiudi' : 'Annulla' }}</Button
                >
                <Button
                    v-if="!result"
                    class="h-11 rounded-xl bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600"
                    :disabled="!canConfirm"
                    @click="move"
                >
                    <LoaderCircle v-if="moving" class="size-4 animate-spin" />
                    <ArrowRight v-else class="size-4" />
                    {{ moving ? 'Spostamento…' : 'Conferma spostamento' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
