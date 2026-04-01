import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const indexSource = readFileSync(
    new URL('../../resources/js/pages/transactions/recurring/Index.vue', import.meta.url),
    'utf8',
);
const formSource = readFileSync(
    new URL('../../resources/js/components/recurring/RecurringEntryFormSheet.vue', import.meta.url),
    'utf8',
);
const mobileSource = readFileSync(
    new URL('../../resources/js/components/recurring/RecurringOccurrencesMobileList.vue', import.meta.url),
    'utf8',
);
const transactionsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/transactions.ts', import.meta.url),
    'utf8',
);

test('recurring index exposes the plan type filter and visible labels for filter selects', () => {
    assert.match(indexSource, /transactions\.recurring\.filters\.account/);
    assert.match(indexSource, /transactions\.recurring\.filters\.entryType/);
    assert.match(indexSource, /transactions\.recurring\.filters\.status/);
    assert.match(indexSource, /transactions\.recurring\.filters\.direction/);
    assert.match(indexSource, /transactions\.recurring\.filters\.conversion/);
    assert.match(indexSource, /transactions\.recurring\.filters\.refund/);
    assert.match(indexSource, /handleAccountSelection/);
    assert.match(indexSource, /filter_accounts/);
    assert.match(indexSource, /<Label>/);
});

test('recurring index renders a dedicated mobile list component', () => {
    assert.match(indexSource, /RecurringOccurrencesMobileList/);
    assert.match(indexSource, /overflow-x-auto pb-1/);
    assert.match(indexSource, /min-w-\[44rem] space-y-2 sm:min-w-0/);
    assert.match(indexSource, /grid grid-cols-7 gap-2 text-center/);
    assert.match(indexSource, /hidden scroll-mt-28.*lg:block/s);
    assert.match(mobileSource, /lg:hidden/);
});

test('recurring form uses public uuids instead of database ids for selected entities', () => {
    assert.match(formSource, /account_uuid/);
    assert.match(formSource, /category_uuid/);
    assert.match(formSource, /tracked_item_uuid/);
    assert.doesNotMatch(formSource, /account_id:\s*Number\(form\.account_id\)/);
    assert.doesNotMatch(formSource, /category_id:\s*Number\(form\.category_id\)/);
    assert.doesNotMatch(formSource, /tracked_item_id:\s*form\.tracked_item_id/);
});

test('recurring pages close the sheet on saved and use the updated convert label', () => {
    const showSource = readFileSync(
        new URL('../../resources/js/pages/transactions/recurring/Show.vue', import.meta.url),
        'utf8',
    );

    assert.match(indexSource, /@saved="formOpen = false"/);
    assert.match(showSource, /@saved="formOpen = false"/);
    assert.match(indexSource, /transactions\.recurring\.actions\.convert/);
    assert.match(showSource, /transactions\.recurring\.actions\.convert/);
    assert.match(indexSource, /yearSelectValue/);
    assert.match(indexSource, /handleYearSelection/);
    assert.match(indexSource, /available_years/);
    assert.match(indexSource, /w-\[168px] rounded-full border px-4 text-sm font-medium/);
    assert.match(indexSource, /transactions\.sheet\.alerts\.periodNotCurrent/);
    assert.match(indexSource, /periodNotice/);
    assert.match(indexSource, /flashSuccess/);
    assert.match(indexSource, /return-to-index="true"/);
    assert.match(indexSource, /:date-options="props\.dateOptions"/);
    assert.match(showSource, /:date-options="props\.dateOptions"/);
    assert.match(showSource, /transactions\.recurring\.actions\.backToIndex/);
    assert.match(showSource, /href="\/recurring-entries"/);
    assert.match(showSource, /show_url/);
    assert.match(indexSource, /transactions\.recurring\.actions\.openTransaction/);
    assert.match(showSource, /transactions\.recurring\.actions\.openTransaction/);
    assert.doesNotMatch(mobileSource, /occurrence\.converted_transaction\.uuid/);
    assert.match(showSource, /transactions\.recurring\.actions\.undoConversion/);
    assert.match(showSource, /transactions\.recurring\.dialogs\.convertFutureTitle/);
    assert.match(showSource, /transactions\.recurring\.dialogs\.undoConversionTitle/);
    assert.match(showSource, /confirm_future_date:\s*true/);
    assert.match(showSource, /can_undo_conversion/);
    assert.match(indexSource, /can_refund/);
    assert.match(indexSource, /cancelled/);
});

test('recurring form surfaces required-field validation and enforces end date after start date in the UI', () => {
    assert.match(formSource, /transactions\.recurring\.form\.errors\.accountRequired/);
    assert.match(formSource, /transactions\.recurring\.form\.errors\.categoryRequired/);
    assert.match(formSource, /transactions\.recurring\.form\.errors\.startDateRequired/);
    assert.match(formSource, /transactions\.recurring\.form\.errors\.endDateBeforeStartDate/);
    assert.match(formSource, /endDateBeforeStartDate/);
    assert.match(formSource, /RecurringEntryDateOptions/);
    assert.match(formSource, /const allowedRecurringYears = computed\(\s*\(\) => props\.dateOptions\?\.available_years \?\? \[],\s*\)/);
    assert.match(formSource, /function isAllowedRecurringDate\(value: string\): boolean/);
    assert.match(formSource, /:min="recurringDateMin \|\| undefined"/);
    assert.match(formSource, /:max="recurringDateMax"/);
    assert.match(formSource, /form\.start_date \|\|\s*recurringDateMin \|\|/s);
    assert.match(formSource, /fieldErrorClass/);
});

