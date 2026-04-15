import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pageSource = readFileSync(
    new URL(
        '../../resources/js/pages/settings/SharedCategories.vue',
        import.meta.url,
    ),
    'utf8',
);

const layoutSource = readFileSync(
    new URL(
        '../../resources/js/layouts/settings/Layout.vue',
        import.meta.url,
    ),
    'utf8',
);

test('shared categories settings page uses a shared-account selector and a dedicated tree section', () => {
    assert.match(pageSource, /categories\.sharedPage\.selectorLabel/);
    assert.match(pageSource, /v-model="selectedAccountUuid"/);
    assert.match(pageSource, /sharedCategories\.accounts/);
    assert.match(pageSource, /selectedTreeCategories/);
    assert.match(pageSource, /CategoryTreeList/);
    assert.match(pageSource, /:max-parent-depth-for-children="1"/);
});

test('shared categories page keeps read-only access for viewers and empty state for no shared accounts', () => {
    assert.match(pageSource, /:read-only="isReadOnlyAccount"/);
    assert.match(pageSource, /:show-slug="false"/);
    assert.match(pageSource, /:show-slug-field="false"/);
    assert.match(pageSource, /:lock-classification-to-parent="true"/);
    assert.match(pageSource, /categories\.sharedPage\.accountReadOnly/);
    assert.match(pageSource, /categories\.sharedPage\.emptyTitle/);
    assert.match(pageSource, /categories\.sharedPage\.emptyDescription/);
});

test('shared categories page exposes an explicit add-to-shared-account action for personal source categories', () => {
    assert.match(pageSource, /selectedSourceCategoryUuid/);
    assert.match(pageSource, /source_categories/);
    assert.match(pageSource, /materialize-personal/);
    assert.match(pageSource, /SearchableSelect/);
    assert.match(pageSource, /:options="\s*selectedSourceCategories\s*"/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.searchPlaceholder/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.noResults/);
    assert.match(pageSource, /hierarchical/);
    assert.match(pageSource, /selectedImportableSourceCategories/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.label/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.title/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.availableCount/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.empty/);
    assert.match(pageSource, /categories\.sharedPage\.materialize\.action/);
    assert.match(
        pageSource,
        /selectedImportableSourceCategories\.length\s*>\s*0/,
    );
    assert.ok(
        pageSource.includes(
            'xl:grid-cols-[minmax(300px,360px)_minmax(340px,420px)]',
        ),
    );
    assert.match(pageSource, /class="mt-5 flex flex-1 flex-col gap-3"/);
    assert.match(pageSource, /class="mt-auto h-11 w-full rounded-2xl px-4"/);
    assert.doesNotMatch(pageSource, /<Select v-model="selectedSourceCategoryUuid"/);
});

test('settings navigation exposes the dedicated shared categories section', () => {
    assert.match(layoutSource, /settings\.sections\.sharedCategories/);
    assert.match(layoutSource, /editSharedCategories\(\)/);
    assert.match(layoutSource, /settings\.summaries\.sharedCategories/);
    assert.match(layoutSource, /settingsNavigation\?\.has_shared_categories === true/);
});
