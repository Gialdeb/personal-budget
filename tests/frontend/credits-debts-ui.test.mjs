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
        /xl:grid-cols-\[minmax\(0,1fr\)_minmax\(0,1fr\)_minmax\(360px,0\.95fr\)\]/,
    );
    assert.match(pageSource, /activeMobileType/);
    assert.match(pageSource, /ListColumn/);
    assert.match(pageSource, /isMobileDetailOpen/);
    assert.match(pageSource, /ArrowLeft/);
    assert.match(pageSource, /@click="isMobileDetailOpen = false"/);
    assert.match(pageSource, /SheetDescription class="sr-only"/);
    assert.match(pageSource, /detailDescription/);
    assert.match(pageSource, /CircleHelp/);
    assert.match(pageSource, /sectionHelp/);
    assert.match(pageSource, /props\.options\.years/);
    assert.match(pageSource, /props\.options\.months/);
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
        /router\.reload\(\{\s*only: \['items', 'summary'\]/,
    );
});

test('credits debts translations include Italian and English labels', () => {
    for (const key of [
        'Da ricevere',
        'Da pagare',
        'Registra acconto',
        'Salda residuo',
        'Mese',
        'Spiegazione della sezione',
        'senza alterare subito il saldo dei conti',
        'No credits found',
        'Expected net',
        'Month',
        'Section explanation',
        'without immediately changing account balances',
    ]) {
        assert.match(messagesSource, new RegExp(key));
    }
});
