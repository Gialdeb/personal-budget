import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../', import.meta.url);

test('monthly operational groups retain child ownership and ordinary filters on desktop and mobile', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Index.vue', root),
        'utf8',
    );
    const expression = source.match(
        /const filteredGroups = computed\(\(\) =>([\s\S]*?)\n\);/,
    )[1];
    const advance = {
        uuid: 'advance',
        recurring_entry: { uuid: 'imu-advance' },
        status: 'pending',
    };
    const ordinary = {
        uuid: 'ordinary',
        recurring_entry: { uuid: 'normal' },
        status: 'paid',
    };
    const balance = {
        uuid: 'balance',
        recurring_entry: { uuid: 'imu-balance' },
        status: 'pending',
    };
    const evaluate = new Function(
        'props',
        'selectedDate',
        'filterOccurrence',
        `return (${expression.trim().replace(/,$/, '')});`,
    );
    const june = {
        monthlyCalendar: {
            days: [{ date: '2026-06-16', occurrences: [advance, ordinary] }],
        },
    };
    const december = {
        monthlyCalendar: {
            days: [{ date: '2026-12-16', occurrences: [balance] }],
        },
    };
    assert.deepEqual(
        evaluate(june, { value: null }, () => true)[0].occurrences,
        [advance, ordinary],
    );
    assert.deepEqual(
        evaluate(december, { value: null }, () => true)[0].occurrences,
        [balance],
    );
    assert.deepEqual(
        evaluate(
            june,
            { value: '2026-06-16' },
            (row) => row.status === 'pending',
        )[0].occurrences,
        [advance],
    );
    assert.deepEqual(
        evaluate(june, { value: '2026-06-17' }, () => true),
        [],
    );
    assert.equal(june.monthlyCalendar.days[0].occurrences[0], advance);
    assert.match(source, /:days="filteredGroups"/);
    assert.match(source, /v-for="day in filteredGroups"/);
    assert.doesNotMatch(source, /standaloneOccurrences/);
});

