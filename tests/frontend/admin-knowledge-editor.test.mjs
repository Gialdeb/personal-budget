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
const articleIndexSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/KnowledgeBase/Articles/Index.vue',
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
    assert.match(
        articleFormSource,
        /knowledge-article-body-\$\{currentLocale}/,
    );
    assert.doesNotMatch(articleFormSource, /SimpleRichTextEditor/);
});

test('knowledge article admin index uses shared card and muted admin surfaces', () => {
    assert.match(
        articleIndexSource,
        /border-border\/80 bg-card\/95 p-8 text-card-foreground/,
    );
    assert.match(
        articleIndexSource,
        /rounded-\[1\.5rem] border-border\/80 bg-card\/92 shadow-none/,
    );
    assert.match(
        articleIndexSource,
        /border-border\/80 bg-muted\/55 p-4 transition-colors hover:bg-accent\/45/,
    );
    assert.match(articleIndexSource, /text-sm text-muted-foreground/);
});
