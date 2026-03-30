import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appLogoSource = readFileSync(
    new URL('../../resources/js/components/AppLogo.vue', import.meta.url),
    'utf8',
);
const appLogoIconSource = readFileSync(
    new URL('../../resources/js/components/AppLogoIcon.vue', import.meta.url),
    'utf8',
);
const appHeaderSource = readFileSync(
    new URL('../../resources/js/components/AppHeader.vue', import.meta.url),
    'utf8',
);
const appShellFooterSource = readFileSync(
    new URL(
        '../../resources/js/components/AppShellFooter.vue',
        import.meta.url,
    ),
    'utf8',
);
const faviconSource = readFileSync(
    new URL('../../public/favicon.svg', import.meta.url),
    'utf8',
);

test('shared brand components use the localized app name and tagline', () => {
    assert.match(appLogoSource, /t\('app\.name'\)/);
    assert.match(appLogoSource, /t\('app\.brand\.tagline'\)/);
    assert.match(appHeaderSource, /t\('app\.name'\)/);
    assert.match(appShellFooterSource, /t\('app\.name'\)/);
});

test('brand icon uses the new ledger and growth mark', () => {
    assert.match(
        appLogoIconSource,
        /<rect\s+x="12"\s+y="24"\s+width="5"\s+height="13"/,
    );
    assert.match(
        appLogoIconSource,
        /<rect\s+x="21\.5"\s+y="17"\s+width="5"\s+height="20"/,
    );
    assert.match(
        appLogoIconSource,
        /<rect\s+x="31"\s+y="11"\s+width="5"\s+height="26"/,
    );
    assert.match(appLogoIconSource, /M13 19L21\.5 13L28\.5 18L36 8\.5/);
    assert.match(appLogoIconSource, /circle cx="36" cy="8\.5" r="2\.5"/);
});

test('favicon matches the Soamco Budget palette and icon silhouette', () => {
    assert.match(faviconSource, /#EA5A47/);
    assert.doesNotMatch(faviconSource, /#EF6C5B/);
    assert.doesNotMatch(faviconSource, /#F28C6E/);
    assert.doesNotMatch(faviconSource, /fill="#F6EFE9"/);
    assert.match(faviconSource, /rect width="64" height="64" rx="10" fill="#EA5A47"/);
    assert.match(
        faviconSource,
        /rect x="17" y="34" width="7" height="15"/,
    );
    assert.match(
        faviconSource,
        /M19 28L31\.5 20L41\.5 27L49 14/,
    );
    assert.match(
        faviconSource,
        /circle cx="49" cy="14" r="3\.5"/,
    );
});
