import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const supportRequestsIndexSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/SupportRequests/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const supportRequestFiltersSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/support/SupportRequestFilters.vue',
        import.meta.url,
    ),
    'utf8',
);
const supportRequestStatusBadgeSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/support/SupportRequestStatusBadge.vue',
        import.meta.url,
    ),
    'utf8',
);

test('admin support requests page uses shared admin surface tokens', () => {
    assert.match(
        supportRequestsIndexSource,
        /border-border\/80 bg-card\/95 p-8 text-card-foreground/,
    );
    assert.match(
        supportRequestsIndexSource,
        /rounded-2xl border border-border\/80 bg-muted\/70 px-4 py-3 text-sm text-muted-foreground/,
    );
    assert.match(
        supportRequestsIndexSource,
        /rounded-\[1\.5rem] border-border\/80 bg-card\/92 shadow-none/,
    );
    assert.match(
        supportRequestsIndexSource,
        /border-border\/80 bg-muted\/55 p-4 transition-colors hover:bg-accent\/45/,
    );
});

test('admin support request filters use theme tokens for form surfaces', () => {
    assert.match(
        supportRequestFiltersSource,
        /border-border\/80 bg-card\/92 p-4/,
    );
    assert.match(
        supportRequestFiltersSource,
        /border-border bg-background px-4 text-sm text-foreground/,
    );
    assert.match(supportRequestFiltersSource, /focus:border-ring/);
});

test('admin support request badges use semantic tones with dark-mode variants', () => {
    assert.match(
        supportRequestStatusBadgeSource,
        /border-amber-500\/20 bg-amber-500\/10 text-amber-700 dark:border-amber-500\/25 dark:bg-amber-500\/15 dark:text-amber-300/,
    );
    assert.match(
        supportRequestStatusBadgeSource,
        /border-sky-500\/20 bg-sky-500\/10 text-sky-700 dark:border-sky-500\/25 dark:bg-sky-500\/15 dark:text-sky-300/,
    );
    assert.match(
        supportRequestStatusBadgeSource,
        /general_support:\s*'border-border bg-background\/80 text-muted-foreground'/,
    );
});
