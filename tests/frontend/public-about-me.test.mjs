import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
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

test('public navbar keeps the reduced navigation while about me page remains routable', () => {
    assert.doesNotMatch(headerSource, /href: '\/about-me'/);
    assert.doesNotMatch(headerSource, /auth\.welcome\.nav\.aboutMe/);
    assert.match(headerSource, /href: '\/features'/);
    assert.match(headerSource, /href: '\/pricing'/);
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
    assert.match(profileLinksSource, /\/images\/about\/about-me-portrait\.jpg/);
    assert.match(
        profileLinksSource,
        /linkedin\.com\/in\/giuseppealessandrodeblasio/,
    );
    assert.match(profileLinksSource, /github\.com\/Gialdeb/);
    assert.match(profileLinksSource, /linkedin\.com/);
    assert.match(profileLinksSource, /github\.com/);
    assert.equal(
        existsSync(
            new URL(
                '../../public/images/about/about-me-portrait.jpg',
                import.meta.url,
            ),
        ),
        true,
    );
});

test('about me page keeps mobile responsive hero and profile layout safeguards', () => {
    assert.match(aboutSource, /px-4 pt-6 pb-14 sm:px-6 sm:pt-10 sm:pb-18/);
    assert.match(aboutSource, /text-\[2rem\] leading-\[0\.98\] font-semibold/);
    assert.match(aboutSource, /class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-\[#ea5a47].*sm:w-auto/);
    assert.match(aboutSource, /class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:text-left"/);
    assert.match(aboutSource, /class="h-56 w-full rounded-\[1\.5rem] object-cover object-\[center_18%].*sm:size-20 sm:w-20 sm:object-cover sm:object-top"/);
    assert.match(aboutSource, /break-all text-sm leading-7 text-slate-600 sm:break-normal/);
});
