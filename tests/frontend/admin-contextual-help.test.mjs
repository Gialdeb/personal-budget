import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const adminIndexSource = readFileSync(
    new URL('../../resources/js/pages/admin/Index.vue', import.meta.url),
    'utf8',
);
const contextualHelpIndexSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/ContextualHelp/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const contextualHelpFormSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/contextual-help/ContextualHelpEntryForm.vue',
        import.meta.url,
    ),
    'utf8',
);

test('admin overview exposes a clear contextual help entry point', () => {
    assert.match(adminIndexSource, /admin\.overview\.cards\.contextualHelp/);
    assert.match(adminIndexSource, /contextualHelpIndex/);
});

test('contextual help index exposes a create action and edit flow cues', () => {
    assert.match(contextualHelpIndexSource, /Nuova entry/);
    assert.match(
        contextualHelpIndexSource,
        /modificare titolo e body nelle\s+due lingue/,
    );
    assert.match(contextualHelpIndexSource, /page key stabile/);
    assert.match(contextualHelpIndexSource, /contextualHelpCreate/);
    assert.match(contextualHelpIndexSource, /contextualHelpEdit/);
});

test('contextual help index uses shared admin surface tokens instead of bright custom shells', () => {
    assert.match(
        contextualHelpIndexSource,
        /border-border\/80 bg-card\/95 p-8 text-card-foreground/,
    );
    assert.match(
        contextualHelpIndexSource,
        /border-border\/80 bg-card\/92 shadow-none/,
    );
    assert.match(
        contextualHelpIndexSource,
        /bg-muted\/75 px-4 py-3 text-sm text-muted-foreground/,
    );
    assert.match(
        contextualHelpIndexSource,
        /border-border\/80 bg-muted\/55 p-4 transition-colors hover:bg-accent\/45/,
    );
});

test('contextual help admin form exposes the stable settings page keys guidance', () => {
    assert.match(
        contextualHelpFormSource,
        /pageKeyOptions\s*\.map\(\(option\) => option\.key\)\s*\.join\(', '\)/,
    );
    assert.match(contextualHelpFormSource, /Route coperte:/);
});
