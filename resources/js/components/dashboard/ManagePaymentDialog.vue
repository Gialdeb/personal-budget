<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    manage,
    paymentCandidates,
} from '@/actions/App/Http/Controllers/RecurringEntryOccurrenceController';
import AppDatePicker from '@/components/AppDatePicker.vue';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@/composables/useNotifiedForm';
import { useUiCopy } from '@/i18n/ui-copy';
import { formatCurrency } from '@/lib/currency';
import {
    bankPaymentReasons,
    paymentStateLabel,
    paymentReasonLabel,
    paymentRequiresReview,
    paymentAction,
    paymentReasons,
    reminderDate,
} from '@/lib/payment-management';
import type { PaymentReason } from '@/lib/payment-management';
import type { DashboardPendingActionItem } from '@/types/dashboard';
const props = defineProps<{
    item: DashboardPendingActionItem | null;
    currency: string;
}>();
const open = defineModel<boolean>('open', { required: true });
const ui = useUiCopy();
const step = ref(0);
const reason = ref<PaymentReason>('not_charged');
const bankReason = ref<keyof typeof bankPaymentReasons>('bank_problem');
const reminder = ref('2');
const paymentSource = ref('existing');
const existingAutomaticPayment = ref(false);
type Candidate = {
    uuid: string;
    date: string;
    amount: number;
    description: string;
    account: string;
};
const candidates = ref<Candidate[]>([]);
const candidatesLoading = ref(false);
const candidatesError = ref(false);
const candidatesPage = ref(1);
const candidatesHasMore = ref(false);
async function loadCandidates(append = false): Promise<void> {
    if (!payment.value) {
        return;
    }

    candidatesLoading.value = true;
    candidatesError.value = false;
    const occurrence = payment.value.occurrence_uuid;

    try {
        const response = await fetch(
            paymentCandidates.url([payment.value.entry_uuid, occurrence], {
                query: { page: append ? candidatesPage.value + 1 : 1 },
            }),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('candidates');
        }

        const data = await response.json();

        if (payment.value?.occurrence_uuid !== occurrence || !open.value) {
            return;
        }

        candidates.value = append
            ? [...candidates.value, ...data.transactions]
            : data.transactions;
        candidatesPage.value = append ? candidatesPage.value + 1 : 1;
        candidatesHasMore.value = data.has_more;
    } catch {
        candidatesError.value = true;
    } finally {
        candidatesLoading.value = false;
    }
}

