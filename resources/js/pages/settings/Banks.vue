<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    CircleCheckBig,
    Landmark,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import BankFormSheet from '@/components/banks/BankFormSheet.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { destroy, edit, store, toggleActive } from '@/routes/banks';
import type { BanksPageProps, BreadcrumbItem, UserBankItem } from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<BanksPageProps>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Banche',
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);

const formOpen = ref(false);
const editingBank = ref<UserBankItem | null>(null);
const deletingBank = ref<UserBankItem | null>(null);
const catalogBankId = ref<string>('');
const feedback = ref<FeedbackState | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const addCatalogForm = useForm({
    mode: 'catalog',
    bank_id: '',
    is_active: true,
});

const flashSuccess = computed(() => flash.value.success ?? undefined);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

watch(
    flashSuccess,
    (message) => {
        if (message) {
            feedback.value = {
                variant: 'default',
                title: 'Operazione completata',
                message,
            };
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.delete ?? errors.name ?? errors.bank_id;

        if (message) {
            feedback.value = {
                variant: 'destructive',
                title: 'Operazione non disponibile',
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
        label: 'Totali',
        value: props.banks.summary.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: 'Attive',
        value: props.banks.summary.active_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'Personalizzate',
        value: props.banks.summary.custom_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: 'Usate da account',
        value: props.banks.summary.used_count,
        tone: 'text-amber-700 dark:text-amber-300',
    },
]);

const catalogAvailable = computed(() => props.catalog.available);
const customBanks = computed(() =>
    props.banks.data.filter((item) => item.is_custom),
);
const catalogBanks = computed(() =>
    props.banks.data.filter((item) => !item.is_custom),
);

const deleteReasons = computed(() => {
    if (!deletingBank.value) {
        return [];
    }

    const reasons: string[] = [];

    if (deletingBank.value.accounts_count > 0) {
        reasons.push(
            deletingBank.value.accounts_count === 1
                ? 'È già collegata a 1 account.'
                : `È già collegata a ${deletingBank.value.accounts_count} account.`,
        );
    }

    return reasons;
});

function openCreateBank(): void {
    editingBank.value = null;
    formOpen.value = true;
}

function openEditBank(bank: UserBankItem): void {
    editingBank.value = bank;
    formOpen.value = true;
}

function handleSaved(message: string): void {
    feedback.value = {
        variant: 'default',
        title: 'Salvataggio completato',
        message,
    };
}

function addCatalogBank(): void {
    if (catalogBankId.value === '') {
        return;
    }

    addCatalogForm
        .transform(() => ({
            mode: 'catalog',
            bank_id: Number(catalogBankId.value),
            is_active: true,
        }))
        .post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                catalogBankId.value = '';
                addCatalogForm.reset();
                feedback.value = {
                    variant: 'default',
                    title: 'Catalogo aggiornato',
                    message:
                        'La banca è stata aggiunta alle tue banche disponibili.',
                };
            },
        });
}

function toggleBank(bank: UserBankItem): void {
    router.patch(
        toggleActive.url(bank.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = {
                    variant: 'default',
                    title: 'Stato aggiornato',
                    message: bank.is_active
                        ? 'La banca è stata disattivata.'
                        : 'La banca è stata attivata.',
                };
            },
        },
    );
}

function requestDelete(bank: UserBankItem): void {
    deletingBank.value = bank;
}

function closeDeleteDialog(): void {
    deletingBank.value = null;
}

