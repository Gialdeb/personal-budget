import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pageSource = readFileSync(
    new URL(
        '../../resources/js/pages/credits-debts/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const sidebarSource = readFileSync(
    new URL('../../resources/js/components/AppSidebar.vue', import.meta.url),
    'utf8',
);
const mobileNavSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileBottomNav.vue',
        import.meta.url,
    ),
    'utf8',
);
const navMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/nav.ts', import.meta.url),
    'utf8',
);
const messagesSource = readFileSync(
    new URL(
        '../../resources/js/i18n/messages/credits-debts.ts',
        import.meta.url,
    ),
    'utf8',
);
const contextualHelpSeederSource = readFileSync(
    new URL('../../database/seeders/ContextualHelpSeeder.php', import.meta.url),
    'utf8',
);
const mobileAmountInputSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileAmountInput.vue',
        import.meta.url,
    ),
    'utf8',
);

test('credits debts navigation is gated by the shared feature flag', () => {
    assert.match(sidebarSource, /credits_debts_enabled/);
    assert.match(sidebarSource, /creditsDebtsEnabled\.value/);
    assert.match(sidebarSource, /creditsDebtsIndex\(\)/);
    assert.match(mobileNavSource, /credits_debts_enabled/);
    assert.match(mobileNavSource, /creditsDebtsEnabled/);
    assert.match(navMessagesSource, /creditsDebts:\s*'Crediti e debiti'/);
    assert.match(navMessagesSource, /creditsDebts:\s*'Credits and debts'/);
});

test('credits debts page exposes the requested desktop and mobile structure', () => {
    assert.match(pageSource, /props\.summary\.credits_remaining_total/);
    assert.match(pageSource, /props\.summary\.debts_remaining_total/);
    assert.match(pageSource, /props\.summary\.overdue_count/);
    assert.match(pageSource, /props\.summary\.net_expected_total/);
    assert.match(
        pageSource,
        /xl:grid-cols-\[minmax\(0,1fr\)_minmax\(0,1fr\)_minmax\(360px,0\.95fr\)]/,
    );
    assert.match(pageSource, /activeMobileType/);
    assert.match(pageSource, /ListColumn/);
    assert.match(pageSource, /isMobileDetailOpen/);
    assert.match(pageSource, /ArrowLeft/);
    assert.match(pageSource, /@click="isMobileDetailOpen = false"/);
    assert.match(pageSource, /SheetDescription class="sr-only"/);
    assert.match(pageSource, /detailDescription/);
    assert.doesNotMatch(pageSource, /CircleHelp/);
    assert.doesNotMatch(pageSource, /sectionHelp/);
    assert.doesNotMatch(pageSource, /TooltipProvider/);
    assert.match(pageSource, /props\.options\.years/);
    assert.match(pageSource, /props\.options\.months/);
    assert.match(pageSource, /pageHeading/);
    assert.match(pageSource, /displayedPeriodLabel/);
    assert.match(pageSource, /displayedPeriod/);
    assert.match(pageSource, /monthOptionLabel/);
    assert.match(pageSource, /searchPlaceholderExtended/);
    assert.match(pageSource, /periodTotal/);
    assert.match(pageSource, /formatDate\(selectedItem\.due_date\)/);
    assert.match(pageSource, /formatDate\(payment\.paid_at\)/);
    assert.doesNotMatch(messagesSource, /Area report/);
});

