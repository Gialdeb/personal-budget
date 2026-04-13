import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSidebarHeaderSource = readFileSync(
    new URL('../../resources/js/components/AppSidebarHeader.vue', import.meta.url),
    'utf8',
);

const contextualHelpButtonSource = readFileSync(
    new URL('../../resources/js/components/contextual-help/ContextualHelpButton.vue', import.meta.url),
    'utf8',
);

test('app shell mounts the contextual help button in the authenticated header', () => {
    assert.match(appSidebarHeaderSource, /ContextualHelpButton/);
    assert.match(appSidebarHeaderSource, /<ContextualHelpButton \/>/);
});

test('contextual help button renders the shared rich content renderer inside a sheet', () => {
    assert.match(contextualHelpButtonSource, /PublicRichContentRenderer/);
    assert.match(contextualHelpButtonSource, /SheetContent/);
    assert.match(contextualHelpButtonSource, /page\.props\.contextualHelp/);
    assert.match(contextualHelpButtonSource, /knowledge_article/);
    assert.match(contextualHelpButtonSource, /:content="contextualHelp\.body \?\? '<p><\/p>'"/);
    assert.doesNotMatch(contextualHelpButtonSource, />\s*Guida contestuale\s*</);
    assert.doesNotMatch(
        contextualHelpButtonSource,
        /Una guida rapida pensata per la pagina che stai usando in questo momento\./,
    );
});
