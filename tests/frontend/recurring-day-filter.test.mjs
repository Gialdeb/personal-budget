import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
import { computed, effectScope, nextTick, reactive, ref, watch } from 'vue';

const source = readFileSync(
    new URL(
        '../../resources/js/pages/transactions/recurring/Index.vue',
        import.meta.url,
    ),
    'utf8',
);

const calendarSource = readFileSync(new URL('../../resources/js/components/recurring/RecurringCalendarGrid.vue', import.meta.url), 'utf8');
const salarySource = readFileSync(new URL('../../resources/js/pages/transactions/salary/Show.vue', import.meta.url), 'utf8');

test('salary month and year navigation clears day and pagination while preserving calendar state', () => {
    const visits = [];
    const navigation = salarySource.slice(salarySource.indexOf('function changePeriod('), salarySource.indexOf('</script>'));
    const script = ts.transpile(navigation, { target: ts.ScriptTarget.ES2022 });
    const changePeriod = new Function('router', 'show', 'props', `${script}; return changePeriod;`)(
        { get: (...args) => visits.push(args) },
        { url: (uuid) => `/salary-employments/${uuid}` },
        { employment: { uuid: 'salary' } },
    );
    changePeriod('2026', '11');
    changePeriod('2027', '2');
    changePeriod('', '2');
    assert.deepEqual(visits, [
        ['/salary-employments/salary', { period: '2026-11' }, { preserveScroll: true, preserveState: true }],
        ['/salary-employments/salary', { period: '2027-02' }, { preserveScroll: true, preserveState: true }],
    ]);
    assert.doesNotMatch(salarySource, /type="month"/);
    assert.match(salarySource, /<RecurringCalendarGrid\s+v-if="!isCalendarCollapsed"/);
    assert.match(salarySource, /aria-controls="salary-calendar"/);
});
function createDayFilter() {
    const state = source.slice(
        source.indexOf('const selectedDate ='),
        source.indexOf('const highlightedEntryUuid ='),
    );
    const groups = source.slice(
        source.indexOf('const filteredGroups ='),
        source.indexOf('onMounted(() =>'),
    );
    const select = source.slice(
        source.indexOf('async function selectCalendarDay('),
        source.indexOf('function entryTypeLabel('),
    );
    const script = ts.transpile(
        `${state}\n${groups}\n${select}\nreturn { selectedDate, filteredListElement, filteredGroups, selectCalendarDay };`,
    );
    const props = reactive({
        activePeriod: { year: 2026, month: 10 },
        monthlyCalendar: {
            days: [
                {
                    date: '2026-10-05',
                    occurrences: [
                        { direction: 'income' },
                        { direction: 'expense' },
                    ],
                },
                { date: '2026-10-12', occurrences: [{ direction: 'expense' }] },
            ],
        },
    });
    const direction = ref('all');
    const scope = effectScope();
    const stateValues = scope.run(() =>
        new Function(
            'ref',
            'computed',
            'watch',
            'nextTick',
            'props',
            'filterOccurrence',
            script,
        )(
            ref,
            computed,
            watch,
            nextTick,
            props,
            (row) =>
                direction.value === 'all' || row.direction === direction.value,
        ),
    );

    return { ...stateValues, props, direction, stop: () => scope.stop() };
}

test('calendar selection toggles and switches days without changing monthly data', () => {
    const state = createDayFilter();
    assert.equal(state.selectedDate.value, null);
    assert.equal(state.filteredGroups.value.length, 2);
    state.selectCalendarDay('2026-10-05');
    assert.deepEqual(
        state.filteredGroups.value.map((day) => day.date),
        ['2026-10-05'],
    );
    state.selectCalendarDay('2026-10-12');
    assert.deepEqual(
        state.filteredGroups.value.map((day) => day.date),
        ['2026-10-12'],
    );
    state.selectCalendarDay('2026-10-12');
    assert.equal(state.filteredGroups.value.length, 2);
    assert.equal(state.props.monthlyCalendar.days[0].occurrences.length, 2);
    state.stop();
});