test('credits debts forms reuse mobile amount input, privacy masking, Wayfinder, and inline references', () => {
    assert.match(pageSource, /MobileAmountInput/);
    assert.match(pageSource, /MobileSearchableSelect/);
    assert.match(pageSource, /SensitiveValue/);
    assert.match(pageSource, /storeCreditDebt\(\)\.url/);
    assert.match(pageSource, /storePayment\(selectedItem\.value\.uuid\)\.url/);
    assert.match(pageSource, /storeTrackedItem\(\)\.url/);
    assert.match(pageSource, /referencePlaceholder/);
    assert.match(pageSource, /settleRemaining/);
    assert.match(
        pageSource,
        /itemForm\.type === 'credit'[\s\S]*border-emerald-300/,
    );
    assert.match(
        pageSource,
        /itemForm\.type === 'debit'[\s\S]*border-rose-300/,
    );
    assert.match(pageSource, /hierarchical/);
    assert.match(pageSource, /creatable/);
    assert.match(pageSource, /referenceMatchesContext/);
    assert.match(pageSource, /validateItemForm/);
    assert.match(pageSource, /validatePaymentForm/);
    assert.match(pageSource, /let isValid = true/);
    assert.match(pageSource, /novalidate/);
    assert.match(pageSource, /normalizeMoneyValue/);
    assert.match(pageSource, /itemTotalAmountLocked/);
    assert.match(pageSource, /remainingReceiveBy/);
    assert.match(pageSource, /paymentExceedsRemaining/);
    assert.match(mobileAmountInputSource, /InputError/);
    assert.match(mobileAmountInputSource, /:message="error"/);
    assert.match(pageSource, /resetItemForm/);
    assert.match(
        pageSource,
        /router\.reload\(\{\s*only: \['items', 'summary']/,
    );
});

test('credits debts filters expose clear period and non ambiguous all labels', () => {
    assert.match(messagesSource, /Periodo visualizzato/);
    assert.match(messagesSource, /Tutto l’anno/);
    assert.match(messagesSource, /Tutti i periodi/);
    assert.match(messagesSource, /Tutti i tipi/);
    assert.match(messagesSource, /Tutti gli stati/);
    assert.match(messagesSource, /Cerca per controparte, descrizione, importo, nota/);
    assert.match(messagesSource, /Nessun credito o debito trovato per questi filtri/);
    assert.match(messagesSource, /Displayed period/);
    assert.match(messagesSource, /Full year/);
    assert.match(messagesSource, /All periods/);
    assert.match(messagesSource, /All types/);
    assert.match(messagesSource, /All statuses/);
    assert.match(messagesSource, /Search by counterparty, description, amount, note/);
    assert.match(messagesSource, /No credits or debts found for these filters/);
    assert.doesNotMatch(pageSource, /\{ value: 'all', label: t\('creditsDebts\.all'\) }/);
});

test('credits debts payment deletion uses the custom destructive dialog', () => {
    assert.doesNotMatch(pageSource, /window\.confirm/);
    assert.doesNotMatch(pageSource, /window\.alert/);
    assert.doesNotMatch(pageSource, /window\.prompt/);
    assert.match(pageSource, /DialogContent class="sm:max-w-lg"/);
    assert.match(pageSource, /openDeletePaymentDialog\(payment\)/);
    assert.match(pageSource, /paymentDeleteForm\.delete/);
    assert.match(pageSource, /deletePaymentDialog\.title/);
    assert.match(pageSource, /deletePaymentDialog\.description/);
    assert.match(pageSource, /deletePaymentDialog\.linkedTransactionNotice/);
    assert.match(pageSource, /deletePaymentDialog\.cancel/);
    assert.match(pageSource, /deletePaymentDialog\.confirm/);
    assert.match(pageSource, /paymentDeleteForm\.errors\.payment/);
    assert.match(messagesSource, /Eliminare questo acconto\?/);
    assert.match(messagesSource, /Elimina definitivamente/);
    assert.match(messagesSource, /Delete this payment\?/);
    assert.match(messagesSource, /Permanently delete/);
});

test('credits debts translations include Italian and English labels', () => {
    for (const key of [
        'Da ricevere',
        'Da pagare',
        'Registra acconto',
        'Salda residuo',
        'Mese',
        'Nessun credito o debito trovato per questi filtri',
        'Expected net',
        'Month',
        'No credits or debts found for these filters',
    ]) {
        assert.match(messagesSource, new RegExp(key));
    }

    for (const key of [
        'Crediti e debiti',
        'Gestisci le somme che devi ricevere o pagare',
        'Le notifiche ti aiutano a ricordare',
        'Credits and debts',
        'Manage amounts you need to receive or pay',
        'Notifications help you remember',
    ]) {
        assert.match(contextualHelpSeederSource, new RegExp(key));
    }
});
