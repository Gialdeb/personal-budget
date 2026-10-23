import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileTemplate, parse } from '@vue/compiler-sfc';

const files = [
    'resources/js/components/recurring/RecurringCalendarGrid.vue',
    'resources/js/pages/transactions/recurring/Show.vue',
];

for (const path of files) {
    test(`${path} compiles and keeps interactive content valid HTML`, () => {
        const source = readFileSync(path, 'utf8');
        const { descriptor, errors } = parse(source);
        assert.deepEqual(errors, []);
        const compiled = compileTemplate({
            source: descriptor.template.content,
            filename: path,
            id: 'markup-test',
            compilerOptions: { expressionPlugins: ['typescript'] },
        });
        assert.deepEqual(compiled.errors, []);
        function check(node, button = false, summary = false) {
            if (node.type !== 1) {
                return;
            }

            if (button || summary) {
                assert.ok(!['div', 'h2', 'p'].includes(node.tag));
            }

            if (summary) {
                assert.ok(!['h2', 'p'].includes(node.tag));
            }

            for (const child of node.children ?? []) {
                check(
                    child,
                    button || node.tag === 'button',
                    summary || node.tag === 'summary',
                );
            }
        }

        for (const node of descriptor.template.ast.children) {
            check(node);
        }

        if (path.includes('CalendarGrid')) {
            assert.equal((source.match(/data-today-dot/g) ?? []).length, 2);
        }
    });
}

test('runtime CSS properties have explicit declarations without overriding supplied values', () => {
    for (const [path, name] of [
        [
            'resources/js/components/recurring/RecurringPriceConditions.vue',
            '--reka-collapsible-content-height',
        ],
        ['resources/js/pages/transactions/Show.vue', '--summary-height'],
    ]) {
        const source = readFileSync(path, 'utf8');
        const property = source.match(
            new RegExp(`@property ${name} \\{([^}]+)}`),
        );
        assert.ok(property);
        assert.match(property[1], /syntax: '\*'/);
        assert.match(property[1], /inherits: true/);
        assert.doesNotMatch(property[1], /initial-value/);
        assert.ok(source.includes(`var(${name}`));
    }
});
