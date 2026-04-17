import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const mobileAmountInputSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileAmountInput.vue',
        import.meta.url,
    ),
    'utf8',
);
const mobileSearchableSelectSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileSearchableSelect.vue',
        import.meta.url,
    ),
    'utf8',
);
const searchableSelectSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/SearchableSelect.vue',
        import.meta.url,
    ),
    'utf8',
);
const mobileTextFieldEditorSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileTextFieldEditor.vue',
        import.meta.url,
    ),
    'utf8',
);
const budgetPlanningMobileAmountEditorSource = readFileSync(
    new URL(
        '../../resources/js/components/budget-planning/BudgetPlanningMobileAmountEditor.vue',
        import.meta.url,
    ),
    'utf8',
);
const searchableSelectOptionContentSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/SearchableSelectOptionContent.vue',
        import.meta.url,
    ),
    'utf8',
);
const transactionsFormSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/TransactionFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const recurringFormSource = readFileSync(
    new URL(
        '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const accountFormSource = readFileSync(
    new URL(
        '../../resources/js/components/accounts/AccountFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const moneyInputSource = readFileSync(
    new URL('../../resources/js/components/MoneyInput.vue', import.meta.url),
    'utf8',
);
const sheetContentSource = readFileSync(
    new URL('../../resources/js/components/ui/sheet/SheetContent.vue', import.meta.url),
    'utf8',
);
const sheetOverlaySource = readFileSync(
    new URL('../../resources/js/components/ui/sheet/SheetOverlay.vue', import.meta.url),
    'utf8',
);
const dialogContentSource = readFileSync(
    new URL('../../resources/js/components/ui/dialog/DialogContent.vue', import.meta.url),
    'utf8',
);
const dialogOverlaySource = readFileSync(
    new URL('../../resources/js/components/ui/dialog/DialogOverlay.vue', import.meta.url),
    'utf8',
);
const buttonVariantsSource = readFileSync(
    new URL('../../resources/js/components/ui/button/index.ts', import.meta.url),
    'utf8',
);
const selectTriggerSource = readFileSync(
    new URL('../../resources/js/components/ui/select/SelectTrigger.vue', import.meta.url),
    'utf8',
);
const sidebarSource = readFileSync(
    new URL('../../resources/js/components/ui/sidebar/index.ts', import.meta.url),
    'utf8',
);
const appCssSource = readFileSync(
    new URL('../../resources/css/app.css', import.meta.url),
    'utf8',
);

test('mobile amount input falls back to the base field on desktop and uses a bottom keypad on mobile', () => {
    assert.match(
        mobileAmountInputSource,
        /useMediaQuery\('\(max-width: 767px\)'\)/,
    );
    assert.match(mobileAmountInputSource, /<MoneyInput/);
    assert.match(mobileAmountInputSource, /<SheetContent\s+side="bottom"/);
    assert.match(mobileAmountInputSource, /emit\(\s*'update:modelValue'/);
    assert.match(mobileAmountInputSource, /\['1', '2', '3', '÷']/);
    assert.match(mobileAmountInputSource, /\['4', '5', '6', '×']/);
    assert.match(mobileAmountInputSource, /\['7', '8', '9', '-']/);
    assert.match(mobileAmountInputSource, /\[decimal, '0', 'backspace', '\+']/);
    assert.match(mobileAmountInputSource, /appendOperator/);
    assert.match(mobileAmountInputSource, /evaluateDraftExpression/);
    assert.match(mobileAmountInputSource, /toStandardMoneyString/);
    assert.match(mobileAmountInputSource, /planning\.mobileEditor\.clear/);
    assert.match(mobileAmountInputSource, /app\.common\.save/);
    assert.match(mobileAmountInputSource, /editorOpen\?: boolean/);
    assert.match(mobileAmountInputSource, /showTrigger\?: boolean/);
    assert.match(mobileAmountInputSource, /update:editorOpen/);
    assert.match(mobileAmountInputSource, /\{\s*immediate:\s*true\s*}/);
    assert.match(mobileAmountInputSource, /app-touch-interactive/);
    assert.match(mobileAmountInputSource, /class="z-\[180] rounded-t-\[2rem] border-none bg-white/);
    assert.match(mobileAmountInputSource, /dark:bg-\[#161616] dark:text-white/);
});

test('budget planning mobile amount editor reuses the shared mobile amount input instead of a duplicated keypad', () => {
    assert.match(budgetPlanningMobileAmountEditorSource, /MobileAmountInput/);
    assert.match(
        budgetPlanningMobileAmountEditorSource,
        /:show-trigger="false"/,
    );
    assert.match(budgetPlanningMobileAmountEditorSource, /:editor-open="open"/);
    assert.match(
        budgetPlanningMobileAmountEditorSource,
        /@update:editor-open="handleOpenUpdate"/,
    );
});

test('mobile searchable select swaps to a bottom sheet with a searchable text input on mobile', () => {
    assert.match(
        mobileSearchableSelectSource,
        /useMediaQuery\('\(max-width: 767px\)'\)/,
    );
    assert.match(mobileSearchableSelectSource, /<SearchableSelect/);
    assert.match(mobileSearchableSelectSource, /<SheetContent\s+side="bottom"/);
    assert.match(mobileSearchableSelectSource, /<Sheet :open="open"/);
    assert.doesNotMatch(
        mobileSearchableSelectSource,
        /:modal="false"/,
    );
    assert.match(mobileSearchableSelectSource, /<input/);
    assert.match(mobileSearchableSelectSource, /useMobileSheetViewport/);
    assert.match(mobileSearchableSelectSource, /enterkeyhint="search"/);
    assert.match(mobileSearchableSelectSource, /autocapitalize="none"/);
    assert.match(mobileSearchableSelectSource, /SearchableSelectOptionContent/);
    assert.match(mobileSearchableSelectSource, /hierarchical\?: boolean/);
    assert.match(mobileSearchableSelectSource, /optionHasChildren/);
    assert.match(mobileSearchableSelectSource, /currentParentValue/);
    assert.match(mobileSearchableSelectSource, /is_selectable\?: boolean/);
    assert.match(mobileSearchableSelectSource, /resolveInitialParentValue/);
    assert.match(mobileSearchableSelectSource, /app-touch-interactive/);
    assert.match(
        mobileSearchableSelectSource,
        /searchInput\.value\?\.focus\(\)/,
    );
    assert.match(
        mobileSearchableSelectSource,
        /class="z-\[190] max-h-\[85dvh] rounded-t-\[2rem]/,
    );
    assert.match(
        mobileSearchableSelectSource,
        /<div[\s\S]*<button[\s\S]*openOptionChildren\(option\)/,
    );
});

test('searchable select option renderer only changes the visual presentation and can emphasize category paths', () => {
    assert.match(searchableSelectOptionContentSource, /resolveCategoryIcon/);
    assert.match(
        searchableSelectOptionContentSource,
        /pathSegments\.value\.length > 1/,
    );
    assert.match(
        searchableSelectOptionContentSource,
        /props\.option\.fullPath \?\? props\.option\.full_path/,
    );
    assert.match(searchableSelectOptionContentSource, /option\.badgeLabel/);
});

test('desktop searchable select restores the selected hierarchical branch instead of reopening from root', () => {
    assert.match(searchableSelectSource, /resolveInitialParentValue/);
    assert.match(
        searchableSelectSource,
        /currentParentValue\.value = resolveInitialParentValue\(\)/,
    );
    assert.match(searchableSelectSource, /app-touch-interactive/);
});

test('mobile text field editor exposes a dedicated bottom sheet for text entry', () => {
    assert.match(mobileTextFieldEditorSource, /<SheetContent\s+side="bottom"/);
    assert.match(mobileTextFieldEditorSource, /<Sheet[\s\S]*:open="open"/);
    assert.doesNotMatch(mobileTextFieldEditorSource, /:modal="false"/);
    assert.match(mobileTextFieldEditorSource, /<input/);
    assert.match(mobileTextFieldEditorSource, /<textarea/);
    assert.match(mobileTextFieldEditorSource, /useMobileSheetViewport/);
    assert.match(
        mobileTextFieldEditorSource,
        /singleLineInput\.value\?\.focus\(\)/,
    );
    assert.match(
        mobileTextFieldEditorSource,
        /multiLineInput\.value\?\.focus\(\)/,
    );
    assert.match(mobileTextFieldEditorSource, /app\.common\.save/);
    assert.match(mobileTextFieldEditorSource, /app\.common\.cancel/);
    assert.match(
        mobileTextFieldEditorSource,
        /class="z-\[190] rounded-t-\[2rem] px-4 pt-5/,
    );
});

test('shared mobile input primitives prevent iPhone zoom and raise modal layers above fixed chrome', () => {
    assert.match(
        moneyInputSource,
        /touch-manipulation rounded-2xl border bg-white px-3 text-right text-base[\s\S]*sm:text-sm/,
    );
    assert.match(appCssSource, /-webkit-tap-highlight-color: transparent;/);
    assert.match(appCssSource, /\.app-touch-interactive\s*\{/);
    assert.match(appCssSource, /touch-action: manipulation;/);
    assert.match(appCssSource, /-webkit-user-select: none;/);
    assert.match(appCssSource, /-webkit-touch-callout: none;/);
    assert.match(appCssSource, /\.app-touch-selectable\s*\{/);
    assert.match(appCssSource, /@media \(max-width: 767px\)[\s\S]*font-size: 16px;/);
    assert.match(sheetContentSource, /z-\[170]/);
    assert.match(sheetOverlaySource, /z-\[160]/);
    assert.match(dialogContentSource, /z-\[170]/);
    assert.match(dialogOverlaySource, /z-\[160]/);
    assert.match(buttonVariantsSource, /app-touch-interactive inline-flex/);
    assert.match(selectTriggerSource, /app-touch-interactive border-input/);
    assert.match(sidebarSource, /app-touch-interactive peer\/menu-button/);
    assert.match(sheetContentSource, /app-touch-interactive/);
    assert.match(dialogContentSource, /app-touch-interactive/);
});

test('account form keeps long linked payment labels truncated on mobile', () => {
    assert.match(accountFormSource, /function linkedPaymentAccountBankLabel/);
    assert.match(accountFormSource, /function linkedPaymentAccountLabel/);
    assert.match(
        accountFormSource,
        /class="h-11 w-full min-w-0 rounded-2xl border-slate-200 dark:border-slate-800"/,
    );
    assert.match(
        accountFormSource,
        /class="flex min-w-0 flex-1 flex-col text-left"/,
    );
    assert.match(accountFormSource, /selectedLinkedPaymentAccountOption\.name/);
    assert.match(
        accountFormSource,
        /selectedLinkedPaymentAccountOption\.currency/,
    );
    assert.match(
        accountFormSource,
        /class="truncate text-xs text-slate-500 dark:text-slate-400"/,
    );
    assert.match(accountFormSource, /:title="\s*linkedPaymentAccountLabel\(/);
});

test('transactions form opts into the dedicated mobile amount, select and text editors', () => {
    assert.match(transactionsFormSource, /MobileAmountInput/);
    assert.match(transactionsFormSource, /MobileSearchableSelect/);
    assert.match(transactionsFormSource, /MobileTextFieldEditor/);
    assert.match(
        transactionsFormSource,
        /useMediaQuery\('\(max-width: 767px\)'\)/,
    );
    assert.match(transactionsFormSource, /useMobileSheetViewport/);
    assert.match(transactionsFormSource, /:modal="!isMobile"/);
    assert.match(
        transactionsFormSource,
        /:side="isMobile \? 'bottom' : 'right'"/,
    );
    assert.match(transactionsFormSource, /@open-auto-focus\.prevent/);
    assert.match(transactionsFormSource, /@focusin\.capture="handleFocusIn"/);
    assert.match(
        transactionsFormSource,
        /:type="isMobile \? 'text' : 'number'"/,
    );
    assert.match(transactionsFormSource, /inputmode="numeric"/);
    assert.match(
        transactionsFormSource,
        /v-model:open="mobileDescriptionEditorOpen"/,
    );
    assert.match(
        transactionsFormSource,
        /v-model:open="mobileNotesEditorOpen"/,
    );
});

test('recurring form opts into the dedicated mobile amount, select and text editors', () => {
    assert.match(recurringFormSource, /MobileAmountInput/);
    assert.match(recurringFormSource, /MobileSearchableSelect/);
    assert.match(recurringFormSource, /MobileTextFieldEditor/);
    assert.match(
        recurringFormSource,
        /useMediaQuery\('\(max-width: 767px\)'\)/,
    );
    assert.match(recurringFormSource, /useMobileSheetViewport/);
    assert.match(recurringFormSource, /:modal="!isMobile"/);
    assert.match(recurringFormSource, /:side="isMobile \? 'bottom' : 'right'"/);
    assert.match(recurringFormSource, /@open-auto-focus\.prevent/);
    assert.match(recurringFormSource, /@focusin\.capture="handleFocusIn"/);
    assert.match(recurringFormSource, /:type="isMobile \? 'text' : 'number'"/);
    assert.match(recurringFormSource, /inputmode="numeric"/);
    assert.match(
        recurringFormSource,
        /v-model:open="mobileDescriptionEditorOpen"/,
    );
    assert.match(recurringFormSource, /v-model:open="mobileNotesEditorOpen"/);
});

test('account form opts into the shared mobile amount input so settings accounts get the same keypad', () => {
    assert.match(accountFormSource, /MobileAmountInput/);
    assert.match(accountFormSource, /id="opening_balance"/);
    assert.match(accountFormSource, /id="credit_limit"/);
});
