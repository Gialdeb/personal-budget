import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);
const rowActionsSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/TransactionRowActions.vue',
        import.meta.url,
    ),
    'utf8',
);
const messagesSource = readFileSync(
    new URL(
        '../../resources/js/i18n/messages/transactions.ts',
        import.meta.url,
    ),
    'utf8',
);
const formSheetSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/TransactionFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const categoryFormSheetSource = readFileSync(
    new URL(
        '../../resources/js/components/categories/CategoryFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const quickCreateSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/TransactionCategoryQuickCreate.vue',
        import.meta.url,
    ),
    'utf8',
);

const classificationSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/TransactionClassificationBadges.vue',
        import.meta.url,
    ),
    'utf8',
);
test('opening balance rows keep the opening badge visible', () => {
    assert.match(
        classificationSource,
        /transactions\.sheet\.grid\.openingBadge/,
    );
});

test('opening balance rows do not render the income or expense type badge', () => {
    assert.match(classificationSource, /category.value === 'opening'/);
});

test('transactions page exposes a toggle to show opening balances', () => {
    assert.match(source, /transactions\.sheet\.filters\.showOpeningBalances/);
    assert.match(source, /showOpeningBalances/);
});

test('transactions page exposes a toggle and dedicated markers for recurring movements', () => {
    assert.match(source, /transactions\.sheet\.filters\.recurring/);
    assert.match(source, /showRecurring/);
    assert.doesNotMatch(source, /showPlannedRecurring|planned_occurrences/);
    assert.match(
        classificationSource,
        /transactions\.sheet\.grid\.recurringBadge/,
    );
    assert.doesNotMatch(classificationSource, /plannedRecurringBadge/);
    assert.match(source, /data-transaction-row/);
    assert.match(source, /highlight/);
});

test('transactions page exposes visibility filter and restore action for deleted rows', () => {
    assert.match(source, /transactions\.sheet\.filters\.showDeleted/);
    assert.match(source, /v-model="showDeleted"/);
    assert.doesNotMatch(source, /visibilityFilter|showDeletedOnly/);
    assert.match(rowActionsSource, /transactions\.sheet\.actions\.restore/);
    assert.match(rowActionsSource, /transactions\.sheet\.actions\.forceDelete/);
    assert.match(
        classificationSource,
        /transactions\.sheet\.grid\.deletedBadge/,
    );
    assert.match(source, /restoreTransaction/);
    assert.match(source, /requestForceDelete/);
    assert.match(source, /confirmForceDelete/);
});

