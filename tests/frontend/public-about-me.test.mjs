import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routesSource = readFileSync(
    new URL('../../routes/web.php', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const aboutSource = readFileSync(
    new URL('../../resources/js/pages/AboutMe.vue', import.meta.url),
    'utf8',
);
const aboutContentSource = readFileSync(
    new URL('../../resources/js/i18n/about-content.ts', import.meta.url),
    'utf8',
);
const profileLinksSource = readFileSync(
    new URL('../../resources/js/config/public-profile.ts', import.meta.url),
    'utf8',
);

test('public about me route is registered', () => {
    assert.match(routesSource, /Route::inertia\('\/about-me', 'AboutMe'/);
});

test('public navbar links to the about me page', () => {
    assert.match(headerSource, /href: '\/about-me'/);
    assert.match(headerSource, /auth\.welcome\.nav\.aboutMe/);
    assert.match(aboutSource, /current-page="about-me"/);
});

test('about me page includes the main narrative sections', () => {
    assert.match(aboutSource, /content\.profile/);
    assert.match(aboutSource, /content\.origin/);
    assert.match(aboutSource, /content\.work/);
    assert.match(aboutSource, /content\.links/);
    assert.match(
        aboutContentSource,
        /Soamco Budget nasce da esigenze personali reali/,
    );
});

test('about me page includes LinkedIn and GitHub links through dedicated config', () => {
    assert.match(aboutSource, /publicProfileLinks\.website/);
    assert.match(aboutSource, /publicProfileLinks\.name/);
    assert.match(aboutSource, /publicProfileLinks\.portrait/);
    assert.match(aboutSource, /publicProfileLinks\.linkedin/);
    assert.match(aboutSource, /publicProfileLinks\.github/);
    assert.match(profileLinksSource, /Giuseppe Alessandro De Blasio/);
    assert.match(profileLinksSource, /giuseppealessandrodeblasio\.it/);
    assert.match(profileLinksSource, /media\.licdn\.com/);
    assert.match(
        profileLinksSource,
        /linkedin\.com\/in\/giuseppealessandrodeblasio/,
    );
    assert.match(profileLinksSource, /github\.com\/Gialdeb/);
    assert.match(profileLinksSource, /linkedin\.com/);
    assert.match(profileLinksSource, /github\.com/);
});
