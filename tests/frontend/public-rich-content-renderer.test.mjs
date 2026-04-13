import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const sanitizerSource = readFileSync(
    new URL('../../resources/js/lib/public-rich-text.ts', import.meta.url),
    'utf8',
);
const rendererSource = readFileSync(
    new URL(
        '../../resources/js/components/public/editorial/PublicRichContentRenderer.vue',
        import.meta.url,
    ),
    'utf8',
);

test('public rich text sanitizer keeps editorial asset urls and figure markup', () => {
    assert.match(sanitizerSource, /'figure'/);
    assert.match(sanitizerSource, /'figcaption'/);
    assert.match(sanitizerSource, /editorial-assets/);
    assert.match(rendererSource, /\[&_figure]/);
    assert.match(rendererSource, /\[&_figcaption]/);
});