test('compound forms and aggregated detail reuse routes and components without collective lifecycle or destructive operations', async () => {
    const [form, detail, index, fields, ordinary] = await Promise.all([
        readFile(
            new URL(
                'resources/js/components/recurring/CompoundRecurringFormSheet.vue',
                root,
            ),
            'utf8',
        ),
        readFile(
            new URL('resources/js/pages/transactions/recurring/Show.vue', root),
            'utf8',
        ),
        readFile(
            new URL(
                'resources/js/pages/transactions/recurring/Index.vue',
                root,
            ),
            'utf8',
        ),
        readFile(
            new URL(
                'resources/js/components/recurring/CompoundRecurringPaymentFields.vue',
                root,
            ),
            'utf8',
        ),
        readFile(
            new URL(
                'resources/js/components/recurring/RecurringEntryFormSheet.vue',
                root,
            ),
            'utf8',
        ),
    ]);
    assert.match(form, /mobile \? 'bottom' : 'right'/);
    assert.match(fields, /<AppDatePicker/);
    assert.match(fields, /<MobileAmountInput/);
    assert.match(form, /form\.post/);
    assert.match(ordinary, /storeCompound\.url/);
    assert.match(ordinary, /store\.url/);
    assert.match(form, /add\.url/);
    assert.match(form, /:disabled="form\.processing"/);
    assert.match(detail, /v-for="component in compound\.components"/);
    assert.match(detail, /v-for="occurrence in yearRows/);
    assert.match(detail, /occurrenceOwner/);
    assert.doesNotMatch(index, /visibleCompounds|v-for="compound in/);
    assert.match(index, /v-if="flatFilteredOccurrences.length === 0"/);
    const childDetail = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    assert.match(childDetail, /:href="component.url"/);
    assert.match(
        index,
        /occurrences: day\.occurrences\.filter\(filterOccurrence\)/,
    );
    assert.doesNotMatch(
        index,
        /compoundFormOpen|CompoundRecurringFormSheet|compound\.create/,
    );
    assert.match(ordinary, /v-if="canCreateCompound"/);
    assert.match(ordinary, /role="switch"/);
    assert.match(ordinary, /:aria-checked="compoundMode"/);
    assert.match(ordinary, /setCompoundMode/);
    assert.match(ordinary, /<CompoundRecurringPaymentFields/);
});

test('compound shortcuts only open authorized editors for the exact highlighted occurrence', async () => {
    const { requestedOccurrenceAction } =
        await import('../../resources/js/lib/compound-recurring.js');
    const occurrence = {
        uuid: 'due',
        can_update_occurrence: true,
        can_update_amount: true,
        can_register_payment: false,
    };
    const url = '/recurring-entries/child?highlight=due&occurrence_action=';
    assert.equal(
        requestedOccurrenceAction(url + 'date', true, occurrence),
        'date',
    );
    assert.equal(
        requestedOccurrenceAction(url + 'amount', true, occurrence),
        'amount',
    );
    assert.equal(
        requestedOccurrenceAction(url + 'payment', true, occurrence),
        null,
    );
    assert.equal(
        requestedOccurrenceAction(url + 'payment', true, {
            ...occurrence,
            can_register_payment: true,
        }),
        'payment',
    );
    assert.equal(
        requestedOccurrenceAction(url + 'date', false, occurrence),
        null,
    );
    assert.equal(
        requestedOccurrenceAction(url + 'date', true, {
            ...occurrence,
            uuid: 'other',
        }),
        null,
    );
    assert.equal(
        requestedOccurrenceAction(url + 'convert', true, occurrence),
        null,
    );
    assert.equal(requestedOccurrenceAction(url + 'date', true, null), null);
});

test('ordinary and compound forms share the same reminder configuration component', async () => {
    for (const file of [
        'RecurringEntryFormSheet.vue',
        'CompoundRecurringFormSheet.vue',
    ]) {
        const source = await readFile(
            new URL('resources/js/components/recurring/' + file, root),
            'utf8',
        );
        assert.match(source, /<RecurringReminderFields/);
        assert.match(source, /v-model="form.reminder_days_before"/);
        assert.doesNotMatch(source, /function toggleReminderDay/);
    }
});

test('shared reminder fields render the configured selection and disable only unselected offsets at the limit', async () => {
    const { default: vue } = await import('@vitejs/plugin-vue');
    const { createServer } = await import('vite');
    const { createSSRApp, h } = await import('vue');
    const { renderToString } = await import('vue/server-renderer');
    const { createI18n } = await import('vue-i18n');
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': new URL('resources/js', root).pathname } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    try {
        const { default: Fields } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringReminderFields.vue',
        );

        for (const selection of [[], [1, 3, 7]]) {
            const app = createSSRApp({
                render: () =>
                    h(Fields, {
                        modelValue: selection,
                        options: { max_days_before: 15, max_custom_alerts: 3 },
                    }),
            });
            app.use(
                createI18n({
                    legacy: false,
                    locale: 'it',
                    missingWarn: false,
                    fallbackWarn: false,
                    messages: { it: {} },
                }),
            );
            const html = await renderToString(app);
            assert.equal(
                (html.match(/aria-pressed="true"/g) ?? []).length,
                selection.length,
            );
            assert.equal(
                (html.match(/ disabled(?:=""|(?=[ >]))/g) ?? []).length,
                selection.length === 3 ? 12 : 0,
            );
        }
    } finally {
        await server.close();
    }
});

test('the compound option is available only for creation of ordinary yearly recurrences and resets when frequency changes', async () => {
    const { canCreateCompoundRecurring } =
        await import('../../resources/js/lib/compound-recurring.js');
    const config = {
        editing: false,
        reactivation: false,
        salary: false,
        planType: 'recurring',
        recurrenceType: 'yearly',
        interval: 1,
    };
    assert.equal(canCreateCompoundRecurring(config), true);

    for (const change of [
        { editing: true },
        { reactivation: true },
        { salary: true },
        { planType: 'installment' },
        { recurrenceType: 'monthly' },
        { interval: 2 },
    ]) {
        assert.equal(
            canCreateCompoundRecurring({ ...config, ...change }),
            false,
        );
    }
});