test('recurring form filters category scope and tracked item options by the selected account contributors', () => {
    assert.match(formSource, /resolveAccountCategoryContributorUserIds/);
    assert.match(formSource, /resolveAccountScopeContributorUserIds/);
    assert.match(formSource, /resolveAccountTrackedItemContributorUserIds/);
    assert.match(formSource, /categoriesForSelectedAccount/);
    assert.match(formSource, /trackedItemsForSelectedAccount/);
    assert.match(formSource, /category_contributor_user_ids/);
    assert.match(formSource, /scope_contributor_user_ids/);
    assert.match(formSource, /tracked_item_contributor_user_ids/);
    assert.match(formSource, /ensureCategoryMatchesAccountContext/);
    assert.match(formSource, /ensureScopeMatchesAccountContext/);
    assert.match(formSource, /ensureTrackedItemMatchesAccountContext/);
    assert.match(formSource, /trackedItemMatchesContext/);
    assert.match(formSource, /resolveCategoryContextUuids/);
    assert.match(formSource, /watch\(\s*\(\) => form\.category_uuid,\s*\(\) => \{\s*ensureTrackedItemMatchesAccountContext\(\);/s);
});

test('recurring account selectors use visual ownership badges instead of the old plain Mio suffix', () => {
    assert.match(formSource, /transactions\.recurring\.form\.accountBadges\.owner/);
    assert.match(formSource, /transactions\.recurring\.form\.accountBadges\.shared/);
    assert.match(indexSource, /groupedAccountFilterOptions = computed/);
    assert.match(indexSource, /account\.accountTypeCode === 'credit_card'/);
    assert.match(indexSource, /dashboard\.filters\.paymentAccountsGroup/);
    assert.match(indexSource, /dashboard\.filters\.creditCardsGroup/);
    assert.match(indexSource, /SelectGroup/);
    assert.match(indexSource, /SelectLabel/);
    assert.match(indexSource, /selectedAccountFilterOption\.badgeLabel/);
    assert.doesNotMatch(formSource, /Mio/);
});

test('recurring form keeps scope hidden while leaving tracked item reference visible', () => {
    assert.doesNotMatch(formSource, /transactions\.recurring\.form\.labels\.scope/);
    assert.match(formSource, /transactions\.recurring\.form\.labels\.trackedItem/);
});

test('recurring inline reference creation maps backend slug validation errors onto the visible reference field', () => {
    assert.match(formSource, /payload\?\.errors\?\.slug/);
    assert.match(formSource, /form\.setError\(\s*'tracked_item_uuid'/);
    assert.match(formSource, /\/recurring-entries\/tracked-items/);
    assert.match(formSource, /trackedItemCatalog\.value = \{/);
    assert.match(formSource, /form\.tracked_item_uuid = optionValue/);
});

test('recurring create form prefers the exposed default account without overriding edit values', () => {
    assert.match(formSource, /function resolveInitialAccountUuid/);
    assert.match(formSource, /props\.formOptions\.default_account_uuid/);
    assert.match(formSource, /account_uuid: resolveInitialAccountUuid\(\)/);
});

test('custom recurring UX exposes a human-readable live preview and less technical monthly copy', () => {
    assert.match(formSource, /const customRecurrencePreview = computed/);
    assert.match(formSource, /transactions\.recurring\.form\.preview\.title/);
    assert.match(formSource, /transactions\.recurring\.form\.helper\.customPreview/);
    assert.match(formSource, /transactions\.recurring\.form\.helper\.monthlyFixedDay/);
    assert.match(formSource, /transactions\.recurring\.form\.helper\.monthlyOrdinalWeekday/);
    assert.match(formSource, /transactions\.recurring\.form\.helper\.yearlyMonthDay/);
    assert.match(formSource, /transactions\.recurring\.form\.helper\.yearlyOrdinalWeekday/);
    assert.match(formSource, /updateMonthlyMode\(\s*'day_of_month'/);
    assert.match(formSource, /updateMonthlyMode\(\s*'ordinal_weekday'/);
    assert.match(formSource, /weekdayListLabel/);
    assert.match(formSource, /monthLongLabel/);
    assert.match(formSource, /ordinalWeekdayLabel/);
    assert.match(formSource, /ordinalWeekdaySummary/);
    assert.match(formSource, /const safeCode = isOrdinalValue/);
    assert.match(formSource, /const safeWeekday = isWeekdayValue/);
    assert.match(formSource, /function blurCurrentTarget\(event: Event\)/);
    assert.match(formSource, /blurCurrentTarget\(\$event\);/);
    assert.match(transactionsMessagesSource, /Si ripeterà ogni \{interval} \{unit} il giorno \{day}\./);
    assert.match(transactionsMessagesSource, /Si ripeterà \{ordinalWeekday} di ogni \{unit}\./);
    assert.match(transactionsMessagesSource, /It will repeat every \{interval} \{unit} on day \{day}\./);
    assert.match(transactionsMessagesSource, /It will repeat on the \{ordinalWeekday} of every \{unit}\./);
});
