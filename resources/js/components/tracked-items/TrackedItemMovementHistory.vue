<script setup lang="ts">
import { RotateCcw } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { canRestoreMovement } from '@/lib/tracked-item-movements';
import type { MovementHistoryEntry } from '@/lib/tracked-item-movements';
import { history, restore } from '@/routes/tracked-items/movements';

const props = defineProps<{ refreshKey: number }>();
const emit = defineEmits<{
    restored: [];
    availability: [hasRestorable: boolean];
}>();
const status = ref<'pending' | 'restored' | 'blocked'>('pending');
const tabs = [
    {
        value: 'pending',
        label: 'Da ripristinare',
        color: 'border-sky-300 text-sky-800 hover:bg-sky-100 dark:border-sky-800 dark:text-sky-200 dark:hover:bg-sky-900',
        active: 'bg-sky-100 dark:bg-sky-900',
    },
    {
        value: 'restored',
        label: 'Ripristinati',
        color: 'border-emerald-300 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-900',
        active: 'bg-emerald-100 dark:bg-emerald-900',
    },
    {
        value: 'blocked',
        label: 'Non ripristinabili',
        color: 'border-red-300 text-red-800 hover:bg-red-100 dark:border-red-800 dark:text-red-200 dark:hover:bg-red-900',
        active: 'bg-red-100 dark:bg-red-900',
    },
] as const;
function selectTab(value: 'pending' | 'restored' | 'blocked'): void {
    status.value = value;
    void load(1);
}
function adjacentTab(direction: number): void {
    const index = tabs.findIndex((tab) => tab.value === status.value);
    selectTab(tabs[(index + direction + tabs.length) % tabs.length].value);
    document.getElementById(`movement-tab-${status.value}`)?.focus();
}
const entries = ref<MovementHistoryEntry[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const restoring = ref(false);
const selected = ref('');
const error = ref('');
const message = ref('');
const unavailable = ref('');
let revision = 0;
function date(value: string): string {
    return new Date(value).toLocaleString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
async function json<T>(url: string, init?: RequestInit): Promise<T> {
    const response = await fetch(url, {
        ...init,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...init?.headers,
        },
    });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(
            Object.values(data.errors ?? {})
                .flat()
                .join(' ') ||
                data.message ||
                'Operazione non disponibile.',
        );
    }

    return data as T;
}
async function load(number: number): Promise<void> {
    const current = ++revision;
    loading.value = true;
    error.value = '';
    selected.value = '';

    try {
        const data = await json<{
            data: MovementHistoryEntry[];
            current_page: number;
            last_page: number;
            unavailable_reason?: string;
            has_restorable: boolean;
        }>(history.url({ query: { page: number, status: status.value } }));

        if (current !== revision) {
            return;
        }

        emit('availability', data.has_restorable);
        entries.value = data.data;
        page.value = data.current_page;
        lastPage.value = data.last_page;
        unavailable.value = data.unavailable_reason ?? '';
    } catch (failure) {
        if (current === revision) {
            error.value = (failure as Error).message;
        }
    } finally {
        if (current === revision) {
            loading.value = false;
        }
    }
}
watch(
    () => props.refreshKey,
    () => {
        void load(1);
    },
    { immediate: true },
);
async function undo(entry: MovementHistoryEntry): Promise<void> {
    if (
        selected.value !== entry.uuid ||
        !canRestoreMovement(entry, restoring.value || loading.value)
    ) {
        return;
    }

    restoring.value = true;
    error.value = '';
    message.value = '';

    try {
        const data = await json<{ message: string }>(restore.url(entry.uuid), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ??
                        '',
                ),
            },
            body: JSON.stringify({ confirmed: true }),
        });
        message.value = data.message;
        emit('restored');
        await load(page.value);
    } catch (failure) {
        const reason = (failure as Error).message;
        await load(page.value);
        error.value = reason;
    } finally {
        restoring.value = false;
    }
}
</script>

