import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse, compileTemplate } from '@vue/compiler-sfc';
import { groupQuickActions } from '../../resources/js/lib/quick-action-groups.js';
import { templateReferenceMatches } from '../../resources/js/lib/transaction-template-references.js';
import { transactionTemplateSplit } from '../../resources/js/lib/transaction-template-split.js';

test('quick action categories use structural IDs and retain every action in the selected category', () => {
    const category = { uuid: 'food', name: 'Alimentari', icon: 'beef', color: '#047857' };
    const actions = [
        { uuid: 'deco', name: 'Decò', category, reference: { name: 'Decò', logo_url: '/logo.png' } },
        { uuid: 'sole', name: 'Sole365', category, shared_enabled: true },
        { uuid: 'fuel', name: 'Q8', category: { ...category, uuid: 'transport', name: 'Trasporti' } },
    ];
    const groups = groupQuickActions(actions, 'Senza categoria');
    assert.equal(groups.length, 2);
    assert.equal(groups[0].icon, 'beef');
    assert.equal(groups[0].color, '#047857');
    assert.deepEqual(groups[0].templates.map(item => item.uuid), ['deco', 'sole']);
    assert.equal(groups[0].templates[0].reference.logo_url, '/logo.png');
    assert.equal(groups[0].templates[1].shared_enabled, true);
    assert.deepEqual(groupQuickActions([], 'Senza categoria'), []);
    assert.equal(groupQuickActions([{ uuid: 'missing', category: null }], 'Senza categoria')[0].name, 'Senza categoria');
});

test('quick actions are in the header after notifications on mobile and desktop with no floating duplicate', () => {
    const header = readFileSync(new URL('../../resources/js/components/AppSidebarHeader.vue', import.meta.url), 'utf8');
    const menus = [...header.matchAll(/<QuickActionsMenu/g)];
    assert.equal(menus.length, 2);

    for (const menu of menus) {
        const bell = header.lastIndexOf('<Bell ', menu.index);
        const user = header.indexOf('app.shell.userMenu.open', menu.index);
        assert.ok(bell >= 0 && user > menu.index);
        assert.ok(!header.slice(bell, menu.index).includes('app.shell.userMenu.open'));
    }

    assert.doesNotMatch(header, /flex justify-end md:hidden/);
    const menu = readFileSync(new URL('../../resources/js/components/transactions/QuickActionsMenu.vue', import.meta.url), 'utf8');
    assert.match(menu, /v-for="item in selectedGroup.templates"/);
    assert.doesNotMatch(menu, /v-for="item in templates"/);
    assert.match(menu, /appearance="category"/);
    assert.match(menu, /item.reference.logo_url/);
    assert.match(menu, /item.shared_enabled/);
});

test('template references follow category ancestors and direction without hiding unrestricted references', () => {
    assert.equal(templateReferenceMatches({ category_uuids: ['food'] }, ['groceries', 'food'], 'expense'), true);
    assert.equal(templateReferenceMatches({ category_uuids: ['transport'] }, ['groceries', 'food'], 'expense'), false);
    assert.equal(templateReferenceMatches({ category_uuids: ['food'], group_keys: ['income'] }, ['food'], 'expense'), false);
    assert.equal(templateReferenceMatches({ group_keys: ['expense'] }, [], 'expense'), true);
    assert.equal(templateReferenceMatches({}, [], 'expense'), true);
});

test('template percentage recalculates from actual amounts and preserves every cent', () => {
    assert.deepEqual(transactionTemplateSplit('100', 3000), { myShare: '30.00', counterpartyShare: '70.00' });
    assert.deepEqual(transactionTemplateSplit('200', 3000), { myShare: '60.00', counterpartyShare: '140.00' });
    assert.deepEqual(transactionTemplateSplit('10,01', 3000), { myShare: '3.00', counterpartyShare: '7.01' });
    assert.deepEqual(transactionTemplateSplit('100', 0), { myShare: '0.00', counterpartyShare: '100.00' });
    assert.deepEqual(transactionTemplateSplit('100', 10000), { myShare: '100.00', counterpartyShare: '0.00' });
    assert.equal(transactionTemplateSplit('', 3000), null);
    assert.equal(transactionTemplateSplit('abc', 3000), null);
    assert.equal(transactionTemplateSplit('100', 10001), null);
});

for (const file of ['components/transactions/QuickActionsMenu.vue', 'pages/settings/QuickActions.vue', 'components/transactions/TransactionFormSheet.vue']) {
    test(`${file} template compiles`, () => {
        const filename = new URL(`../../resources/js/${file}`, import.meta.url).pathname;
        const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename });
        assert.deepEqual(errors, []);
        const template = compileTemplate({ source: descriptor.template.content, filename, id: file });
        assert.deepEqual(template.errors, []);
    });
}

test('quick action settings keep mobile card actions separate from readable titles and scroll the editor body', () => {
    const source = readFileSync(new URL('../../resources/js/pages/settings/QuickActions.vue', import.meta.url), 'utf8');
    assert.match(source, /grid-cols-\[2rem_2\.5rem_minmax\(0,1fr\)\]/);
    assert.match(source, /col-span-3 flex w-full/);
    assert.match(source, /h-\[92dvh\] min-w-0/);
    assert.match(source, /min-h-0 min-w-0 flex-1[^\n]+overflow-y-auto/);
    assert.match(source, /SheetHeader class="shrink-0/);
});

test('quick actions open the existing form and never post transactions from their menu', () => {
    const menu = readFileSync(new URL('../../resources/js/components/transactions/QuickActionsMenu.vue', import.meta.url), 'utf8');
    assert.match(menu, /create: 1/);
    assert.match(menu, /template: uuid/);
    assert.doesNotMatch(menu, /\.post\(/);
    const form = readFileSync(new URL('../../resources/js/components/transactions/TransactionFormSheet.vue', import.meta.url), 'utf8');
    assert.match(form, /transaction_template_uuid/);
    assert.match(form, /storeTransaction.url/);
    assert.match(form, /form.amount = template.default_amount \?\? ''/);
    assert.match(form, /unresolvedTemplateIssues/);
});
