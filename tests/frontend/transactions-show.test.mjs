import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
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

test('opening balance rows keep the opening badge visible', () => {
    assert.match(source, /transactions\.sheet\.grid\.openingBadge/);
});

test('opening balance rows do not render the income or expense type badge', () => {
    assert.match(source, /v-if="\s*!transaction\.is_opening_balance\s*"/);
});

test('transactions page exposes a toggle to show opening balances', () => {
    assert.match(source, /transactions\.sheet\.filters\.showOpeningBalances/);
    assert.match(source, /showOpeningBalances/);
});

test('transactions page exposes a toggle and dedicated markers for recurring movements', () => {
    assert.match(source, /transactions\.sheet\.filters\.showPlannedRecurring/);
    assert.match(source, /showPlannedRecurring/);
    assert.match(source, /transactions\.sheet\.grid\.recurringBadge/);
    assert.match(source, /transactions\.sheet\.grid\.plannedRecurringBadge/);
    assert.match(source, /data-transaction-row/);
    assert.match(source, /highlight/);
});

test('transactions page exposes visibility filter and restore action for deleted rows', () => {
    assert.match(source, /transactions\.sheet\.filters\.visibility/);
    assert.match(source, /transactions\.sheet\.filters\.showDeletedOnly/);
    assert.match(source, /<SelectItem value="active">/);
    assert.match(source, /<SelectItem value="deleted">/);
    assert.match(source, /<SelectItem value="all">/);
    assert.match(source, /transactions\.sheet\.actions\.restore/);
    assert.match(source, /transactions\.sheet\.actions\.forceDelete/);
    assert.match(source, /transactions\.sheet\.grid\.deletedBadge/);
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
    assert.match(source, /requestRefund\(transaction\)/);
    assert.match(source, /confirmRefund/);
    assert.match(
        source,
        /\/transactions\/\$\{props\.year}\/\$\{props\.month}\/\$\{refundingTransaction\.value\.uuid}\/refund/,
    );
    assert.match(source, /transactions\.sheet\.actions\.refund/);
    assert.match(source, /transactions\.sheet\.dialog\.refundTitle/);
    assert.match(source, /transactions\.sheet\.dialog\.refundDescription/);
    assert.match(source, /transactions\.sheet\.dialog\.refundDate/);
    assert.match(source, /transactions\.sheet\.dialog\.refundConfirm/);
    assert.match(source, /type="date"/);
    assert.match(source, /v-model="refundForm\.transaction_date"/);
});

test('transactions page exposes an undo-refund action and highlights refund rows explicitly', () => {
    assert.match(source, /undoRefund\(transaction\)/);
    assert.match(source, /transactions\.sheet\.actions\.undoRefund/);
    assert.match(source, /transaction\.can_undo_refund/);
    assert.match(source, /transaction\.kind === 'refund'/);
    assert.match(source, /function transactionTypeBadgeTone/);
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
        /creditCardChargeCycleHint:\s+'Inclusa nell’addebito del \{date}'/,
    );
    assert.match(
        messagesSource,
        /creditCardRefundCycleHint:\s+'Compensa l’addebito del \{date}'/,
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
        /creditCardChargeCycleHint:\s+'Included in the charge due on \{date}'/,
    );
    assert.match(
        messagesSource,
        /creditCardRefundCycleHint:\s+'Offsets the charge due on \{date}'/,
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

test('scheduled transactions expose recurring management instead of delete-only handling', () => {
    assert.match(source, /transactions\.sheet\.actions\.openRecurring/);
    assert.match(source, /transaction\.kind === 'scheduled'/);
    assert.match(source, /transaction\.recurring_entry_show_url/);
    assert.match(source, /TooltipProvider/);
    assert.match(source, /TooltipTrigger as-child/);
    assert.match(source, /ArrowUpRight/);
    assert.match(
        source,
        /:aria-label="\s*t\(\s*'transactions\.sheet\.actions\.openRecurring'/,
    );
});

test('transactions page exposes audit tooltip metadata for shared-account context', () => {
    assert.match(source, /transactions\.sheet\.actions\.auditInfo/);
    assert.match(source, /isSharedAccountTransaction/);
    assert.match(source, /transactionHasAuditDetails/);
    assert.match(source, /shouldShowTransactionAuditIcon/);
    assert.match(source, /transactionAuditCreatedLabel/);
    assert.match(source, /transactionAuditUpdatedLabel/);
    assert.match(source, /transactions\.sheet\.grid\.createdBy/);
    assert.match(source, /transactions\.sheet\.grid\.updatedBy/);
    assert.match(source, /<User class="size-4"/);
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
    assert.match(source, /fetch\('\/transactions\/tracked-items'/);
    assert.match(source, /account_uuid: accountUuid/);
    assert.match(source, /category_uuid: categoryUuid/);
    assert.match(source, /type_key: typeKey/);
    assert.match(formSheetSource, /fetch\('\/transactions\/tracked-items'/);
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
    const desktopRowStart = source.indexOf(
        '@dblclick="\n                                                startInlineEdit(transaction)',
    );
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

test('desktop table shows the secondary date in dd-mm-yyyy format instead of ISO', () => {
    assert.match(source, /function formatDateNumeric/);
    assert.match(source, /day: '2-digit'/);
    assert.match(source, /month: '2-digit'/);
    assert.match(source, /year: 'numeric'/);
    assert.match(source, /formatDateNumeric\(\s*transaction\.date/);
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

test('transactions layout preserves more horizontal room for inline amount inputs on laptop widths', () => {
    assert.match(
        source,
        /grid gap-6 xl:grid-cols-\[minmax\(0,1fr\)_300px] 2xl:grid-cols-\[minmax\(0,1fr\)_340px]/,
    );
    assert.match(
        source,
        /w-\[11\.5rem] min-w-\[11\.5rem] px-4 py-3 text-right/,
    );
    assert.match(
        source,
        /min-w-\[10rem] px-4 text-right font-mono text-base font-semibold tracking-tight/,
    );
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
    assert.match(formSheetSource, /accountCurrentBalance !== null/);
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
    assert.match(source, /type="date"/);
    assert.match(formSheetSource, /type="date"/);
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