test('transactions page requires a dedicated confirmation dialog before permanent delete', () => {
    assert.match(source, /forceDeletingTransaction = ref/);
    assert.match(source, /:open="forceDeletingTransaction !== null"/);
    assert.match(source, /transactions\.sheet\.forceDeleteDialog\.title/);
    assert.match(source, /transactions\.sheet\.forceDeleteDialog\.description/);
    assert.match(source, /transactions\.sheet\.forceDeleteDialog\.cancel/);
    assert.match(source, /transactions\.sheet\.forceDeleteDialog\.confirm/);
    assert.match(source, /variant="destructive"/);
    assert.match(source, /@click="confirmForceDelete"/);
    assert.match(source, /@click="forceDeletingTransaction = null"/);
    assert.match(source, /requestForceDelete\(\s*transaction/);
});

test('transactions page exposes a dedicated refund action with an autonomous refund date', () => {
    assert.match(source, /refundingTransaction = ref/);
    assert.match(source, /refundForm = useForm\(\{\s*transaction_date: ''/);
    assert.match(source, /requestRefund\(\s*transaction,?\s*\)/);
    assert.match(source, /confirmRefund/);
    assert.match(
        source,
        /\/transactions\/\$\{props\.year}\/\$\{props\.month}\/\$\{refundingTransaction\.value\.uuid}\/refund/,
    );
    assert.match(rowActionsSource, /transactions\.sheet\.actions\.refund/);
    assert.match(rowActionsSource, /show: editable && row\.can_refund/);
    assert.match(source, /transactions\.sheet\.dialog\.refundTitle/);
    assert.match(source, /transactions\.sheet\.dialog\.refundDescription/);
    assert.match(source, /transactions\.sheet\.dialog\.refundDate/);
    assert.match(source, /transactions\.sheet\.dialog\.refundConfirm/);
    assert.match(source, /<AppDatePicker/);
    assert.match(source, /v-model="refundForm\.transaction_date"/);
});

test('transactions page exposes an undo-refund action and highlights refund rows explicitly', () => {
    assert.match(source, /undoRefund\(\s*transaction,?\s*\)/);
    assert.match(source, /transactions\.sheet\.actions\.undoRefund/);
    assert.match(source, /transaction\.can_undo_refund/);
    assert.match(source, /transaction\.kind === 'refund'/);
    assert.match(source, /TransactionClassificationBadges/);
    assert.match(classificationSource, /transactionAttributes/);
    assert.doesNotMatch(source, /transactions\.sheet\.grid\.refundBadge/);
});

test('transactions page shows a localized credit-card cycle hint for expenses and refunds', () => {
    assert.match(source, /function creditCardCycleHelper/);
    assert.match(
        source,
        /transactions\.sheet\.grid\.creditCardChargeCycleHint/,
    );
    assert.match(
        source,
        /transactions\.sheet\.grid\.creditCardRefundCycleHint/,
    );
    assert.match(source, /transaction\.credit_card_payment_due_date/);
});

test('transactions page exposes a collapsible mobile overview hero with persisted state', () => {
    assert.match(source, /ChevronUp,/);
    assert.match(source, /ChevronDown,/);
    assert.match(
        source,
        /const heroStorageKey = 'transactions-sheet-hero-collapsed'/,
    );
    assert.match(source, /const isHeroCollapsed = ref\(false\)/);
    assert.match(
        source,
        /window\.localStorage\.getItem\(heroStorageKey\) === 'true'/,
    );
    assert.match(
        source,
        /window\.localStorage\.setItem\(heroStorageKey, value \? 'true' : 'false'\)/,
    );
    assert.match(source, /class="space-y-4 p-5 md:hidden"/);
    assert.match(source, /class="hidden gap-6 p-5 md:grid/);
    assert.match(source, /transactions\.sheet\.actions\.expandOverview/);
    assert.match(source, /transactions\.sheet\.actions\.collapseOverview/);
});

test('transactions sheet keeps long account labels constrained in list and mobile detail views', () => {
    assert.match(
        source,
        /class="truncate font-medium text-slate-900 dark:text-slate-100"/,
    );
    assert.match(
        source,
        /class="text-xs break-words text-slate-500 dark:text-slate-400"/,
    );
    assert.match(
        source,
        /class="break-words text-slate-700 dark:text-slate-200"/,
    );
});

test('transactions transaction forms expose backend-driven fx preview and account currency guidance', () => {
    assert.match(formSheetSource, /previewExchangeSnapshot\.url\(/);
    assert.match(
        formSheetSource,
        /transactions\.form\.helper\.accountCurrency/,
    );
    assert.match(formSheetSource, /transactions\.form\.helper\.fxPreviewTitle/);
    assert.match(
        formSheetSource,
        /transactions\.form\.helper\.fxPreviewAmount/,
    );
    assert.match(
        formSheetSource,
        /transactions\.form\.helper\.fxPreviewRateDate/,
    );
    assert.match(formSheetSource, /visibleTransactionDateError/);
    assert.match(source, /previewExchangeSnapshot\.url\(/);
    assert.match(source, /inlineExchangePreview/);
    assert.match(source, /editExchangePreview/);
    assert.match(source, /visibleInlineDayError/);
    assert.match(source, /visibleEditDayError/);
});

test('transactions rows format original currency and expose converted base amount details for multi currency entries', () => {
    assert.match(source, /function transactionAmountCurrency/);
    assert.match(source, /function transactionConvertedAmountLabel/);
    assert.match(source, /function transactionExchangeRateContextLabel/);
    assert.match(source, /transaction\.currency_code/);
    assert.match(source, /transactions\.sheet\.grid\.convertedAmount/);
    assert.match(source, /transactions\.sheet\.grid\.exchangeRateContext/);
});

test('transactions edit type selectors expose refund as a virtual option before move in italian and english', () => {
    assert.match(source, /const refundTypeKey = 'refund'/);
    assert.match(formSheetSource, /const refundTypeKey = 'refund'/);
    assert.match(source, /transactions\.form\.actions\.refund/);
    assert.match(formSheetSource, /transactions\.form\.actions\.refund/);
    assert.match(
        source,
        /value: refundTypeKey,\s*label: t\('transactions\.form\.actions\.refund'\)/s,
    );
    assert.match(
        formSheetSource,
        /value: refundTypeKey,\s*label: t\('transactions\.form\.actions\.refund'\)/s,
    );
    assert.match(
        source,
        /value: moveTypeKey,\s*label: t\('transactions\.form\.actions\.move'\)/s,
    );
    assert.match(
        formSheetSource,
        /value: moveTypeKey,\s*label: t\('transactions\.form\.actions\.move'\)/s,
    );
    assert.match(source, /handleInlineEditTypeChange\(/);
    assert.match(formSheetSource, /handleTypeSelection\(/);
});

test('transactions account selects group payment accounts and credit cards in filters and both edit forms', () => {
    assert.match(source, /import MobileSearchableSelect/);
    assert.match(source, /<MobileSearchableSelect\s+v-model="selectedAccount"/);
    assert.match(source, /account_type_code === 'credit_card'/);
    assert.match(source, /dashboard\.filters\.paymentAccountsGroup/);
    assert.match(source, /dashboard\.filters\.creditCardsGroup/);
    assert.match(source, /sortAccountOptionsByGroup/);
    assert.match(formSheetSource, /sortAccountOptionsByGroup/);
    assert.match(source, /inlineAccountOptions = computed/);
    assert.match(source, /inlineDestinationAccountOptions = computed/);
    assert.match(source, /editAccountOptions = computed/);
    assert.match(source, /editDestinationAccountOptions = computed/);
    assert.match(formSheetSource, /accountSelectOptions = computed/);
    assert.match(formSheetSource, /destinationAccountOptions = computed/);
    assert.match(source, /left\.account_type_code === 'credit_card' \? 1 : 0/);
    assert.match(
        formSheetSource,
        /left\.account_type_code === 'credit_card' \? 1 : 0/,
    );
    assert.match(formSheetSource, /groupLabel: accountGroupLabel\(account\)/);
    assert.match(source, /groupLabel: accountGroupLabel\(account\)/);
    assert.match(source, /:options="\s*inlineAccountOptions\s*"/s);
    assert.match(source, /:options="\s*inlineDestinationAccountOptions\s*"/s);
    assert.match(source, /:options="\s*editAccountOptions\s*"/s);
    assert.match(source, /:options="\s*editDestinationAccountOptions\s*"/s);
    assert.match(formSheetSource, /:options="accountSelectOptions"/);
    assert.match(formSheetSource, /:options="destinationAccountOptions"/);
    assert.match(
        readFileSync(
            new URL(
                '../../resources/js/components/transactions/SearchableSelect.vue',
                import.meta.url,
            ),
            'utf8',
        ),
        /groupedVisibleOptions = computed/,
    );
});

test('transactions refund dialog is localized in italian and english', () => {
    assert.match(messagesSource, /refund: 'Rimborso'/);
    assert.match(messagesSource, /refund: 'Rimborsa'/);
    assert.match(messagesSource, /refundTitle: 'Registra rimborso'/);
    assert.match(
        messagesSource,
        /refundDescription:\s+'Verrà creata una nuova transazione opposta senza modificare il movimento originale\.'/,
    );
    assert.match(messagesSource, /refundDate: 'Data rimborso'/);
    assert.match(messagesSource, /refundConfirm: 'Conferma rimborso'/);
    assert.match(messagesSource, /undoRefund: 'Annulla rimborso'/);
    assert.match(messagesSource, /refundBadge: 'Rimborso'/);
    assert.match(
        messagesSource,
        /creditCardChargeCycleHint:\s+'Inclusa nell’addebito del {date}'/,
    );
    assert.match(
        messagesSource,
        /creditCardRefundCycleHint:\s+'Compensa l’addebito del {date}'/,
    );
    assert.match(
        messagesSource,
        /accountCurrency:\s+'L’importo verrà registrato in {currency}\.'/,
    );
    assert.match(messagesSource, /fxPreviewTitle: 'Anteprima conversione'/);
    assert.match(messagesSource, /fxPreviewAmount:\s+'{source} ≈ {target}'/);
    assert.match(
        messagesSource,
        /fxPreviewRateDate:\s+'Basata sul tasso del {date}\.'/,
    );
    assert.match(messagesSource, /convertedAmount:\s+'Base {amount}'/);
    assert.match(
        messagesSource,
        /exchangeRateContext:\s+'Cambio {rate} del {date}'/,
    );
    assert.match(messagesSource, /refund: 'Refund'/);
    assert.match(messagesSource, /refund: 'Refund'/);
    assert.match(messagesSource, /refundTitle: 'Record refund'/);
    assert.match(
        messagesSource,
        /refundDescription:\s+'A new opposite transaction will be created without changing the original movement\.'/,
    );
    assert.match(messagesSource, /refundDate: 'Refund date'/);
    assert.match(messagesSource, /refundConfirm: 'Confirm refund'/);
    assert.match(messagesSource, /undoRefund: 'Undo refund'/);
    assert.match(messagesSource, /refundBadge: 'Refund'/);
    assert.match(
        messagesSource,
        /creditCardChargeCycleHint:\s+'Included in the charge due on {date}'/,
    );
    assert.match(
        messagesSource,
        /creditCardRefundCycleHint:\s+'Offsets the charge due on {date}'/,
    );
    assert.match(
        messagesSource,
        /accountCurrency:\s+'The amount will be recorded in {currency}\.'/,
    );
    assert.match(messagesSource, /fxPreviewTitle: 'Conversion preview'/);
    assert.match(messagesSource, /fxPreviewAmount:\s+'{source} ≈ {target}'/);
    assert.match(
        messagesSource,
        /fxPreviewRateDate:\s+'Based on the rate for {date}\.'/,
    );
    assert.match(messagesSource, /convertedAmount:\s+'Base {amount}'/);
    assert.match(
        messagesSource,
        /exchangeRateContext:\s+'Rate {rate} on {date}'/,
    );
});

test('transactions permanent delete dialog is localized in italian and english', () => {
    assert.match(
        messagesSource,
        /title: 'Eliminare definitivamente questa transazione\?'/,
    );
    assert.match(
        messagesSource,
        /description:\s+'Questa azione è irreversibile\.[\s\S]*non potrà più essere ripristinata\./,
    );
    assert.match(messagesSource, /cancel: 'Annulla'/);
    assert.match(messagesSource, /confirm: 'Elimina definitivamente'/);
    assert.match(
        messagesSource,
        /title: 'Permanently delete this transaction\?'/,
    );
    assert.match(
        messagesSource,
        /description:\s+'This action is irreversible\.[\s\S]*cannot be restored\./,
    );
    assert.match(messagesSource, /cancel: 'Cancel'/);
    assert.match(messagesSource, /confirm: 'Delete permanently'/);
});

test('scheduled transactions expose canonical recurring navigation on both layouts', () => {
    assert.equal(source.match(/<TransactionRowActions/g)?.length, 2);
    assert.match(rowActionsSource, /href: row.recurring_entry_show_url/);
    assert.match(
        rowActionsSource,
        /transactions\.sheet\.actions\.openRecurring/,
    );
    assert.match(rowActionsSource, /ArrowUpRight/);
});

test('transactions page exposes audit tooltip metadata for shared-account context', () => {
    assert.match(source, /auditTransaction\.value\s*=\s*transaction/);
    assert.match(source, /isSharedAccountTransaction/);
    assert.match(source, /transactionHasAuditDetails/);
    assert.match(source, /shouldShowTransactionAuditIcon/);
    assert.match(source, /transactionAuditCreatedLabel/);
    assert.match(source, /transactionAuditUpdatedLabel/);
    assert.match(source, /transactions\.sheet\.grid\.createdBy/);
    assert.match(source, /transactions\.sheet\.grid\.updatedBy/);
    assert.match(source, /transactionAuditCreatedLabel/);
});

test('audit icon visibility is symmetric and hides self-authored shared transactions', () => {
    assert.match(
        source,
        /function shouldShowTransactionAuditIcon[\s\S]*!isSharedAccountTransaction\(transaction\)/,
    );
    assert.match(
        source,
        /function shouldShowTransactionAuditIcon[\s\S]*createdBy === null/,
    );
    assert.match(
        source,
        /function shouldShowTransactionAuditIcon[\s\S]*createdBy\.uuid !== authenticatedUserUuid/,
    );
});

test('transactions category selectors use account-aware payload slices instead of a global mixed category list', () => {
    assert.match(source, /resolveAccountCategoryContributorUserIds/);
    assert.match(source, /filterEditorCategoriesByAccount/);
    assert.match(source, /category_contributor_user_ids/);
    assert.match(
        source,
        /sheet\.value\.editor\.categories\[accountUuid] \?\? \[]/,
    );
    assert.match(
        source,
        /contributorUserIds\.includes\(category\.owner_user_id \?\? -1\)/,
    );
    assert.match(source, /ensureCategoryMatchesAccountContext/);
    assert.match(formSheetSource, /categoriesForSelectedAccount/);
    assert.match(
        formSheetSource,
        /props\.sheet\.editor\.categories\[accountUuid] \?\? \[]/,
    );
    assert.match(
        formSheetSource,
        /contributorUserIds\.includes\(category\.owner_user_id \?\? -1\)/,
    );
    assert.match(formSheetSource, /hierarchical/);
    assert.match(formSheetSource, /ensureCategoryMatchesAccountContext/);
    assert.match(source, /hierarchical/);
    assert.match(
        readFileSync(
            new URL(
                '../../resources/js/components/transactions/SearchableSelect.vue',
                import.meta.url,
            ),
            'utf8',
        ),
        /is_selectable\?: boolean/,
    );
});

test('transactions scope selectors filter options by the selected account contributors', () => {
    assert.match(source, /resolveAccountScopeContributorUserIds/);
    assert.match(source, /filterEditorScopesByAccount/);
    assert.match(source, /scope_contributor_user_ids/);
    assert.match(
        source,
        /contributorUserIds\.includes\(scope\.owner_user_id \?\? -1\)/,
    );
    assert.match(source, /ensureScopeMatchesAccountContext/);
    assert.match(formSheetSource, /resolveAccountScopeContributorUserIds/);
    assert.match(formSheetSource, /scope_contributor_user_ids/);
    assert.match(
        formSheetSource,
        /contributorUserIds\.includes\(scope\.owner_user_id \?\? -1\)/,
    );
    assert.match(formSheetSource, /ensureScopeMatchesAccountContext/);
});

test('transactions forms do not expose scope as a separate user-facing label', () => {
    assert.doesNotMatch(source, /transactions\.form\.labels\.scope/);
    assert.doesNotMatch(formSheetSource, /transactions\.form\.labels\.scope/);
});

test('transactions tracked item selectors filter options by the selected account contributors and reset invalid values', () => {
    assert.match(source, /resolveAccountTrackedItemContributorUserIds/);
    assert.match(source, /tracked_item_contributor_user_ids/);
    assert.match(
        source,
        /contributorUserIds\.includes\(option\.owner_user_id \?\? -1\)/,
    );
    assert.match(source, /inlineForm\.tracked_item_uuid = ''/);
    assert.match(source, /editForm\.tracked_item_uuid = ''/);
    assert.match(
        formSheetSource,
        /resolveAccountTrackedItemContributorUserIds/,
    );
    assert.match(formSheetSource, /tracked_item_contributor_user_ids/);
    assert.match(
        formSheetSource,
        /contributorUserIds\.includes\(option\.owner_user_id \?\? -1\)/,
    );
    assert.match(formSheetSource, /form\.tracked_item_uuid = ''/);
});

test('transactions reference creation surfaces backend slug validation errors on the visible reference field', () => {
    assert.match(source, /payload\?\.errors\?\.slug/);
    assert.match(formSheetSource, /payload\?\.errors\?\.slug/);
    assert.match(source, /tracked_item_uuid/);
    assert.match(formSheetSource, /tracked_item_uuid/);
});

test('transactions tracked item create-option uses the account-aware endpoint and sends account plus category context', () => {
    assert.match(
        source,
        /fetchWithNotifications\(\s*'\/transactions\/tracked-items'/,
    );
    assert.match(source, /account_uuid: accountUuid/);
    assert.match(source, /category_uuid: categoryUuid/);
    assert.match(source, /type_key: typeKey/);
    assert.match(
        formSheetSource,
        /import \{ storeTrackedItemOption \} from '@\/actions\/App\/Http\/Controllers\/TransactionsController'/,
    );
    assert.match(
        formSheetSource,
        /fetchWithNotifications\(\s*storeTrackedItemOption\.url\(\)/,
    );
    assert.match(formSheetSource, /account_uuid: form\.account_uuid/);
    assert.match(formSheetSource, /category_uuid: form\.category_uuid/);
    assert.match(formSheetSource, /type_key: form\.type_key/);
    assert.doesNotMatch(source, /fetch\('\/settings\/tracked-items'/);
    assert.doesNotMatch(formSheetSource, /fetch\('\/settings\/tracked-items'/);
});

test('transaction form sheet renders account selection before category selection', () => {
    const accountLabelIndex = formSheetSource.indexOf(
        'transactions.form.labels.account',
    );
    const categoryLabelIndex = formSheetSource.indexOf(
        'transactions.form.labels.category',
    );

    assert.ok(accountLabelIndex !== -1);
    assert.ok(categoryLabelIndex !== -1);
    assert.ok(accountLabelIndex < categoryLabelIndex);
});

test('inline desktop transaction form renders account selection before category selection', () => {
    const inlineCreateRowStart = source.indexOf(
        'v-for="option in inlineCreateTypeOptions"',
    );
    const inlineCreateRowEnd = source.indexOf(
        'submitInlineTransaction',
        inlineCreateRowStart,
    );
    const inlineCreateMarkup = source.slice(
        inlineCreateRowStart,
        inlineCreateRowEnd,
    );
    const accountFieldIndex = inlineCreateMarkup.indexOf("'account_uuid'");
    const categoryFieldIndex = inlineCreateMarkup.indexOf("'category_uuid'");

    assert.ok(accountFieldIndex !== -1);
    assert.ok(categoryFieldIndex !== -1);
    assert.ok(accountFieldIndex < categoryFieldIndex);
});

test('desktop table headers align account resource before category in the inline register', () => {
    const accountHeaderIndex = source.indexOf(
        'transactions.sheet.grid.columns.accountResource',
    );
    const categoryHeaderIndex = source.indexOf(
        'transactions.sheet.grid.columns.category',
    );

    assert.ok(accountHeaderIndex !== -1);
    assert.ok(categoryHeaderIndex !== -1);
    assert.ok(accountHeaderIndex < categoryHeaderIndex);
});

test('desktop transaction rows render data cells in the same order as the headers', () => {
    const desktopRowStart = source.indexOf('@dblclick=');
    const desktopRowMarkup = source.slice(desktopRowStart);
    const accountLabelIndex = desktopRowMarkup.indexOf(
        'transaction.account_label',
    );
    const categoryLabelIndex = desktopRowMarkup.indexOf(
        'transaction.category_label',
    );
    const amountIndex = desktopRowMarkup.indexOf('transaction.amount_raw');
    const detailIndex = desktopRowMarkup.indexOf('transaction.detail ??');
    const trackedItemIndex = desktopRowMarkup.indexOf(
        'transaction.tracked_item_label',
    );

    assert.ok(desktopRowStart !== -1);
    assert.ok(accountLabelIndex !== -1);
    assert.ok(categoryLabelIndex !== -1);
    assert.ok(amountIndex !== -1);
    assert.ok(detailIndex !== -1);
    assert.ok(trackedItemIndex !== -1);
    assert.ok(accountLabelIndex < categoryLabelIndex);
    assert.ok(categoryLabelIndex < amountIndex);
    assert.ok(amountIndex < detailIndex);
    assert.ok(detailIndex < trackedItemIndex);
});

test('transaction rows render cached tracked item logos with an accessible name fallback', () => {
    assert.match(source, /TrackedItemIdentity/);
    assert.match(source, /transaction\.tracked_item_logo_url/);
    assert.match(source, /:name="\s*transaction\.tracked_item_label\s*"/);
});

test('desktop table uses a single compact localized date', () => {
    assert.match(source, /function formatDateNumeric/);
    assert.match(source, /dateFormatPreference/);
    assert.match(source, /replace\('YYYY', year\)/);
    assert.match(source, /formatDateShort\(\s*transaction\.date/);
});

test('transactions page marks credit debt generated movements and links back to the item', () => {
    assert.match(source, /is_credit_debt_transaction/);
    assert.match(classificationSource, /creditDebtBadge/);
    assert.match(source, /fromCreditDebt/);
    assert.match(source, /credit_debt_item_show_url/);
    assert.match(source, /creditDebtLink/);
});

test('desktop inline headers keep detail separate from the final reference control', () => {
    const detailHeaderIndex = source.indexOf(
        'transactions.sheet.grid.columns.detail',
    );
    const trackedItemHeaderIndex = source.indexOf(
        'transactions.sheet.grid.columns.trackedItem',
    );

    assert.ok(detailHeaderIndex !== -1);
    assert.ok(trackedItemHeaderIndex !== -1);
    assert.ok(detailHeaderIndex < trackedItemHeaderIndex);
});

test('transaction form sheet keeps detail before the final reference field', () => {
    const detailLabelIndex = formSheetSource.indexOf(
        'transactions.form.labels.detail',
    );
    const trackedItemLabelIndex = formSheetSource.indexOf(
        'transactions.form.labels.trackedItem',
    );

    assert.ok(detailLabelIndex !== -1);
    assert.ok(trackedItemLabelIndex !== -1);
    assert.ok(detailLabelIndex < trackedItemLabelIndex);
});

test('inline transaction category preview uses editor category overview items instead of the generic overview list', () => {
    assert.match(source, /sheet\.value\.editor\.category_overview_items\.find/);
});

test('transactions summary cards switch to filtered account totals when filters are active', () => {
    assert.match(source, /const filteredSummary = computed/);
    assert.match(source, /const filteredLastBalance = computed/);
    assert.match(source, /transaction\.kind === 'refund'/);
    assert.match(source, /summary\.expenses -= amount/);
    assert.match(source, /summary\.income -= amount/);
    assert.match(
        source,
        /hasActiveFilters\.value\s*\?\s*filteredSummary\.value\.income/,
    );
    assert.match(
        source,
        /hasActiveFilters\.value\s*\?\s*filteredSummary\.value\.expenses/,
    );
    assert.match(
        source,
        /hasActiveFilters\.value\s*\?\s*filteredSummary\.value\.net/,
    );
    assert.match(
        source,
        /selectedAccount\.value !== 'all'\s*\?\s*filteredLastBalance\.value/,
    );
});

test('refund actions use a neutral refresh icon and accessible localized explanations', () => {
    assert.match(rowActionsSource, /icon: RefreshCcw/);
    assert.match(rowActionsSource, /:aria-label="action.label"/);
    assert.match(rowActionsSource, /:title="action.reason \|\| action.label"/);
    assert.match(rowActionsSource, /TooltipContent/);
    assert.match(rowActionsSource, /role="note"/);
});

test('transaction register adapts to available width and releases the summary column', () => {
    assert.doesNotMatch(source, /min-w-\[1140px\]|overflow-x-auto xl:block/);
    assert.match(source, /table-fixed/);
    assert.match(source, /summary-closed/);
    assert.match(source, /grid-template-columns: minmax\(0, 1fr\) 36px/);
    assert.match(source, /monthly-summary-open:v1/);
    assert.match(source, /prefers-reduced-motion: reduce/);
});

test('macrogroup selects deduplicate the global all option', () => {
    assert.match(
        source,
        /const macrogroupFilterOptions = computed\(\(\) => \{/,
    );
    assert.match(source, /const seenValues = new Set<string>\(\)/);
    assert.match(
        source,
        /\{ value: 'all', label: t\('transactions\.index\.labels\.allGroups'\) }/,
    );
    assert.match(source, /seenValues\.has\(option\.value\)/);
});

test('transaction create forms prefer the exposed default account while edit preserves the saved account', () => {
    assert.match(source, /function resolveDefaultEditorAccountUuid/);
    assert.match(source, /sheet\.value\.editor\.default_account_uuid/);
    assert.match(source, /account_uuid: resolveDefaultEditorAccountUuid\(\)/);
    assert.match(
        source,
        /preservedAccount \|\| resolveDefaultEditorAccountUuid\(\)/,
    );
    assert.match(formSheetSource, /function resolveDefaultAccountUuid/);
    assert.match(formSheetSource, /props\.sheet\.editor\.default_account_uuid/);
    assert.match(
        formSheetSource,
        /account_uuid: resolveDefaultAccountUuid\(\)/,
    );
});

test('transaction form sheet exposes balance adjustment preview fields and backend preview fetch', () => {
    assert.match(
        formSheetSource,
        /const balanceAdjustmentTypeKey = 'balance_adjustment'/,
    );
    assert.match(formSheetSource, /adjustmentTypeOptions/);
    assert.match(formSheetSource, /props\.sheet\.editor\.type_options\.filter/);
    assert.match(
        formSheetSource,
        /!isEditing\.value \|\| option\.create_only !== true/,
    );
    assert.match(formSheetSource, /balanceAdjustmentPreview/);
    assert.match(formSheetSource, /balanceAdjustmentLoading/);
    assert.match(formSheetSource, /balance-adjustment-preview/);
    assert.match(
        formSheetSource,
        /transactions\.form\.labels\.theoreticalBalance/,
    );
    assert.match(formSheetSource, /transactions\.form\.labels\.desiredBalance/);
    assert.match(
        formSheetSource,
        /transactions\.form\.labels\.adjustmentDifference/,
    );
});

test('transaction form sheet shows the selected account current balance with dedicated state separate from adjustment preview', () => {
    assert.match(
        formSheetSource,
        /const accountCurrentBalance = ref<number \| null>\(null\)/,
    );
    assert.match(
        formSheetSource,
        /const accountCurrentBalanceLoading = ref\(false\)/,
    );
    assert.match(
        formSheetSource,
        /async function refreshAccountCurrentBalance/,
    );
    assert.match(formSheetSource, /desired_balance: 0/);
    assert.match(formSheetSource, /transactions\.form\.labels\.currentBalance/);
    assert.match(formSheetSource, /accountCurrentBalance !==\s*null/);
});

test('move mode is available only in edit flows and locks non-date fields in both inline and sheet forms', () => {
    assert.match(source, /const moveTypeKey = 'move'/);
    assert.match(formSheetSource, /const moveTypeKey = 'move'/);
    assert.match(
        source,
        /const moveEligibleTypeKeys = \['income', 'expense', 'bill', 'debt', 'saving']/,
    );
    assert.match(
        formSheetSource,
        /const moveEligibleTypeKeys = \['income', 'expense', 'bill', 'debt', 'saving']/,
    );
    assert.match(source, /moveAvailableYears/);
    assert.match(formSheetSource, /moveAvailableYears/);
    assert.match(source, /moveDateMin/);
    assert.match(formSheetSource, /moveDateMin/);
    assert.match(source, /moveDateMax/);
    assert.match(formSheetSource, /moveDateMax/);
    assert.match(source, /transactions\.form\.actions\.move/);
    assert.match(formSheetSource, /transactions\.form\.actions\.move/);
    assert.match(source, /canMoveTransaction/);
    assert.match(formSheetSource, /canMoveTransaction/);
    assert.match(source, /!transaction\.is_recurring_transaction/);
    assert.match(formSheetSource, /!transaction\.is_recurring_transaction/);
    assert.match(source, /transactions\.form\.errors\.moveYearUnavailable/);
    assert.match(
        formSheetSource,
        /transactions\.form\.errors\.moveYearUnavailable/,
    );
    assert.match(
        source,
        /inlineCreateTypeOptions = computed\(\(\) => sheet\.value\.editor\.type_options\)/,
    );
    assert.match(source, /:disabled="isEditMove"/);
    assert.match(formSheetSource, /:disabled="isMoveMode"/);
    assert.match(source, /transaction_date/);
    assert.match(formSheetSource, /transaction_date/);
    assert.match(source, /<AppDatePicker/);
    assert.match(formSheetSource, /<AppDatePicker/);
    assert.match(source, /transactions\.form\.labels\.moveDate/);
    assert.match(formSheetSource, /transactions\.form\.labels\.moveDate/);
});

test('balance adjustment rows expose a dedicated badge tooltip and balance effect label', () => {
    assert.match(source, /function isBalanceAdjustmentTransaction/);
    assert.match(source, /function balanceAdjustmentEffectLabel/);
    assert.match(source, /transactions\.sheet\.grid\.balanceAdjustmentBadge/);
    assert.match(
        source,
        /transactions\.sheet\.grid\.balanceAdjustmentTooltipTitle/,
    );
    assert.match(
        source,
        /transactions\.sheet\.grid\.balanceAdjustmentTooltipBody/,
    );
    assert.match(
        source,
        /transactions\.sheet\.grid\.balanceAdjustmentIncrease/,
    );
    assert.match(
        source,
        /transactions\.sheet\.grid\.balanceAdjustmentDecrease/,
    );
    assert.match(source, /<Scale/);
});

test('inline and sheet forms both read transaction types from the shared type_options source', () => {
    assert.match(source, /const inlineCreateTypeOptions = computed/);
    assert.match(source, /const inlineEditTypeOptions = computed/);
    assert.match(source, /sheet\.value\.editor\.type_options/);
    assert.match(formSheetSource, /props\.sheet\.editor\.type_options/);
    assert.match(source, /inlineCreateTypeOptions/);
    assert.match(source, /inlineEditTypeOptions/);
});

test('transaction type options are separated from macrogroup options', () => {
    assert.match(source, /sheet\.value\.editor\.type_options/);
    assert.doesNotMatch(source, /sheet\.value\.editor\.group_options\.filter/);
});

test('inline register exposes balance adjustment handling instead of hiding the type', () => {
    assert.match(source, /const isInlineBalanceAdjustment = computed/);
    assert.match(source, /normalizeInlineDesiredBalance/);
    assert.match(source, /refreshInlineBalanceAdjustmentPreview/);
    assert.match(source, /inlineBalanceAdjustmentPreview/);
    assert.match(source, /v-for="option in inlineCreateTypeOptions"/);
});

test('inline balance adjustment shows current balance and current amount labels only in the desktop form', () => {
    assert.match(
        source,
        /transactions\.sheet\.grid\.balanceAdjustmentCurrentBalanceLabel/,
    );
    assert.match(
        source,
        /transactions\.sheet\.grid\.balanceAdjustmentCurrentAmountLabel/,
    );
    assert.match(source, /inlineBalanceAdjustmentCurrentBalanceRaw/);
    assert.match(source, /inlineBalanceAdjustmentCurrentBalanceLoading/);
    assert.match(source, /refreshInlineBalanceAdjustmentCurrentBalance/);
    assert.doesNotMatch(source, /transactions\.balance_adjustment\.kind_label/);
    assert.doesNotMatch(
        formSheetSource,
        /transactions\.sheet\.grid\.balanceAdjustmentCurrentBalanceLabel/,
    );
    assert.doesNotMatch(
        formSheetSource,
        /transactions\.sheet\.grid\.balanceAdjustmentCurrentAmountLabel/,
    );
});

test('transaction type lists do not expose unsupported move scheduling placeholders', () => {
    assert.doesNotMatch(source, /spostamento/i);
    assert.doesNotMatch(formSheetSource, /spostamento/i);
});

test('transaction form sheet keeps source and destination account selects wired to the editable account lists', () => {
    assert.match(formSheetSource, /v-model="form\.account_uuid"/);
    assert.match(formSheetSource, /:options="accountSelectOptions"/);
    assert.match(
        formSheetSource,
        /v-model="form\.account_uuid"[\s\S]*:teleport="false"/,
    );
    assert.match(formSheetSource, /v-model="form\.destination_account_uuid"/);
    assert.match(formSheetSource, /:options="destinationAccountOptions"/);
    assert.match(
        formSheetSource,
        /v-model="form\.destination_account_uuid"[\s\S]*:teleport="false"/,
    );
    assert.match(
        formSheetSource,
        /form\.destination_account_uuid === form\.account_uuid/,
    );
    assert.match(
        formSheetSource,
        /transactions\.form\.errors\.destinationAccountRequired/,
    );
    assert.match(
        formSheetSource,
        /transactions\.form\.errors\.destinationAccountDifferent/,
    );
});

test('transaction form sheet keeps validation and reference errors visible in the mobile form', () => {
    assert.match(formSheetSource, /InputError/);
    assert.match(formSheetSource, /form\.errors\.account_uuid/);
    assert.match(formSheetSource, /form\.errors\.category_uuid/);
    assert.match(formSheetSource, /destination_account_uuid/);
    assert.match(formSheetSource, /form\.errors\.desired_balance/);
    assert.match(formSheetSource, /form\.errors\.tracked_item_uuid \|\|/);
    assert.match(formSheetSource, /payload\?\.errors\?\.slug/);
});

test('transaction form suggests existing tracked items and links a selected suggestion to the category', () => {
    assert.match(
        formSheetSource,
        /const suggestedOptions\s*=\s*searchQuery\.trim\(\) === ''/,
    );
    assert.match(formSheetSource, /searchQuery\.trim\(\) === ''/);
    assert.match(
        formSheetSource,
        /function handleReferenceSelection\(value: string\)/,
    );
    assert.match(
        formSheetSource,
        /createTrackedItemFromContext\(trackedItem\.name \?\? trackedItem\.label\)/,
    );
    assert.match(
        formSheetSource,
        /@search-query="\s*trackedItemSearchQuery = \$event\s*"/,
    );
    assert.match(
        formSheetSource,
        /@update:model-value="\s*handleReferenceSelection\s*"/,
    );
});

test('mobile transaction form derives a single effective date from the selected period and day', () => {
    assert.match(formSheetSource, /const effectiveTransactionDate = computed/);
    assert.match(formSheetSource, /new Date\(year, month - 1, day\)/);
    assert.match(formSheetSource, /new Date\(year, month, 0\)\.getDate\(\)/);
    assert.match(formSheetSource, /props\.year === todayDate\.value\.year/);
    assert.match(formSheetSource, /props\.month === todayDate\.value\.month/);
    assert.match(
        formSheetSource,
        /transaction_day: isSelectedPeriodCurrent\s*\? String\(todayDate\.value\.day\)\s*: '1'/,
    );
});

test('mobile transaction form preserves an edited transaction date and explains its context', () => {
    assert.match(
        formSheetSource,
        /transaction_day: transactionDateParts\s*\? String\(transactionDateParts\.day\)\s*: '1'/,
    );
    assert.match(formSheetSource, /const transactionDateContext = computed/);
    assert.match(formSheetSource, /transactions\.form\.dateContext\.today/);
    assert.match(
        formSheetSource,
        /transactions\.form\.dateContext\.differentDay/,
    );
    assert.match(
        formSheetSource,
        /transactions\.form\.dateContext\.differentMonth/,
    );
    assert.match(formSheetSource, /CalendarDays/);
});

test('mobile transaction date context is localized in Italian and English', () => {
    assert.match(messagesSource, /today: 'Oggi'/);
    assert.match(messagesSource, /differentDay: 'Data diversa da oggi'/);
    assert.match(
        messagesSource,
        /differentMonth: 'Stai registrando nel mese selezionato'/,
    );
    assert.match(messagesSource, /today: 'Today'/);
    assert.match(messagesSource, /differentDay: 'Date differs from today'/);
    assert.match(
        messagesSource,
        /differentMonth: 'You are recording in the selected month'/,
    );
});

test('transaction form supports inline category and subcategory creation without leaving the form', () => {
    assert.match(formSheetSource, /TransactionCategoryQuickCreate/);
    assert.match(quickCreateSource, /categoryQuickCreateChoiceOpen/);
    assert.match(quickCreateSource, /openCategoryQuickCreate\('category'\)/);
    assert.match(quickCreateSource, /openCategoryQuickCreate\('subcategory'\)/);
    assert.match(
        quickCreateSource,
        /:require-parent="categoryQuickCreateKind === 'subcategory'"/,
    );
    assert.match(quickCreateSource, /json-store/);
    assert.match(quickCreateSource, /@created="selectCreatedCategory"/);
    assert.match(quickCreateSource, /categoryUuid\.value = uuid/);
});

test('quick category creation renders only parents compatible with the transaction context', () => {
    assert.match(
        quickCreateSource,
        /const quickCreateCategoryContext = computed/,
    );
    assert.match(
        quickCreateSource,
        /category\.type_key === props\.typeKey[\s\S]*category\.direction_type === directionType[\s\S]*category\.group_type === groupType/,
    );
    assert.match(
        quickCreateSource,
        /:parent-direction-type="quickCreateCategoryContext\.directionType"/,
    );
    assert.match(
        quickCreateSource,
        /:parent-group-type="quickCreateCategoryContext\.groupType"/,
    );
    assert.match(
        categoryFormSheetSource,
        /item\.direction_type === props\.parentDirectionType/,
    );
    assert.match(
        categoryFormSheetSource,
        /item\.group_type === props\.parentGroupType/,
    );
});

test('quick category creation resets its kind and only selects a compatible created category', () => {
    assert.match(quickCreateSource, /function updateCategoryQuickCreateOpen/);
    assert.match(
        quickCreateSource,
        /categoryQuickCreateKind\.value = 'category'/,
    );
    assert.match(
        quickCreateSource,
        /String\(category\.direction_type \?\? ''\) !== directionType/,
    );
    assert.match(
        quickCreateSource,
        /String\(category\.group_type \?\? ''\) !== groupType/,
    );
});

test('desktop transaction forms expose the shared quick category create trigger without duplicating the mobile CTA', () => {
    assert.match(
        source,
        /<TransactionCategoryQuickCreate[\s\S]*v-model="\s*inlineForm\.category_uuid\s*"/,
    );
    assert.match(
        source,
        /<TransactionCategoryQuickCreate[\s\S]*v-model="\s*editForm\.category_uuid\s*"/,
    );
    assert.match(source, /openQuickCreate/);
    assert.match(source, /transactions\.form\.quickCategory\.trigger/);
    assert.match(formSheetSource, /<TransactionCategoryQuickCreate/);
});

test('register display options contain exactly eight simple toggles', () => {
    const options = source.slice(
        source.indexOf('transactions.sheet.grid.title'),
        source.indexOf('transactions.sheet.grid.activeFilters'),
    );

    for (const name of [
        'showTransfers',
        'showIncome',
        'showExpenses',
        'showOpeningBalances',
        'showRefunded',
        'showShared',
        'showDeleted',
        'showRecurring',
    ]) {
        assert.match(options, new RegExp('v-model="' + name + '"'));
    }

    assert.equal(
        (options.match(/<TransactionVisibilityToggle/g) ?? []).length,
        8,
    );
    assert.doesNotMatch(
        options,
        /restrictive|Only|inclusionHint|showPlannedRecurring/,
    );
});

test('payment issue requires a dedicated dialog before sending the existing action', async () => {
    const ts = await import('typescript');
    const script = source.slice(
        source.indexOf('function reportPaymentProblem('),
        source.indexOf('const paymentConfirmationForm'),
    );
    const confirmation = source.slice(
        source.indexOf('function confirmPaymentProblem('),
        source.indexOf('function requestRefund('),
    );
    const js = ts.default.transpile(script + confirmation, {
        target: ts.default.ScriptTarget.ES2022,
    });
    const selected = { value: null };
    const requests = [];
    const form = {
        processing: false,
        clearErrors() {},
        post(...args) {
            requests.push(args);
        },
    };
    const handlers = new Function(
        'paymentProblemTransaction',
        'paymentProblemForm',
        'paymentProblem',
        `${js}; return { reportPaymentProblem, closePaymentProblem, confirmPaymentProblem };`,
    )(selected, form, { url: (uuid) => `/payment-problem/${uuid}` });
    const transaction = {
        uuid: 'test-payment',
        can_report_payment_problem: true,
    };
    handlers.reportPaymentProblem(transaction);
    assert.equal(selected.value, transaction);
    assert.equal(requests.length, 0);
    handlers.closePaymentProblem();
    handlers.confirmPaymentProblem();
    assert.equal(requests.length, 0);
    handlers.reportPaymentProblem(transaction);
    handlers.confirmPaymentProblem();
    assert.equal(requests[0][0], '/payment-problem/test-payment');
    form.processing = true;
    handlers.confirmPaymentProblem();
    handlers.closePaymentProblem();
    assert.equal(requests.length, 1);
    assert.equal(selected.value, transaction);
    requests[0][1].onSuccess();
    assert.equal(selected.value, null);
    assert.match(source, /:open="paymentProblemTransaction !== null"/);
    assert.match(messagesSource, /eliminata definitivamente/);
    assert.match(
        messagesSource,
        /non verrà registrata di nuovo automaticamente/,
    );
});

test('transaction visibility chips follow the requested display order', () => {
    const models = [
        ...source.matchAll(/<TransactionVisibilityToggle\s+v-model="([^"]+)"/g),
    ].map((match) => match[1]);
    assert.deepEqual(models, [
        'showIncome',
        'showExpenses',
        'showShared',
        'showRecurring',
        'showRefunded',
        'showTransfers',
        'showOpeningBalances',
        'showDeleted',
    ]);
});

test('day filter can be cleared before search and from the floating month controls', () => {
    const search = source.indexOf(
        'v-model="transactionSearch"',
        source.indexOf('<CardContent class="p-0">'),
    );
    const clear = source.lastIndexOf("ui('Rimuovi filtro giorno')", search);
    assert.ok(clear > 0 && clear < search);
    const floating = source.slice(
        source.indexOf('v-if="isMobilePeriodAccountSelectorOutOfView"'),
        source.indexOf('v-if="isMobilePeriodAccountSelectorOutOfView"') + 4000,
    );
    assert.match(floating, /v-if="selectedDay"/);
    assert.match(floating, /@click="selectedDay = ''"/);
    assert.match(source, /<RecurringCalendarGrid\s+compact-counters/);
});

test('calendar disclosure names its action and floating day uses compact spacing', () => {
    assert.match(source, /ui\('Apri calendario'\)/);
    assert.match(source, /ui\('Chiudi calendario'\)/);
    assert.match(source, /group-open\/calendar:rotate-180/);
    assert.match(source, /min-h-8 max-w-full items-center gap-1.5 rounded-lg/);
});

test('transactions calendar remembers disclosure state independently from filters', () => {
    assert.match(source, /soamco:transactions:calendar-open:v1/);
    assert.match(source, /:open="transactionCalendarOpen"/);
    assert.match(source, /@toggle="rememberCalendarState"/);
    assert.match(
        source,
        /localStorage.setItem\(\s*transactionCalendarStorageKey,\s*String\(open\),?\s*\)/,
    );
    const recurring = readFileSync(
        new URL(
            '../../resources/js/pages/transactions/recurring/Index.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const floating = recurring.slice(
        recurring.indexOf('v-if="isMobilePeriodAccountSelectorOutOfView"'),
        recurring.indexOf('v-if="isMobilePeriodAccountSelectorOutOfView"') +
            4000,
    );
    assert.match(floating, /v-if="selectedDate"/);
    assert.match(floating, /@click="selectedDate = null"/);
});

test('shared advances render once after both desktop and mobile bank registers', () => {
    const position = source.indexOf('<SharedLedgerMovements');
    assert.equal(source.match(/<SharedLedgerMovements/g)?.length, 1);
    assert.ok(position > source.indexOf('</table>'));
    assert.ok(position > source.indexOf('class="space-y-2 p-3 xl:hidden"'));
    assert.ok(
        position >
            source.lastIndexOf('v-if="displayedTransactions.length === 0"'),
    );
    assert.ok(position < source.indexOf('id="monthly-summary"'));
    assert.doesNotMatch(source, /function clearTransactionSearch/);
});

test('transaction actions share direct accessible controls on desktop and mobile', () => {
    assert.doesNotMatch(rowActionsSource, /DropdownMenu|MoreHorizontal/);
    assert.equal(source.match(/<TransactionRowActions/g)?.length, 2);
    assert.match(rowActionsSource, /transaction-direct-actions flex flex-wrap/);
    assert.match(rowActionsSource, /:aria-label="action.label"/);
    assert.match(rowActionsSource, /min-width: 44px/);
    assert.match(rowActionsSource, /:focus-visible/);

    for (const permission of [
        'can_edit',
        'can_refund',
        'can_delete',
        'can_restore',
        'can_force_delete',
        'can_undo_refund',
        'can_report_payment_problem',
        'can_confirm_payment',
    ]) {
        assert.ok(rowActionsSource.includes('row.' + permission));
    }
});

test('shared management reuses the transaction form and keeps inline draft fields in dialogs', () => {
    assert.match(source, /function openSharedManagement/);
    assert.match(source, /:shared-management="sharedManagement"/);
    assert.match(source, /v-model:open="\s*inlineSharedOpen\s*"/);
    assert.match(source, /v-model:open="\s*editSharedOpen\s*"/);
    assert.match(source, /inlineForm\.shared_expense_my_share/);
    assert.match(source, /editForm\.shared_expense_my_share/);
    assert.match(
        formSheetSource,
        /sharedManagement && !isMobile[\s\S]*DialogContent[\s\S]*SheetContent/,
    );
    assert.match(formSheetSource, /ref="sharedFieldsElement"/);
    assert.match(formSheetSource, /transactions\.sharedLedger\.myShare/);
    assert.match(
        formSheetSource,
        /transaction\.shared_expense_counterparty_label/,
    );
});

test('summary and edge toggle follow page scroll together while all summary content stays reachable', () => {
    assert.doesNotMatch(source, /class="mb-3 hidden size-9/);
    assert.match(source, /summary-toggle-track/);
    assert.match(source, /overflow-x: clip/);
    assert.match(source, /top: calc\(50dvh - 22px\)/);
    assert.match(source, /summary-closed \.summary-content/);
    assert.match(source, /display: none/);
    const styles = source.slice(source.indexOf('<style scoped>'));
    assert.doesNotMatch(
        styles,
        /\.summary-content\s*\{[^}]*position:\s*(fixed|sticky)/,
    );
    assert.match(styles, /\.summary-rail\s*\{[^}]*position:\s*sticky/);
    assert.match(styles, /\.summary-toggle\s*\{[^}]*position:\s*absolute/);
    assert.match(styles, /var\(--summary-height, 0px\)/);
    assert.doesNotMatch(styles, /overflow-y: (auto|scroll)/);
});

test('shared expense searches remain inside their modal focus scope and stacking context', () => {
    for (const markup of [source, formSheetSource]) {
        const fields = [
            ...markup.matchAll(/<SharedExpenseFields\b[\s\S]*?\/>/g),
        ];
        assert.ok(fields.length > 0);

        for (const [field] of fields) {
            assert.match(field, /:dropdown-teleport="\s*false\s*"/);
        }
    }
});

test('desktop shared management shows only the shared editor with an intrinsic-height dialog', () => {
    assert.match(formSheetSource, /const sharedOnly = computed/);
    assert.equal(
        (formSheetSource.match(/<template v-if="!sharedOnly"\s*>/g) ?? [])
            .length,
        2,
    );
    assert.match(formSheetSource, /sharedOnly\s*\? 'min-w-0 px-5 py-4'/);
    assert.doesNotMatch(formSheetSource, /h-\[min\(85dvh,900px\)\]/);
    assert.match(formSheetSource, /preventScroll: true/);
    assert.match(
        formSheetSource,
        /sharedOnly && Object\.keys\(form\.errors\)\.length/,
    );
});
