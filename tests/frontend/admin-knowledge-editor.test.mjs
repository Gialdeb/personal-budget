import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const articleEditSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/KnowledgeBase/Articles/Edit.vue',
        import.meta.url,
    ),
    'utf8',
);

const articleFormSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/knowledge/KnowledgeArticleForm.vue',
        import.meta.url,
    ),
    'utf8',
);

test('knowledge article admin edit flow mounts the shared rich editor for body content', () => {
    assert.match(articleEditSource, /KnowledgeArticleForm/);
    assert.match(articleFormSource, /RichContentEditor/);
    assert.match(articleFormSource, /translation\(currentLocale\)\.body/);
    assert.match(articleFormSource, /knowledge-article-body-\$\{currentLocale}/);
    assert.doesNotMatch(articleFormSource, /SimpleRichTextEditor/);
});