test('compound creation sends existing shared fields and independent payments without ordinary financial settings', async () => {
    const { compoundRecurringCreationPayload } =
        await import('../../resources/js/lib/compound-recurring.js');
    const data = {
        description: ' IMU ',
        account_uuid: 'account',
        category_uuid: 'tax',
        direction: 'expense',
        reminder_days_before: [3, 7],
        expected_amount: '999',
        start_date: '2026-01-01',
        auto_create_transaction: true,
        is_shared_expense: true,
    };
    const components = [
        { name: 'Acconto', date: '2027-06-16', amount: '300' },
        { name: 'Saldo', date: '2027-12-16', amount: '350' },
    ];
    const payload = compoundRecurringCreationPayload(data, components);
    assert.deepEqual(payload, {
        name: 'IMU',
        account_uuid: 'account',
        category_uuid: 'tax',
        direction: 'expense',
        reminder_days_before: [3, 7],
        components,
    });
    assert.notEqual(payload.components, components);
    assert.notEqual(payload.reminder_days_before, data.reminder_days_before);
    assert.equal(data.auto_create_transaction, true);
    assert.equal(data.expected_amount, '999');
});

test('the first payment inherits ordinary date and amount while other drafts and round trips retain values', async () => {
    const { withFirstCompoundPayment, compoundRecurringCreationPayload } =
        await import('../../resources/js/lib/compound-recurring.js');
    const drafts = [
        { name: 'Acconto', date: '2026-10-20', amount: '' },
        { name: 'Saldo', date: '2026-12-16', amount: '80' },
    ];
    const first = withFirstCompoundPayment(drafts, '2026-06-16', '50.25');
    assert.deepEqual(first[0], {
        name: 'Acconto',
        date: '2026-06-16',
        amount: '50.25',
    });
    assert.deepEqual(first[1], drafts[1]);
    first[0].date = '2026-05-16';
    first[0].amount = '60';
    const ordinary = {
        description: 'IMU',
        account_uuid: 'a',
        category_uuid: 'c',
        direction: 'expense',
        reminder_days_before: [3],
        start_date: first[0].date,
        expected_amount: first[0].amount,
    };
    const restored = withFirstCompoundPayment(
        first,
        ordinary.start_date,
        ordinary.expected_amount,
    );
    const payload = compoundRecurringCreationPayload(ordinary, restored);
    assert.equal(payload.components[0].date, '2026-05-16');
    assert.equal(payload.components[0].amount, '60');
    assert.deepEqual(payload.components[1], drafts[1]);
    assert.equal(drafts[0].date, '2026-10-20');
});

test('payment date controls allow retrospective values and expose errors beside their accessible fields', async () => {
    const fields = await readFile(
        new URL(
            'resources/js/components/recurring/CompoundRecurringPaymentFields.vue',
            root,
        ),
        'utf8',
    );
    const form = await readFile(
        new URL(
            'resources/js/components/recurring/RecurringEntryFormSheet.vue',
            root,
        ),
        'utf8',
    );
    assert.doesNotMatch(fields, /:min="today"/);
    assert.match(fields, /:min="minDate"/);
    assert.match(fields, /components\.\$\{index\}\.date/);
    assert.match(fields, /:aria-describedby/);
    assert.match(form, /<Transition name="compound-fields">/);
    assert.match(form, /prefers-reduced-motion: reduce/);
});

