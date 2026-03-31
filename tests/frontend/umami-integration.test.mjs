import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const analyticsSource = readFileSync(
    new URL('../../resources/js/lib/analytics.ts', import.meta.url),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const bladeSource = readFileSync(
    new URL('../../resources/views/app.blade.php', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const footerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteFooter.vue',
        import.meta.url,
    ),
    'utf8',
);
const welcomeSource = readFileSync(
    new URL('../../resources/js/pages/Welcome.vue', import.meta.url),
    'utf8',
);
const pricingSource = readFileSync(
    new URL('../../resources/js/pages/Pricing.vue', import.meta.url),
    'utf8',
);
const aboutSource = readFileSync(
    new URL('../../resources/js/pages/AboutMe.vue', import.meta.url),
    'utf8',
);
const downloadSource = readFileSync(
    new URL('../../resources/js/pages/DownloadApp.vue', import.meta.url),
    'utf8',
);
const changelogCardSource = readFileSync(
    new URL(
        '../../resources/js/components/public/changelog/PublicChangelogReleaseCard.vue',
        import.meta.url,
    ),
    'utf8',
);

test('umami pageviews are initialized once and tracked on inertia navigation', () => {
    assert.match(appSource, /initializeAnalytics\(props\.initialPage\)/);
    assert.match(analyticsSource, /window\.__soamcoBudgetUmamiInitialized/);
    assert.match(analyticsSource, /router\.on\('navigate'/);
    assert.match(
        analyticsSource,
        /window\.__soamcoBudgetUmamiLastTrackedPage === signature/,
    );
});

test('umami script is rendered with manual pageview mode', () => {
    assert.match(bladeSource, /data-auto-track="false"/);
    assert.match(bladeSource, /data-website-id=/);
    assert.match(bladeSource, /data-do-not-track="true"/);
});

test('public cta tracking goes through the shared helper instead of direct window.umami calls', () => {
    assert.match(headerSource, /trackPublicCta/);
    assert.match(footerSource, /trackPublicCta/);
    assert.match(welcomeSource, /trackPublicCta/);
    assert.match(pricingSource, /trackPublicCta/);
    assert.match(aboutSource, /trackPublicCta/);
    assert.match(downloadSource, /trackPublicCta/);
    assert.match(changelogCardSource, /trackPublicCta/);
    assert.doesNotMatch(headerSource, /window\.umami/);
    assert.doesNotMatch(footerSource, /window\.umami/);
    assert.doesNotMatch(welcomeSource, /window\.umami/);
    assert.doesNotMatch(pricingSource, /window\.umami/);
    assert.doesNotMatch(aboutSource, /window\.umami/);
    assert.doesNotMatch(downloadSource, /window\.umami/);
    assert.doesNotMatch(changelogCardSource, /window\.umami/);
});

test('public funnel events use consistent event names and placements', () => {
    assert.match(headerSource, /cta_login_clicked/);
    assert.match(headerSource, /cta_register_clicked/);
    assert.match(welcomeSource, /cta_features_clicked/);
    assert.match(pricingSource, /pricing_donation_clicked/);
    assert.match(aboutSource, /about_linkedin_clicked/);
    assert.match(aboutSource, /about_github_clicked/);
    assert.match(downloadSource, /download_app_clicked/);
    assert.match(changelogCardSource, /changelog_release_clicked/);
    assert.match(analyticsSource, /placement/);
    assert.match(analyticsSource, /target/);
    assert.match(analyticsSource, /locale/);
});