<template>
    <section
        class="space-y-4 rounded-3xl border border-slate-200 bg-white/95 p-5 dark:border-slate-800 dark:bg-slate-950/80"
        aria-label="Storico spostamenti"
    >
        <div>
            <h2 class="text-base font-semibold">Storico spostamenti</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Ripristina i riferimenti originali entro 30 giorni, se i
                collegamenti sono ancora compatibili. I movimenti creati
                successivamente restano invariati.
            </p>
        </div>
        <div
            role="tablist"
            aria-label="Stato ripristino"
            class="flex flex-wrap gap-2"
        >
            <Button
                v-for="tab in tabs"
                :id="`movement-tab-${tab.value}`"
                :key="tab.value"
                role="tab"
                aria-controls="movement-history-panel"
                :aria-selected="status === tab.value"
                variant="outline"
                :class="[
                    tab.color,
                    status === tab.value ? tab.active : 'bg-transparent',
                ]"
                :tabindex="status === tab.value ? 0 : -1"
                :disabled="restoring"
                @click="selectTab(tab.value)"
                @keydown.right.prevent="adjacentTab(1)"
                @keydown.left.prevent="adjacentTab(-1)"
                >{{ tab.label }}</Button
            >
        </div>
        <div
            id="movement-history-panel"
            role="tabpanel"
            :aria-labelledby="`movement-tab-${status}`"
            :aria-busy="loading"
            class="space-y-4"
        >
            <p v-if="error" role="alert" class="text-sm text-destructive">
                {{ error }}
            </p>
            <p v-if="message" role="status" class="text-sm">{{ message }}</p>
            <p v-if="unavailable" class="text-sm text-muted-foreground">
                {{ unavailable }}
            </p>
            <p
                v-else-if="loading"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Caricamento storico…
            </p>
            <p
                v-else-if="!entries.length"
                class="text-sm text-muted-foreground"
            >
                {{
                    status === 'restored'
                        ? 'Nessuno spostamento ripristinato.'
                        : status === 'blocked'
                          ? 'Nessuno spostamento non ripristinabile.'
                          : 'Nessuno spostamento da ripristinare.'
                }}
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="entry in entries"
                    :key="entry.uuid"
                    class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                    >
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium break-words">
                                {{ entry.source_name }} →
                                {{ entry.destination_name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    entry.groups
                                        .map(
                                            (group) =>
                                                group.count + ' ' + group.label,
                                        )
                                        .join(' · ')
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.count }} collegamenti ·
                                {{ entry.actor_name }} ·
                                {{ date(entry.created_at) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    entry.restored_at
                                        ? 'Ripristinato'
                                        : entry.can_restore
                                          ? 'Ripristinabile fino al'
                                          : 'Termine previsto per il ripristino:'
                                }}
                                {{ date(entry.restored_at ?? entry.expires_at)
                                }}<template v-if="entry.restored_by_name">
                                    · {{ entry.restored_by_name }}</template
                                >
                            </p>
                            <p
                                v-if="entry.block_reason && !entry.restored_at"
                                class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                            >
                                {{ entry.block_reason }}
                            </p>
                        </div>
                        <Button
                            v-if="entry.can_restore && !entry.restored_at"
                            variant="outline"
                            class="h-10 shrink-0 rounded-xl"
                            :disabled="
                                !canRestoreMovement(entry, restoring || loading)
                            "
                            @click="selected = entry.uuid"
                            ><RotateCcw class="size-4" />Ripristina</Button
                        >
                    </div>
                    <div
                        v-if="selected === entry.uuid"
                        class="space-y-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-900"
                    >
                        <p class="text-sm">
                            Ripristinare il riferimento originale dei
                            {{ entry.count }} collegamenti?
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                :disabled="restoring"
                                @click="selected = ''"
                                >Annulla</Button
                            >
                            <Button
                                :disabled="
                                    !canRestoreMovement(
                                        entry,
                                        restoring || loading,
                                    )
                                "
                                @click="undo(entry)"
                                >{{
                                    restoring
                                        ? 'Ripristino…'
                                        : 'Conferma ripristino'
                                }}</Button
                            >
                        </div>
                    </div>
                </li>
            </ul>
            <div
                v-if="lastPage > 1"
                class="flex items-center justify-between gap-3"
            >
                <Button
                    variant="outline"
                    :disabled="loading || restoring || page <= 1"
                    @click="load(page - 1)"
                    >Precedente</Button
                >
                <span class="text-sm text-muted-foreground"
                    >{{ page }} / {{ lastPage }}</span
                >
                <Button
                    variant="outline"
                    :disabled="loading || restoring || page >= lastPage"
                    @click="load(page + 1)"
                    >Successiva</Button
                >
            </div>
        </div>
    </section>
</template>