test('ordinary Show routes occurrence actions to each real owner and fails closed for unknown compound rows', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const body = source.match(
        /function occurrenceOwner\(uuid: string\): string \{([\s\S]*?)\n\}/,
    )[1];
    const resolve = new Function('uuid', 'yearRows', 'props', body);
    const rows = {
        value: {
            2026: [
                { uuid: 'advance', entry_uuid: 'advance-owner' },
                { uuid: 'balance', entry_uuid: 'balance-owner' },
            ],
        },
    };
    const props = {
        compound: { uuid: 'parent' },
        recurringEntry: { entry: { uuid: 'advance-owner' } },
    };
    assert.equal(resolve('advance', rows, props), 'advance-owner');
    assert.equal(resolve('balance', rows, props), 'balance-owner');
    assert.throws(
        () => resolve('missing', rows, props),
        /Missing occurrence owner/,
    );
    assert.equal(
        resolve('ordinary', { value: {} }, { ...props, compound: null }),
        'advance-owner',
    );

    for (const action of [
        'convertOccurrence',
        'updateAmount',
        'updateOccurrence',
        'undoConversion',
        'allocatePayment',
        'paymentCandidates',
    ]) {
        assert.doesNotMatch(
            source,
            new RegExp(`${action}\\.url\\(\\[\\s*entry\\.value\\.uuid`),
        );
    }

    assert.match(source, /action\.url\(\[occurrenceOwner\(occurrence\.uuid\)/);
    assert.match(
        source,
        /props\.compound\?\.uuid \?\? props\.recurringEntry\.entry\.uuid/,
    );
});

test('monthly child occurrences do not expose independent plan deletion on desktop or mobile', async () => {
    for (const path of [
        'resources/js/pages/transactions/recurring/Index.vue',
        'resources/js/components/recurring/RecurringOccurrencesMobileList.vue',
    ]) {
        const source = await readFile(new URL(path, root), 'utf8');
        assert.ok(
            source.replace(/\s+/g, '').includes('recurring_entry?.can_delete'),
            path,
        );
    }
});

test('cancelled history controls reload only the selected year and retain the other year selections', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const body = source.match(
        /async function toggleCancelled\(year: number\): Promise<void> \{([\s\S]*?)\n\}/,
    )[1];
    const selections = { value: { 2026: false, 2027: true } };
    const requests = [];
    const toggle = new Function(
        'showCancelledYears',
        'loadYear',
        `return async function(year) {${body}}`,
    )(selections, async (...args) => requests.push(args));
    await toggle(2026);
    assert.deepEqual(selections.value, { 2026: true, 2027: true });
    await toggle(2026);
    assert.deepEqual(selections.value, { 2026: false, 2027: true });
    assert.deepEqual(requests, [
        [2026, 0, true],
        [2026, 0, true],
    ]);
    assert.match(source, /show_cancelled: includeCancelled \? 1 : 0/);
    assert.match(source, /chunk.show_cancelled \?\? false/);
    assert.match(source, /v-if="group.cancelled_count"/);
    assert.match(source, /v-if="compound.archived_components.length"/);
    assert.match(source, /archiveComponentForm.patch\(/);
    assert.match(source, /archiveComponent.url\(\[/);
    assert.match(
        source,
        /onSuccess: \(\) => \{\s*archiveComponentOpen.value = false;/,
    );
});

test('ordinary editor uses selected component protections instead of aggregate payments and keeps removal last', async () => {
    const form = await readFile(
        new URL(
            'resources/js/components/recurring/RecurringEntryFormSheet.vue',
            root,
        ),
        'utf8',
    );
    const expression = form
        .match(
            /const structuralLocked = computed\(\s*\(\) =>([\s\S]*?)\n\);/,
        )[1]
        .trim()
        .replace(/,$/, '');
    const locked = new Function('props', `return (${expression});`);
    const aggregatePaid = { stats: { converted_occurrences: 1 } };
    assert.equal(
        locked({ entry: { ...aggregatePaid, component_edit_locked: false } }),
        false,
    );
    assert.equal(
        locked({ entry: { ...aggregatePaid, component_edit_locked: true } }),
        true,
    );
    assert.equal(
        locked({
            entry: {
                ...aggregatePaid,
                component_edit_locked: false,
                has_activity_periods: true,
            },
        }),
        true,
    );
    assert.equal(locked({ entry: aggregatePaid }), true);
    assert.equal(
        locked({ entry: { stats: { converted_occurrences: 0 } } }),
        false,
    );
    assert.match(form, /entry.component_name \?\?/);
    assert.match(
        form,
        /:disabled="\s*structuralLocked\s*\|\|\s*!!props.entry\?\.is_compound_component\s*"/,
    );
    const detail = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const header = detail.match(
        /<div[^>]*data-compound-plan-actions[^>]*>([\s\S]*?)<\/div>/,
    )[1];
    assert.doesNotMatch(
        header,
        /runComponentAction|archiveComponentOpen|cancelDialogOpen|interrupt.url/,
    );
    const componentRows = detail.slice(
        detail.indexOf('aria-labelledby="annual-components-title"'),
    );
    assert.ok(
        componentRows.indexOf("'remove'") > componentRows.indexOf("'cancel'"),
    );
});

test('component row actions select the exact owner before opening existing editors or confirmations', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const ts = (await import('typescript')).default;
    const actionSource = source.slice(
        source.indexOf('function runComponentAction('),
        source.indexOf('const focusedOccurrenceUuid'),
    );
    const entry = { value: { uuid: 'acconto', can_edit: true } };
    const props = {
        compound: { is_archived: false },
        recurringEntry: { actions: {} },
    };
    const pending = { value: null };
    const formOpen = { value: false };
    const reactivationMode = { value: true };
    const archiveComponentOpen = { value: false };
    const visits = [];
    const router = { visit: (...args) => visits.push(args) };
    const run = new Function(
        'entry',
        'props',
        'componentActionPending',
        'router',
        'formOpen',
        'reactivationMode',
        'archiveComponentOpen',
        ts.transpile(actionSource) + '; return runComponentAction;',
    )(
        entry,
        props,
        pending,
        router,
        formOpen,
        reactivationMode,
        archiveComponentOpen,
    );
    const target = {
        entry_uuid: 'test',
        url: '/existing-detail?component=test',
    };
    run(target, 'edit');
    assert.equal(visits[0][0], target.url);
    assert.equal(formOpen.value, false);
    visits[0][1].onSuccess();
    assert.equal(
        formOpen.value,
        false,
        'an unexpected owner must not open an editor',
    );
    entry.value.uuid = 'test';
    visits[0][1].onSuccess();
    assert.equal(formOpen.value, true);
    assert.equal(reactivationMode.value, false);
    visits[0][1].onFinish();
    assert.equal(pending.value, null);
    run(target, 'remove');
    assert.equal(archiveComponentOpen.value, true);
    archiveComponentOpen.value = false;
    entry.value.can_edit = false;
    run(target, 'remove');
    assert.equal(archiveComponentOpen.value, false);
});

test('annual component rows compile and keep ordinary occurrence history outside their actions', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const { compileTemplate } = await import('@vue/compiler-sfc');
    const start = source.lastIndexOf(
        '<details',
        source.indexOf('aria-labelledby="annual-components-title"'),
    );
    const next = source.indexOf(
        'v-if="recurringEntry.activity_periods?.length"',
        start,
    );
    const end = source.lastIndexOf('<details', next);
    const rows = source.slice(start, end);
    assert.deepEqual(
        compileTemplate({
            source: rows,
            filename: 'annual-components.vue',
            id: 'annual-components',
        }).errors,
        [],
    );

    for (const action of [
        'edit',
        'interrupt',
        'reactivate',
        'pause',
        'recover',
        'cancel',
        'remove',
    ]) {
        assert.match(
            rows,
            new RegExp(
                `runComponentAction\\(\\s*component,\\s*'${action}',?\\s*\\)`,
            ),
        );
    }

    assert.match(rows, /annualComponentDate/);
    assert.match(rows, /formatMoney/);
    assert.match(rows, /componentStatusLabel/);
    assert.doesNotMatch(
        rows,
        /convertOccurrence|allocatePayment|undoConversion/,
    );
    assert.match(source, /fetch\(component.url, \{/);
    assert.match(
        source,
        /payload.recurringEntry.entry.uuid === component.entry_uuid/,
    );
});

test('component and lifecycle panels collapse accessibly and explicit selection opens the matching history', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    assert.match(source, /const componentsOpen = ref\(true\)/);
    assert.match(source, /const lifecycleOpen = ref\(false\)/);
    assert.match(source, /:open="componentsOpen"/);
    assert.match(source, /:open="!compound \|\| lifecycleOpen"/);
    assert.match(source, /@success="lifecycleOpen = true"/);
    assert.match(source, /preserve-scroll\s+preserve-state/);
    assert.match(
        source,
        /:aria-current="[\s\S]*?entry.uuid === component.entry_uuid\s*\? 'page'/,
    );
    assert.match(source, /v-if="entry.uuid === component.entry_uuid"/);
    assert.match(source, /role="status"\s+aria-live="polite"/);
    assert.match(
        source,
        /viewingComponent[\s\S]*?name: compound.active_component/,
    );
    const { compileTemplate } = await import('@vue/compiler-sfc');
    const marker = source.indexOf(
        'v-if="recurringEntry.activity_periods?.length"',
    );
    const start = source.lastIndexOf('<details', marker);
    const end = source.indexOf('</details>', marker) + '</details>'.length;
    assert.deepEqual(
        compileTemplate({
            source: source.slice(start, end),
            filename: 'component-lifecycle.vue',
            id: 'component-lifecycle',
        }).errors,
        [],
    );
    assert.match(source.slice(start, end), /<summary/);
});

test('compound annual progress counts fully paid current-year occurrences independently of component and history filters', async () => {
    const ts = (await import('typescript')).default;
    const source = await readFile(
        new URL('resources/js/lib/recurring-plan-progress.ts', root),
        'utf8',
    );
    const helper = new Function(
        'exports',
        ts.transpile(source, {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        }) + '; return exports.annualOccurrencesProgress;',
    )({});
    const paid = {
        uuid: 'acconto',
        expected_date: '2026-06-16',
        due_date: null,
        status: 'completed',
        settlement_status: 'settled',
    };
    const pending = {
        ...paid,
        uuid: 'saldo',
        expected_date: '2026-12-16',
        status: 'pending',
        settlement_status: null,
    };
    const rows = [
        paid,
        pending,
        paid,
        { ...pending, uuid: 'old-saldo', status: 'cancelled' },
        { ...paid, uuid: 'previous', expected_date: '2025-06-16' },
        { ...paid, uuid: 'future', expected_date: '2027-06-16' },
    ];
    assert.deepEqual(helper(rows, 2026), {
        paid: 1,
        total: 2,
        value: '1 / 2',
        percentage: 50,
    });
    assert.deepEqual(
        helper(
            [...rows, { ...pending, uuid: 'skipped', status: 'skipped' }],
            2026,
        ),
        { paid: 1, total: 3, value: '1 / 3', percentage: (1 / 3) * 100 },
    );
    const partial = {
        ...pending,
        uuid: 'partial',
        settlement_status: 'partial',
    };
    assert.equal(helper([...rows, partial], 2026).paid, 1);
    assert.equal(helper([...rows, partial], 2026).total, 3);
    assert.equal(
        helper(
            [{ ...paid, expected_date: '2025-12-31', due_date: '2026-01-02' }],
            2026,
        ).paid,
        1,
    );
    assert.deepEqual(helper([], 2026), {
        paid: 0,
        total: 0,
        value: '0 / 0',
        percentage: null,
    });
});

test('annual progress loads a complete year without changing occurrence history or component selection', async () => {
    const source = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    const ts = (await import('typescript')).default;
    const start = source.indexOf(
        'watch(',
        source.indexOf('const annualProgress ='),
    );
    const end = source.indexOf('const progressSettled', start);
    let callback;
    const rows = { value: null };
    const failed = { value: false };
    const requests = [];
    const chunks = [
        { occurrences: [{ uuid: 'first' }], next_offset: 12 },
        { occurrences: [{ uuid: 'last' }], next_offset: null },
    ];
    new Function(
        'watch',
        'props',
        'annualProgressRows',
        'annualProgressFailed',
        'currentProgressYear',
        'fetchYear',
        ts.transpile(source.slice(start, end)),
    )(
        (getter, handler) => {
            callback = handler;
        },
        { compound: {} },
        rows,
        failed,
        { value: 2026 },
        async (year, offset) => {
            requests.push([year, offset]);

            return chunks.shift();
        },
    );
    const payload = {
        occurrence_page: { year: 2025, offset: 12, next_offset: null },
        occurrences: [{ uuid: 'other-year' }],
    };
    const before = JSON.stringify(payload);
    await callback(payload, null, () => {});
    assert.deepEqual(requests, [
        [2026, 0],
        [2026, 12],
    ]);
    assert.deepEqual(
        rows.value.map((row) => row.uuid),
        ['first', 'last'],
    );
    assert.equal(JSON.stringify(payload), before);
    requests.length = 0;
    const complete = [{ uuid: 'complete-year' }];
    await callback(
        {
            occurrence_page: { year: 2026, offset: 0, next_offset: null },
            occurrences: complete,
        },
        null,
        () => {},
    );
    assert.equal(rows.value, complete);
    assert.deepEqual(requests, []);
    assert.match(
        source,
        /props.compound\s*\? t\('recurringDetail.annualOccurrences'\)/,
    );
});
