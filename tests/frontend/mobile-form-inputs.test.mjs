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
    assert.match(
        mobileSearchableSelectSource,
        /<Sheet :open="open" :modal="false"/,
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
    assert.match(
        mobileSearchableSelectSource,
        /searchInput\.value\?\.focus\(\)/,
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
});

test('mobile text field editor exposes a dedicated bottom sheet for text entry', () => {
    assert.match(mobileTextFieldEditorSource, /<SheetContent\s+side="bottom"/);
    assert.match(
        mobileTextFieldEditorSource,
        /<Sheet[\s\S]*:open="open"[\s\S]*:modal="false"/,
    );
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