test('day selection composes with other filters and supports empty days', () => {
    const state = createDayFilter();
    state.direction.value = 'income';
    state.selectCalendarDay('2026-10-05');
    assert.equal(state.filteredGroups.value[0].occurrences.length, 1);
    state.selectCalendarDay('2026-10-12');
    assert.equal(state.filteredGroups.value.length, 0);
    state.direction.value = 'all';
    assert.equal(state.selectedDate.value, '2026-10-12');
    assert.equal(state.filteredGroups.value.length, 1);
    state.selectCalendarDay('2026-10-06');
    assert.equal(state.filteredGroups.value.length, 0);
    state.selectedDate.value = null;
    assert.equal(state.filteredGroups.value.length, 2);
    state.stop();
});

test('period changes clear the day selection without clearing other filters', async () => {
    const state = createDayFilter();
    state.direction.value = 'expense';
    state.selectCalendarDay('2026-10-12');
    state.props.activePeriod = { year: 2026, month: 10 };
    await nextTick();
    assert.equal(state.selectedDate.value, '2026-10-12');
    state.props.activePeriod.month = 11;
    await nextTick();
    assert.equal(state.selectedDate.value, null);
    assert.equal(state.direction.value, 'expense');
    state.selectCalendarDay('2026-11-12');
    state.props.activePeriod.year = 2027;
    await nextTick();
    assert.equal(state.selectedDate.value, null);
    state.stop();
});

test('both calendar views allow empty current-month days and expose selection accessibly', () => {
    assert.equal(
        (calendarSource.match(/@click="emit\('select', cell.date\)"/g) ?? []).length,
        2,
    );
    assert.equal(
        (calendarSource.match(/:disabled="!cell.isCurrentMonth"/g) ?? []).length,
        2,
    );
    assert.equal((calendarSource.match(/:aria-pressed=/g) ?? []).length, 2);
    assert.match(source, /transactions\.recurring\.table\.emptyDay/);
    assert.match(source, /@click="selectedDate = null"/);
    assert.doesNotMatch(source, /jumpToDay|selectedAnchor/);
    const select = source.slice(
        source.indexOf('async function selectCalendarDay('),
        source.indexOf('function entryTypeLabel('),
    );
    assert.match(select, /await nextTick\(\)/);
    assert.doesNotMatch(select, /router|URLSearchParams|localStorage/);
});

test('selection scrolls once after the filtered list updates, while clearing does not scroll', async () => {
    const state = createDayFilter();
    const scrolls = [];
    state.filteredListElement.value = {
        scrollIntoView: (options) =>
            scrolls.push({
                options,
                dates: state.filteredGroups.value.map((day) => day.date),
            }),
    };
    const selection = state.selectCalendarDay('2026-10-05');
    assert.equal(scrolls.length, 0);
    await selection;
    assert.deepEqual(scrolls, [
        {
            options: { behavior: 'smooth', block: 'start' },
            dates: ['2026-10-05'],
        },
    ]);
    await state.selectCalendarDay('2026-10-12');
    assert.equal(scrolls.length, 2);
    assert.deepEqual(scrolls[1].dates, ['2026-10-12']);
    await state.selectCalendarDay('2026-10-12');
    assert.equal(state.selectedDate.value, null);
    assert.equal(scrolls.length, 2);
    state.stop();
});

test('a superseded selection never scrolls towards a removed day', async () => {
    const state = createDayFilter();
    let scrolls = 0;
    state.filteredListElement.value = { scrollIntoView: () => scrolls++ };
    await Promise.all([
        state.selectCalendarDay('2026-10-05'),
        state.selectCalendarDay('2026-10-12'),
    ]);
    assert.equal(scrolls, 1);
    await state.selectCalendarDay('2026-10-12');
    await Promise.all([
        state.selectCalendarDay('2026-10-05'),
        state.selectCalendarDay('2026-10-05'),
    ]);
    assert.equal(scrolls, 1);
    state.stop();
});
