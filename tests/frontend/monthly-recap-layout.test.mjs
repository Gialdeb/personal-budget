import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const monthlyRecapSource = readFileSync(
    new URL(
        '../../resources/js/pages/dashboard/MonthlyRecap.vue',
        import.meta.url,
    ),
    'utf8',
);

test('monthly recap uses a dark page background in dark mode', () => {
    assert.match(monthlyRecapSource, /dark:bg-\[#080806]/);
    assert.doesNotMatch(
        monthlyRecapSource,
        /<main\s+class="[^"]*\bbg-white\b[^"]*"/,
    );
});

test('monthly recap report grows progressively on larger screens', () => {
    assert.match(monthlyRecapSource, /max-w-\[960px]/);
    assert.match(monthlyRecapSource, /xl:max-w-\[1120px]/);
    assert.match(
        monthlyRecapSource,
        /2xl:max-w-\[min\(1520px,calc\(100vw-5rem\)\)]/,
    );
    assert.match(monthlyRecapSource, /px-5/);
    assert.match(monthlyRecapSource, /sm:px-8/);
    assert.match(monthlyRecapSource, /2xl:px-24/);
});
