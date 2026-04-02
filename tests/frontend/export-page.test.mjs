import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const exportPageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Export.vue', import.meta.url),
    'utf8',
);

test('export page keeps the flow simple and uses the typed export route for downloads', () => {
    assert.match(exportPageSource, /data-test="settings-export-page"/);
    assert.match(
        exportPageSource,
        /import \{ download, edit } from '@\/routes\/exports'/,
    );
    assert.match(exportPageSource, /t\('export\.steps\.dataset'\)/);
    assert.match(exportPageSource, /t\('export\.steps\.period'\)/);
    assert.match(exportPageSource, /t\('export\.steps\.format'\)/);
    assert.match(exportPageSource, /t\('export\.steps\.summary'\)/);
    assert.match(exportPageSource, /download\.url\(/);
    assert.match(
        exportPageSource,
        /window\.location\.assign\(downloadHref\.value\)/,
    );
    assert.match(exportPageSource, /type="date"/);
    assert.match(exportPageSource, /selectedDataset\.value\.supports_period/);
    assert.match(exportPageSource, /tracked_items:\s+'trackedItems'/);
    assert.match(exportPageSource, /`export\.formats\.\$\{format}\.label`/);
});
