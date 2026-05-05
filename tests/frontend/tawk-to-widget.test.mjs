import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { loadTawkToWidget } from '../../resources/js/lib/tawk-to.js';

const widgetSource = readSource('resources/js/components/public/TawkToWidget.vue');
const marketingLayoutSource = readSource(
    'resources/js/layouts/public/PublicMarketingLayout.vue',
);
const headerSource = readSource('resources/js/components/public/PublicSiteHeader.vue');
const footerSource = readSource('resources/js/components/public/PublicSiteFooter.vue');
const legalLayoutSource = readSource(
    'resources/js/layouts/public/PublicLegalLayout.vue',
);
const appLayoutSource = readSource('resources/js/layouts/AppLayout.vue');
const appSidebarLayoutSource = readSource(
    'resources/js/layouts/app/AppSidebarLayout.vue',
);
const adminLayoutSource = readSource('resources/js/layouts/admin/Layout.vue');
const settingsLayoutSource = readSource('resources/js/layouts/settings/Layout.vue');
const welcomeSource = readSource('resources/js/pages/Welcome.vue');
const featuresSource = readSource('resources/js/pages/Features.vue');
const pricingSource = readSource('resources/js/pages/Pricing.vue');
const aboutSource = readSource('resources/js/pages/AboutMe.vue');

function readSource(path) {
    return readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
}