function confirmDelete(): void {
    if (!deletingBank.value) {
        return;
    }

    router.delete(destroy.url(deletingBank.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = {
                variant: 'default',
                title: 'Banca rimossa',
                message:
                    'La banca è stata rimossa dalle tue banche disponibili.',
            };
            closeDeleteDialog();
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Banche" />

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,130,246,0.14),_transparent_28%),linear-gradient(135deg,rgba(15,23,42,0.03),rgba(255,255,255,0))] px-6 py-6 sm:px-8 sm:py-8 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl space-y-4">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <Building2 class="h-3.5 w-3.5" />
                                Banche disponibili
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    Banche
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300"
                                >
                                    Gestisci l’elenco delle banche selezionabili
                                    per i tuoi account: catalogo condiviso
                                    quando basta, banche personalizzate quando
                                    serve.
                                </p>
                            </div>
                        </div>

                        <Button
                            class="h-11 rounded-2xl px-5"
                            @click="openCreateBank"
                        >
                            <Plus class="h-4 w-4" />
                            Nuova banca personalizzata
                        </Button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold tracking-tight"
                                :class="card.tone"
                            >
                                {{ card.value }}
                            </p>
                        </article>
                    </div>

                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-[1.5rem] border"
                    >
                        <CheckCircle2
                            v-if="feedback.variant === 'default'"
                            class="h-4 w-4"
                        />
                        <ShieldAlert v-else class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>
                            <p>{{ feedback.message }}</p>
                        </AlertDescription>
                    </Alert>

                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-3 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-3 opacity-0"
                    >
                        <div
                            v-if="feedback"
                            class="pointer-events-none fixed right-4 bottom-4 z-50 max-w-sm sm:right-6 sm:bottom-6"
                        >
                            <div
                                class="pointer-events-auto overflow-hidden rounded-[1.5rem] border shadow-2xl"
                                :class="
                                    feedback.variant === 'default'
                                        ? 'border-emerald-200 bg-emerald-500 text-white'
                                        : 'border-rose-200 bg-rose-600 text-white'
                                "
                            >
                                <div class="flex items-start gap-3 px-4 py-4">
                                    <div
                                        class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15"
                                    >
                                        <CircleCheckBig
                                            v-if="
                                                feedback.variant === 'default'
                                            "
                                            class="h-5 w-5"
                                        />
                                        <ShieldAlert v-else class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            {{ feedback.title }}
                                        </p>
                                        <p class="mt-1 text-sm text-white/90">
                                            {{ feedback.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <section
                        class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/80"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    Aggiungi dal catalogo globale
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Rendi disponibili solo le banche che vuoi
                                    usare davvero.
                                </p>
                            </div>

                            <div
                                class="grid gap-3 md:grid-cols-[minmax(0,280px)_auto]"
                            >
                                <div>
                                    <Label
                                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        Banca dal catalogo
                                    </Label>
                                    <Select
                                        :model-value="catalogBankId"
                                        @update:model-value="
                                            catalogBankId = String($event)
                                        "
                                    >
                                        <SelectTrigger
                                            class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                        >
                                            <SelectValue
                                                placeholder="Seleziona una banca"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-if="
                                                    catalogAvailable.length ===
                                                    0
                                                "
                                                disabled
                                                value="__empty__"
                                            >
                                                Nessuna banca aggiuntiva
                                                disponibile
                                            </SelectItem>
                                            <SelectItem
                                                v-for="option in catalogAvailable"
                                                :key="option.id"
                                                :value="String(option.id)"
                                            >
                                                {{ option.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button
                                    class="h-11 rounded-2xl px-5"
                                    :disabled="catalogAvailable.length === 0"
                                    @click="addCatalogBank"
                                >
                                    <Landmark class="h-4 w-4" />
                                    Aggiungi dal catalogo
                                </Button>
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        Banche dal catalogo
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Voci globali rese disponibili al tuo
                                        profilo.
                                    </p>
                                </div>
                                <Badge variant="secondary" class="rounded-full">
                                    {{ catalogBanks.length }}
                                </Badge>
                            </div>

                            <article
                                v-if="catalogBanks.length === 0"
                                class="rounded-[1.5rem] border border-dashed border-slate-300/80 bg-slate-50/80 p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                            >
                                Non hai ancora aggiunto banche dal catalogo
                                condiviso. Usa il selettore qui sopra per
                                rendere disponibili solo quelle che ti servono.
                            </article>

                            <article
                                v-for="bank in catalogBanks"
                                :key="bank.id"
                                class="rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div
                                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="space-y-3">
                                        <div>
                                            <p
                                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ bank.name }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    bank.catalog_bank
                                                        ?.country_code ??
                                                    'Codice paese non disponibile'
                                                }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{ bank.source_label }}
                                            </Badge>
                                            <Badge
                                                class="rounded-full"
                                                :class="
                                                    bank.is_active
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                "
                                            >
                                                {{
                                                    bank.is_active
                                                        ? 'Attiva'
                                                        : 'Disattiva'
                                                }}
                                            </Badge>
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{
                                                    bank.accounts_count
                                                }}
                                                account
                                            </Badge>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end"
                                    >
                                        <Button
                                            variant="secondary"
                                            class="h-10 rounded-2xl"
                                            @click="toggleBank(bank)"
                                        >
                                            {{
                                                bank.is_active
                                                    ? 'Disattiva'
                                                    : 'Attiva'
                                            }}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            class="h-10 rounded-2xl"
                                            @click="requestDelete(bank)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Rimuovi
                                        </Button>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <section class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        Banche personalizzate
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Voci create solo per il tuo profilo
                                        utente.
                                    </p>
                                </div>
                                <Badge variant="secondary" class="rounded-full">
                                    {{ customBanks.length }}
                                </Badge>
                            </div>

                            <article
                                v-if="customBanks.length === 0"
                                class="rounded-[1.5rem] border border-dashed border-slate-300/80 bg-slate-50/80 p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                            >
                                Nessuna banca personalizzata. Creane una solo se
                                non trovi la banca nel catalogo oppure vuoi una
                                voce tutta tua.
                            </article>

                            <article
                                v-for="bank in customBanks"
                                :key="bank.id"
                                class="rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div
                                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="space-y-3">
                                        <div>
                                            <p
                                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ bank.name }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Slug: {{ bank.slug }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{ bank.source_label }}
                                            </Badge>
                                            <Badge
                                                class="rounded-full"
                                                :class="
                                                    bank.is_active
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                "
                                            >
                                                {{
                                                    bank.is_active
                                                        ? 'Attiva'
                                                        : 'Disattiva'
                                                }}
                                            </Badge>
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{
                                                    bank.accounts_count
                                                }}
                                                account
                                            </Badge>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end"
                                    >
                                        <Button
                                            variant="secondary"
                                            class="h-10 rounded-2xl"
                                            @click="openEditBank(bank)"
                                        >
                                            Modifica
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            class="h-10 rounded-2xl"
                                            @click="toggleBank(bank)"
                                        >
                                            {{
                                                bank.is_active
                                                    ? 'Disattiva'
                                                    : 'Attiva'
                                            }}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            class="col-span-2 h-10 rounded-2xl"
                                            @click="requestDelete(bank)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Elimina
                                        </Button>
                                    </div>
                                </div>
                            </article>

                            <div
                                v-if="customBanks.length === 0"
                                class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50/80 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/60"
                            >
                                <p
                                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                >
                                    Nessuna banca personalizzata. Creane una
                                    solo se non trovi ciò che ti serve nel
                                    catalogo.
                                </p>
                            </div>
                        </section>
                    </div>
                </div>
            </section>

            <BankFormSheet
                v-model:open="formOpen"
                :bank="editingBank"
                @saved="handleSaved"
            />

            <Dialog
                :open="deletingBank !== null"
                @update:open="!$event ? closeDeleteDialog() : null"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="flex items-center gap-2">
                            <Trash2 class="h-4 w-4" />
                            Rimuovi banca disponibile
                        </DialogTitle>
                        <DialogDescription class="leading-6">
                            <template v-if="deletingBank?.is_deletable">
                                Stai per rimuovere
                                <strong>{{ deletingBank?.name }}</strong>
                                dalla tua rubrica banche.
                            </template>
                            <template v-else>
                                <strong>{{ deletingBank?.name }}</strong>
                                non può essere rimossa in questo momento.
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteReasons.length > 0"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-medium">Motivi del blocco</p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="reason in deleteReasons" :key="reason">
                                {{ reason }}
                            </li>
                        </ul>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="closeDeleteDialog"
                        >
                            Chiudi
                        </Button>
                        <Button
                            v-if="deletingBank?.is_deletable"
                            type="button"
                            variant="destructive"
                            class="rounded-xl"
                            @click="confirmDelete"
                        >
                            Rimuovi banca
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
