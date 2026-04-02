import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const categoriesPageSource = readFileSync(
    new URL(
        '../../resources/js/pages/settings/Categories.vue',
        import.meta.url,
    ),
    'utf8',
);

const categoryTreeListSource = readFileSync(
    new URL(
        '../../resources/js/components/categories/CategoryTreeList.vue',
        import.meta.url,
    ),
    'utf8',
);

test('categories page keeps a lighter presentation on mobile while preserving the existing sections', () => {
    assert.match(categoriesPageSource, /rounded-\[2rem][\s\S]*sm:rounded-4xl/);
    assert.match(categoriesPageSource, /class="hidden space-y-4 xl:block"/);
    assert.match(
        categoriesPageSource,
        /grid gap-5 xl:grid-cols-\[minmax\(0,1fr\)_320px]/,
    );
});

test('category tree list uses a cleaner card layout with compact metrics and action rows', () => {
    assert.match(categoryTreeListSource, /<Collapsible/);
    assert.match(categoryTreeListSource, /<CollapsibleTrigger/);
    assert.match(categoryTreeListSource, /ChevronDown/);
    assert.match(categoryTreeListSource, /ChevronRight/);
    assert.match(
        categoryTreeListSource,
        /grid grid-cols-2 gap-2 xl:grid-cols-4/,
    );
    assert.match(
        categoryTreeListSource,
        /border-l border-slate-200\/80 pl-2\.5 sm:pl-4 dark:border-slate-800/,
    );
    assert.match(categoryTreeListSource, /categories\.tree\.actions\.expand/);
    assert.match(
        categoryTreeListSource,
        /item\.depth === 0[\s\S]*item\.direction_label[\s\S]*item\.full_path/,
    );
});
