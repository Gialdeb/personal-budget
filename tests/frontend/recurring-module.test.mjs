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

test('recurring index exposes the plan type filter and visible labels for filter selects', () => {
    assert.match(indexSource, /transactions\.recurring\.filters\.entryType/);
    assert.match(indexSource, /transactions\.recurring\.filters\.status/);
    assert.match(indexSource, /transactions\.recurring\.filters\.direction/);
    assert.match(indexSource, /transactions\.recurring\.filters\.conversion/);
    assert.match(indexSource, /transactions\.recurring\.filters\.refund/);
    assert.match(indexSource, /<Label>/);
});

test('recurring index renders a dedicated mobile list component', () => {
    assert.match(indexSource, /RecurringOccurrencesMobileList/);
    assert.match(indexSource, /overflow-x-auto pb-1/);
    assert.match(indexSource, /min-w-\[44rem\] space-y-2 sm:min-w-0/);
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
    assert.match(indexSource, /navigation\.context\.available_years/);
    assert.match(indexSource, /w-\[168px] rounded-full border px-4 text-sm font-medium/);
    assert.match(indexSource, /transactions\.sheet\.alerts\.periodNotCurrent/);
    assert.match(indexSource, /periodNotice/);
    assert.match(indexSource, /flashSuccess/);
    assert.match(indexSource, /return-to-index="true"/);
    assert.match(showSource, /transactions\.recurring\.actions\.backToIndex/);
    assert.match(showSource, /href="\/recurring-entries"/);
    assert.match(showSource, /occurrence\.converted_transaction\.show_url/);
    assert.match(indexSource, /transactions\.recurring\.actions\.openTransaction/);
    assert.match(showSource, /transactions\.recurring\.actions\.openTransaction/);
    assert.doesNotMatch(mobileSource, /occurrence\.converted_transaction\.uuid/);
    assert.match(showSource, /transactions\.recurring\.actions\.undoConversion/);
    assert.match(showSource, /transactions\.recurring\.dialogs\.convertFutureTitle/);
    assert.match(showSource, /transactions\.recurring\.dialogs\.undoConversionTitle/);
    assert.match(showSource, /confirm_future_date:\s*true/);
    assert.match(showSource, /can_undo_conversion/);
    assert.match(indexSource, /occurrence\.converted_transaction\?\.can_refund/);
    assert.match(indexSource, /recurring_entry\?\.status === 'cancelled'/);
});

test('recurring form surfaces required-field validation and enforces end date after start date in the UI', () => {
    assert.match(formSource, /transactions\.recurring\.form\.errors\.accountRequired/);
    assert.match(formSource, /transactions\.recurring\.form\.errors\.categoryRequired/);
    assert.match(formSource, /transactions\.recurring\.form\.errors\.startDateRequired/);
    assert.match(formSource, /transactions\.recurring\.form\.errors\.endDateBeforeStartDate/);
    assert.match(formSource, /form\.setError\('end_date'/);
    assert.match(formSource, /:min="form\.start_date \|\| undefined"/);
    assert.match(formSource, /fieldErrorClass/);
});