function createScript(src = '') {
    return {
        async: false,
        src,
        charset: '',
        attributes: {},
        parentNode: null,
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
}

function createEnvironment(existingScripts = [createScript('https://example.test/app.js')]) {
    const inserted = [];
    const appended = [];
    const parentNode = {
        insertBefore(script, before) {
            script.parentNode = this;
            inserted.push({ script, before });
        },
    };
    const scripts = existingScripts.map((script) => ({
        ...script,
        parentNode,
    }));
    const body = {
        appendChild(script) {
            script.parentNode = this;
            appended.push(script);
        },
    };

    return {
        inserted,
        appended,
        window: {},
        document: {
            body,
            createElement(tagName) {
                assert.equal(tagName, 'script');

                return createScript();
            },
            getElementsByTagName(tagName) {
                assert.equal(tagName, 'script');

                return scripts;
            },
        },
    };
}

test('tawk widget is mounted only by public layouts', () => {
    assert.match(widgetSource, /onMounted/);
    assert.match(widgetSource, /loadTawkToWidget/);
    assert.match(marketingLayoutSource, /TawkToWidget/);
    assert.match(legalLayoutSource, /TawkToWidget/);
    assert.doesNotMatch(headerSource, /TawkToWidget/);
    assert.doesNotMatch(footerSource, /TawkToWidget/);
    assert.doesNotMatch(appLayoutSource, /TawkToWidget/);
    assert.doesNotMatch(appSidebarLayoutSource, /TawkToWidget/);
    assert.doesNotMatch(adminLayoutSource, /TawkToWidget/);
    assert.doesNotMatch(settingsLayoutSource, /TawkToWidget/);
});

test('marketing pages use the public marketing layout that mounts the tawk widget', () => {
    assert.match(welcomeSource, /PublicMarketingLayout/);
    assert.match(featuresSource, /PublicMarketingLayout/);
    assert.match(pricingSource, /PublicMarketingLayout/);
    assert.match(aboutSource, /PublicMarketingLayout/);
});

test('tawk widget loader does not hardcode development or production gates', () => {
    assert.doesNotMatch(readSource('resources/js/lib/tawk-to.js'), /import\.meta\.env|NODE_ENV|APP_ENV/);
    assert.match(widgetSource, /import\.meta\.env\.DEV/);
    assert.match(widgetSource, /console\.debug/);
});

test('tawk widget inserts the configured script in test and development environments', () => {
    const previousNodeEnvironment = process.env.NODE_ENV;
    process.env.NODE_ENV = 'development';

    try {
        const environment = createEnvironment();

        assert.equal(
            loadTawkToWidget(
                {
                    enabled: true,
                    propertyId: '69fa033f3527a91c38586ba7',
                    widgetId: '1jns9pcos',
                },
                environment,
            ),
            true,
        );
        assert.equal(environment.inserted.length, 1);
        assert.equal(
            environment.inserted[0].script.src,
            'https://embed.tawk.to/69fa033f3527a91c38586ba7/1jns9pcos',
        );
    } finally {
        if (previousNodeEnvironment === undefined) {
            delete process.env.NODE_ENV;
        } else {
            process.env.NODE_ENV = previousNodeEnvironment;
        }
    }
});

test('tawk widget does not insert a script when disabled', () => {
    const environment = createEnvironment();
    const reports = [];

    assert.equal(
        loadTawkToWidget(
            {
                enabled: false,
                propertyId: '69fa033f3527a91c38586ba7',
                widgetId: '1jns9pcos',
            },
            environment,
            {
                reporter: (state) => reports.push(state),
            },
        ),
        false,
    );
    assert.equal(environment.inserted.length, 0);
    assert.equal(environment.appended.length, 0);
    assert.equal(reports[0].reason, 'disabled');
});

test('tawk widget does not insert a script when ids are missing', () => {
    for (const config of [
        { enabled: true, propertyId: '', widgetId: '1jns9pcos' },
        { enabled: true, propertyId: '69fa033f3527a91c38586ba7', widgetId: '' },
        { enabled: true, propertyId: null, widgetId: '1jns9pcos' },
        { enabled: true, propertyId: '69fa033f3527a91c38586ba7', widgetId: null },
    ]) {
        const environment = createEnvironment();

        assert.equal(loadTawkToWidget(config, environment), false);
        assert.equal(environment.inserted.length, 0);
        assert.equal(environment.appended.length, 0);
    }
});

test('tawk widget inserts the configured script before the first script', () => {
    const environment = createEnvironment();
    const reports = [];

    assert.equal(
        loadTawkToWidget(
            {
                enabled: true,
                propertyId: '69fa033f3527a91c38586ba7',
                widgetId: '1jns9pcos',
            },
            environment,
            {
                reporter: (state) => reports.push(state),
            },
        ),
        true,
    );

    assert.equal(environment.inserted.length, 1);
    assert.equal(environment.appended.length, 0);
    assert.equal(
        environment.inserted[0].script.src,
        'https://embed.tawk.to/69fa033f3527a91c38586ba7/1jns9pcos',
    );
    assert.equal(environment.inserted[0].script.async, true);
    assert.equal(environment.inserted[0].script.charset, 'UTF-8');
    assert.equal(environment.inserted[0].script.attributes.crossorigin, '*');
    assert.deepEqual(environment.window.Tawk_API, {});
    assert.ok(environment.window.Tawk_LoadStart instanceof Date);
    assert.deepEqual(reports[0], {
        loaded: true,
        reason: 'inserted-before-first-script',
        enabled: true,
        hasPropertyId: true,
        hasWidgetId: true,
        scriptSrc: 'https://embed.tawk.to/69fa033f3527a91c38586ba7/1jns9pcos',
    });
});

test('tawk widget appends to body when no existing script is available', () => {
    const environment = createEnvironment([]);

    assert.equal(
        loadTawkToWidget(
            {
                enabled: true,
                propertyId: '69fa033f3527a91c38586ba7',
                widgetId: '1jns9pcos',
            },
            environment,
        ),
        true,
    );
    assert.equal(environment.inserted.length, 0);
    assert.equal(environment.appended.length, 1);
    assert.equal(
        environment.appended[0].src,
        'https://embed.tawk.to/69fa033f3527a91c38586ba7/1jns9pcos',
    );
});

test('tawk widget avoids inserting the same script twice', () => {
    const environment = createEnvironment([
        createScript('https://embed.tawk.to/69fa033f3527a91c38586ba7/1jns9pcos'),
    ]);

    assert.equal(
        loadTawkToWidget(
            {
                enabled: true,
                propertyId: '69fa033f3527a91c38586ba7',
                widgetId: '1jns9pcos',
            },
            environment,
        ),
        false,
    );
    assert.equal(environment.inserted.length, 0);
});