const form = useForm({
    transaction_uuid: null as string | null,
    confirm_new_payment: false,
    request_id: '',
    reason: '',
    action: 'pending',
    estimated_date: '',
    actual_date: '',
    actual_amount: '',
    account_uuid: null as string | null,
    notes: '',
    reminder_date: '',
    confirm_rectification: false,
});
const payment = computed(() => props.item?.payment_management);
const resolvedReason = computed(() =>
    reason.value === 'bank_problem' ? bankReason.value : reason.value,
);
const reviewOnly = computed(() => paymentRequiresReview(resolvedReason.value));
const action = computed(() =>
    paymentAction(
        resolvedReason.value,
        payment.value?.has_automatic_transaction ?? false,
        existingAutomaticPayment.value,
    ),
);
const actualPayment = computed(() =>
    ['correct', 'record', 'confirm'].includes(action.value),
);
const needsRectification = computed(
    () =>
        !!payment.value?.has_automatic_transaction &&
        (['pending', 'cancel'].includes(action.value) ||
            (action.value === 'record' && existingAutomaticPayment.value)) &&
        !['processing', 'duplicate_charge', 'bank_reversal'].includes(
            resolvedReason.value,
        ),
);
const canContinue = computed(
    () =>
        !actualPayment.value ||
        (!!form.actual_date && Number(form.actual_amount) > 0),
);
watch(open, (value) => {
    if (!value) {
        return;
    }

    step.value = 0;
    reason.value = 'not_charged';
    bankReason.value = 'bank_problem';
    reminder.value = '2';
    paymentSource.value = 'existing';
    existingAutomaticPayment.value = false;
    candidates.value = [];
    void loadCandidates();
    form.reset();
    form.clearErrors();
    form.request_id = crypto.randomUUID();
    form.actual_amount = String(
        payment.value?.actual_amount ??
            payment.value?.expected_amount ??
            props.item?.amount_raw ??
            '',
    );
    form.actual_date = payment.value?.actual_date ?? reminderDate(0);
    form.estimated_date = payment.value?.estimated_date ?? '';
});
watch(existingAutomaticPayment, () => {
    paymentSource.value = 'existing';
    form.transaction_uuid = null;
    form.confirm_new_payment = false;
    form.confirm_rectification = false;
    form.actual_amount = String(
        payment.value?.actual_amount ?? payment.value?.expected_amount ?? '',
    );
    form.actual_date = payment.value?.actual_date ?? reminderDate(0);
});
watch(paymentSource, () => {
    form.transaction_uuid = null;
    form.confirm_new_payment = false;
});
watch(
    () => form.transaction_uuid,
    (uuid) => {
        const candidate = candidates.value.find((item) => item.uuid === uuid);

        if (candidate) {
            form.actual_date = candidate.date;
            form.actual_amount = String(candidate.amount);
        }
    },
);
watch(resolvedReason, () => {
    if (resolvedReason.value !== 'already_paid') {
        existingAutomaticPayment.value = false;
    }

    form.transaction_uuid = null;
    form.account_uuid = null;
    form.confirm_rectification = false;
});
function submit(): void {
    if (
        !payment.value ||
        !canContinue.value ||
        (needsRectification.value &&
            (!payment.value.can_rectify || !form.confirm_rectification))
    ) {
        return;
    }

    form.reason = resolvedReason.value;

    if (
        action.value === 'record' &&
        (paymentSource.value === 'existing'
            ? !form.transaction_uuid
            : !form.confirm_new_payment)
    ) {
        return;
    }

    form.action = action.value;
    form.transform((data) => ({
        ...data,
        transaction_uuid:
            action.value === 'record' && paymentSource.value === 'existing'
                ? data.transaction_uuid
                : null,
        confirm_new_payment:
            action.value === 'record' &&
            paymentSource.value === 'new' &&
            data.confirm_new_payment,
        account_uuid:
            resolvedReason.value === 'other_account' ? data.account_uuid : null,
        confirm_rectification:
            needsRectification.value && data.confirm_rectification,
        estimated_date:
            !actualPayment.value && !reviewOnly.value && data.estimated_date
                ? data.estimated_date
                : null,
        actual_date: actualPayment.value ? data.actual_date : null,
        actual_amount: actualPayment.value ? data.actual_amount : null,
        reminder_date:
            action.value === 'pending' &&
            !reviewOnly.value &&
            reminder.value !== 'none'
                ? reminder.value === 'custom'
                    ? data.reminder_date
                    : reminderDate(Number(reminder.value))
                : null,
    })).post(
        manage.url([payment.value.entry_uuid, payment.value.occurrence_uuid]),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}
</script>
<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ ui('Gestisci pagamento') }}</DialogTitle>
                <DialogDescription>{{
                    ui(
                        'Gestisci soltanto questa scadenza. Le altre occorrenze restano invariate.',
                    )
                }}</DialogDescription>
            </DialogHeader>
            <div v-if="payment" class="grid gap-5">
                <div class="rounded-xl border bg-muted/30 p-4">
                    <p class="font-semibold">{{ payment.reference }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground">
                                {{ ui('Importo previsto') }}
                            </dt>
                            <dd>
                                {{
                                    formatCurrency(
                                        payment.expected_amount,
                                        item?.currency ?? currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                {{ ui('Data prevista') }}
                            </dt>
                            <dd>{{ payment.expected_date }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                {{ ui('Stato del pagamento') }}
                            </dt>
                            <dd>{{ ui(paymentStateLabel(payment.state)) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                {{ ui('Registrazione') }}
                            </dt>
                            <dd>
                                {{
                                    payment.has_automatic_transaction
                                        ? ui('Automatica')
                                        : ui('Manuale')
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <p class="text-xs font-medium text-muted-foreground">
                    {{ ui('Passaggio') }} {{ step + 1 }} / 3 ·
                    {{
                        step === 0
                            ? ui('Cosa è successo?')
                            : step === 1
                              ? ui('Dettagli')
                              : ui('Riepilogo')
                    }}
                </p>
                <fieldset v-if="step === 0" class="grid gap-2 sm:grid-cols-2">
                    <legend class="sr-only">
                        {{ ui('Cosa è successo?') }}
                    </legend>
                    <label
                        v-for="(label, key) in paymentReasons"
                        :key="key"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm transition-colors hover:bg-muted/50"
                        :class="
                            reason === key ? 'border-primary bg-primary/5' : ''
                        "
                    >
                        <input
                            v-model="reason"
                            type="radio"
                            name="payment-reason"
                            :value="key"
                            class="accent-primary"
                        />{{ ui(label) }}
                    </label>
                </fieldset>
                <div v-else-if="step === 1" class="grid gap-4">
                    <label
                        v-if="reason === 'bank_problem'"
                        class="grid gap-2 text-sm font-medium"
                        >{{ ui('Tipo di problema') }}
                        <select
                            v-model="bankReason"
                            class="h-10 rounded-md border bg-background px-3"
                        >
                            <option value="bank_problem">
                                {{ ui('Problema bancario') }}
                            </option>
                            <option value="declined">
                                {{ ui('Addebito rifiutato') }}
                            </option>
                            <option value="invalid_method">
                                {{ ui('Metodo non valido') }}
                            </option>
                            <option value="processing">
                                {{ ui('Pagamento in elaborazione') }}
                            </option>
                            <option value="other_account">
                                {{ ui('Pagamento da altro conto') }}
                            </option>
                            <option value="duplicate_charge">
                                {{ ui('Doppio addebito reale') }}
                            </option>
                            <option value="bank_reversal">
                                {{ ui('Storno bancario successivo') }}
                            </option>
                        </select>
                    </label>
                    <label
                        v-if="
                            reason === 'already_paid' &&
                            payment.has_automatic_transaction
                        "
                        class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                    >
                        <input
                            v-model="existingAutomaticPayment"
                            type="checkbox"
                            class="mt-1 accent-primary"
                        />{{
                            ui(
                                'Il pagamento è già presente in un altro movimento',
                            )
                        }}
                    </label>
                    <template v-if="actualPayment">
                        <template v-if="action === 'record'">
                            <label
                                v-if="!existingAutomaticPayment"
                                class="grid gap-2 text-sm font-medium"
                                >{{ ui('Come registrare il pagamento?') }}
                                <select
                                    v-model="paymentSource"
                                    class="h-10 rounded-md border bg-background px-3"
                                >
                                    <option value="existing">
                                        {{
                                            ui('Collega un movimento esistente')
                                        }}
                                    </option>
                                    <option value="new">
                                        {{
                                            ui(
                                                'Registra un pagamento non ancora presente',
                                            )
                                        }}
                                    </option>
                                </select>
                            </label>
                            <label
                                v-if="paymentSource === 'existing'"
                                class="grid gap-2 text-sm font-medium"
                                >{{ ui('Movimento esistente') }}
                                <select
                                    v-model="form.transaction_uuid"
                                    class="h-10 rounded-md border bg-background px-3"
                                    :disabled="candidatesLoading"
                                >
                                    <option :value="null" disabled>
                                        {{ ui('Seleziona un movimento') }}
                                    </option>
                                    <option
                                        v-for="candidate in candidates"
                                        :key="candidate.uuid"
                                        :value="candidate.uuid"
                                    >
                                        {{ candidate.date }} ·
                                        {{
                                            formatCurrency(
                                                candidate.amount,
                                                item?.currency ?? currency,
                                            )
                                        }}
                                        · {{ candidate.description }} ·
                                        {{ candidate.account }}
                                    </option>
                                </select>
                            </label>
                            <p
                                v-if="candidatesLoading"
                                role="status"
                                class="text-xs text-muted-foreground"
                            >
                                {{ ui('Caricamento movimenti…') }}
                            </p>
                            <p
                                v-if="candidatesError"
                                role="alert"
                                class="text-sm text-destructive"
                            >
                                {{
                                    ui(
                                        'Impossibile caricare i movimenti. Riprova.',
                                    )
                                }}
                            </p>
                            <Button
                                v-if="candidatesHasMore || candidatesError"
                                size="sm"
                                variant="outline"
                                :disabled="candidatesLoading"
                                @click="loadCandidates(candidatesHasMore)"
                                >{{ ui('Carica movimenti') }}</Button
                            >
                        </template>
                        <label class="grid gap-2 text-sm font-medium"
                            >{{ ui('Data effettiva')
                            }}<AppDatePicker
                                v-model="form.actual_date"
                                required
                        /></label>
                        <label class="grid gap-2 text-sm font-medium"
                            >{{ ui('Importo effettivo')
                            }}<MobileAmountInput
                                v-model="form.actual_amount"
                                :currency-code="item?.currency ?? currency"
                                min="0.01"
                                :disabled="
                                    action === 'record' &&
                                    paymentSource === 'existing'
                                "
                                required
                        /></label>
                        <p class="text-sm text-muted-foreground">
                            {{
                                ui(
                                    'L’importo effettivo non genera automaticamente acconti o residui nei piani variabili.',
                                )
                            }}
                        </p>
                        <label
                            v-if="resolvedReason === 'other_account'"
                            class="grid gap-2 text-sm font-medium"
                            >{{ ui('Conto del pagamento') }}
                            <select
                                v-model="form.account_uuid"
                                required
                                class="h-10 rounded-md border bg-background px-3"
                            >
                                <option :value="null" disabled>
                                    {{ ui('Seleziona conto') }}
                                </option>
                                <option
                                    v-for="account in payment.accounts ?? []"
                                    :key="account.uuid"
                                    :value="account.uuid"
                                >
                                    {{ account.name }}
                                </option>
                            </select>
                        </label>
                    </template>
                    <template v-else-if="action === 'pending'">
                        <template v-if="!reviewOnly">
                            <label class="grid gap-2 text-sm font-medium"
                                >{{ ui('Nuova data stimata (facoltativa)')
                                }}<AppDatePicker v-model="form.estimated_date"
                            /></label>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    ui(
                                        'Lascia vuoto se non conosci la nuova data. Il pagamento resta dovuto.',
                                    )
                                }}
                            </p>
                            <label class="grid gap-2 text-sm font-medium"
                                >{{ ui('Ricordamelo')
                                }}<select
                                    v-model="reminder"
                                    class="h-10 rounded-md border bg-background px-3"
                                >
                                    <option value="1">
                                        {{ ui('Domani') }}
                                    </option>
                                    <option value="2">
                                        {{ ui('Tra 2 giorni') }}
                                    </option>
                                    <option value="7">
                                        {{ ui('Tra 7 giorni') }}
                                    </option>
                                    <option value="custom">
                                        {{ ui('Data personalizzata') }}
                                    </option>
                                    <option value="none">
                                        {{ ui('Nessun promemoria') }}
                                    </option>
                                </select></label
                            >
                            <AppDatePicker
                                v-if="reminder === 'custom'"
                                v-model="form.reminder_date"
                                :min="reminderDate(1)"
                                required
                            />
                        </template>
                        <p
                            v-if="reviewOnly"
                            class="rounded-lg border p-3 text-sm"
                        >
                            {{
                                ui(
                                    'La segnalazione conserva i movimenti esistenti. Verifica gli addebiti e gli storni nel dettaglio transazioni.',
                                )
                            }}
                        </p>
                    </template>
                    <p v-else class="text-sm">
                        {{
                            ui(
                                'Annullerai soltanto questa scadenza, che non sarà più dovuta. Il piano resta attivo.',
                            )
                        }}
                    </p>
                    <label class="grid gap-2 text-sm font-medium"
                        >{{ ui('Note')
                        }}<textarea
                            v-model="form.notes"
                            rows="3"
                            maxlength="2000"
                            class="rounded-md border bg-background p-3 text-sm"
                        />
                    </label>
                </div>
                <div v-else class="grid gap-4">
                    <dl class="grid gap-3 rounded-xl border p-4 text-sm">
                        <div>
                            <dt class="text-muted-foreground">
                                {{ ui('Cosa è successo?') }}
                            </dt>
                            <dd>
                                {{
                                    ui(
                                        reason === 'bank_problem'
                                            ? bankPaymentReasons[bankReason]
                                            : paymentReasons[reason],
                                    )
                                }}
                            </dd>
                        </div>
                        <div v-if="actualPayment">
                            <dt class="text-muted-foreground">
                                {{ ui('Pagamento effettivo') }}
                            </dt>
                            <dd>
                                {{ form.actual_date }} ·
                                {{
                                    formatCurrency(
                                        Number(form.actual_amount),
                                        item?.currency ?? currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div v-else>
                            <dt class="text-muted-foreground">
                                {{ ui('Esito') }}
                            </dt>
                            <dd>
                                {{
                                    reviewOnly
                                        ? ui('Da verificare')
                                        : action === 'cancel'
                                          ? ui('Singola scadenza non dovuta')
                                          : ui('Pagamento ancora dovuto')
                                }}<span
                                    v-if="!reviewOnly && form.estimated_date"
                                >
                                    · {{ form.estimated_date }}</span
                                >
                            </dd>
                        </div>
                        <div
                            v-if="
                                action === 'pending' &&
                                !reviewOnly &&
                                reminder !== 'none'
                            "
                        >
                            <dt class="text-muted-foreground">
                                {{ ui('Promemoria') }}
                            </dt>
                            <dd>
                                {{
                                    reminder === 'custom'
                                        ? form.reminder_date
                                        : reminderDate(Number(reminder))
                                }}
                            </dd>
                        </div>
                        <div v-if="form.notes">
                            <dt class="text-muted-foreground">
                                {{ ui('Note') }}
                            </dt>
                            <dd class="whitespace-pre-wrap">
                                {{ form.notes }}
                            </dd>
                        </div>
                    </dl>
                    <label
                        v-if="action === 'record' && paymentSource === 'new'"
                        class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                        ><input
                            v-model="form.confirm_new_payment"
                            type="checkbox"
                            class="mt-1 accent-primary"
                        />{{
                            ui(
                                'Confermo che questo pagamento non è già presente nelle transazioni.',
                            )
                        }}</label
                    >
                    <label
                        v-if="needsRectification && payment.can_rectify"
                        class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm"
                        ><input
                            v-model="form.confirm_rectification"
                            type="checkbox"
                            class="mt-1 accent-primary"
                        />{{
                            existingAutomaticPayment
                                ? ui(
                                      'Confermo che il movimento automatico è una previsione non addebitata. Autorizzo la rettifica tracciata e il collegamento del pagamento esistente.',
                                  )
                                : ui(
                                      'Confermo che l’addebito non è avvenuto e autorizzo la rettifica tracciata del movimento automatico.',
                                  )
                        }}</label
                    >
                    <p
                        v-if="needsRectification && !payment.can_rectify"
                        role="alert"
                        class="rounded-xl border border-destructive/40 p-4 text-sm text-destructive"
                    >
                        {{
                            ui(
                                'Questo movimento ha collegamenti contabili protetti. La rettifica non è disponibile.',
                            )
                        }}
                    </p>
                </div>
                <details
                    v-if="payment.history.length"
                    class="rounded-xl border p-4 text-sm"
                >
                    <summary class="cursor-pointer font-medium">
                        {{ ui('Storico del pagamento') }}
                    </summary>
                    <ol class="mt-3 grid gap-3">
                        <li
                            v-for="event in payment.history"
                            :key="event.id"
                            class="border-l-2 border-primary/20 pl-3"
                        >
                            <p class="font-medium">
                                {{ ui(paymentReasonLabel(event.reason)) }}
                            </p>
                            <time class="text-xs text-muted-foreground">{{
                                event.created_at
                            }}</time>
                            <p
                                v-if="event.notes"
                                class="mt-1 whitespace-pre-wrap text-muted-foreground"
                            >
                                {{ event.notes }}
                            </p>
                        </li>
                    </ol>
                </details>
                <InputError
                    v-for="(message, key) in form.errors"
                    :key="key"
                    :message="message"
                />
                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="form.processing"
                        @click="step > 0 ? step-- : (open = false)"
                        >{{ step > 0 ? ui('Indietro') : ui('Chiudi') }}</Button
                    >
                    <Button
                        v-if="step < 2"
                        :disabled="
                            step === 1 &&
                            (!canContinue ||
                                (reminder === 'custom' &&
                                    action === 'pending' &&
                                    !reviewOnly &&
                                    !form.reminder_date) ||
                                (resolvedReason === 'other_account' &&
                                    !form.account_uuid))
                        "
                        @click="step++"
                        >{{ ui('Avanti') }}</Button
                    >
                    <Button
                        v-else
                        :disabled="
                            form.processing ||
                            (action === 'record' &&
                                (paymentSource === 'existing'
                                    ? !form.transaction_uuid
                                    : !form.confirm_new_payment)) ||
                            (needsRectification &&
                                (!payment.can_rectify ||
                                    !form.confirm_rectification))
                        "
                        @click="submit"
                        >{{ ui('Conferma e salva') }}</Button
                    >
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
