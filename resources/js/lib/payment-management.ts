export const paymentReasons = {
    not_charged: 'Addebito non ancora avvenuto',
    bank_problem: 'Problema bancario',
    insufficient_funds: 'Fondi insufficienti',
    postponed: 'Pagamento posticipato',
    cancelled: 'Pagamento annullato',
    already_paid: 'Pagamento già effettuato',
    different_amount: 'Importo diverso',
    other: 'Altro problema',
} as const;
export type PaymentReason = keyof typeof paymentReasons;
export function paymentAction(
    reason: string,
    automatic: boolean,
    existingAutomaticPayment = false,
): 'pending' | 'cancel' | 'confirm' | 'correct' | 'record' {
    if (automatic && reason === 'already_paid' && existingAutomaticPayment) {
        return 'record';
    }

    if (reason === 'cancelled') {
        return 'cancel';
    }

    if (
        reason === 'already_paid' ||
        reason === 'different_amount' ||
        reason === 'other_account'
    ) {
        return automatic ? 'correct' : 'record';
    }

    return 'pending';
}
export function reminderDate(days: number, now = new Date()): string {
    const date = new Date(now);
    date.setDate(date.getDate() + days);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
export const bankPaymentReasons = {
    declined: 'Addebito rifiutato',
    invalid_method: 'Metodo non valido',
    processing: 'Pagamento in elaborazione',
    other_account: 'Pagamento da altro conto',
    duplicate_charge: 'Doppio addebito reale',
    bank_reversal: 'Storno bancario successivo',
    bank_problem: 'Problema bancario',
} as const;
export function paymentStateLabel(state: string) {
    if (state === 'partial') {
        return 'Parzialmente pagata';
    }

    if (state === 'to_verify') {
        return 'Pagamento da confermare';
    }

    if (state === 'review') {
        return 'Da verificare';
    }

    if (state === 'cancelled') {
        return 'Singola scadenza non dovuta';
    }

    if (state === 'paid' || state === 'corrected') {
        return 'Pagamento registrato';
    }

    if (state === 'processing') {
        return 'Pagamento in elaborazione';
    }

    if (state === 'postponed') {
        return 'Pagamento posticipato';
    }

    return 'Pagamento ancora dovuto';
}
export function paymentReasonLabel(reason: string) {
    if (reason === 'forecast_changed') {
        return 'Previsione aggiornata';
    }

    if (reason === 'payment_recorded') {
        return 'Pagamento registrato';
    }

    if (reason in paymentReasons) {
        return paymentReasons[reason as PaymentReason];
    }

    if (reason in bankPaymentReasons) {
        return bankPaymentReasons[reason as keyof typeof bankPaymentReasons];
    }

    return 'Altro problema';
}
export function paymentActionLabel(action: string) {
    if (action === 'forecast') {
        return 'Previsione aggiornata';
    }

    if (action === 'cancel') {
        return 'Singola scadenza non dovuta';
    }

    if (action === 'correct') {
        return 'Pagamento corretto';
    }

    if (action === 'resolved') {
        return 'Scadenza risolta';
    }

    if (action === 'rectify') {
        return 'Rettificato';
    }

    if (['record', 'confirm'].includes(action)) {
        return 'Pagamento registrato';
    }

    return 'Pagamento ancora dovuto';
}
export function paymentRequiresReview(reason: string): boolean {
    return ['duplicate_charge', 'bank_reversal'].includes(reason);
}
