import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const welcomeSource = readFileSync(
    new URL('../../resources/js/pages/Welcome.vue', import.meta.url),
    'utf8',
);
const authMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/auth.ts', import.meta.url),
    'utf8',
);
const publicFooterSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteFooter.vue',
        import.meta.url,
    ),
    'utf8',
);
const publicHeaderSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteHeader.vue',
        import.meta.url,
    ),
    'utf8',
);

test('public welcome page uses the new public foundation components', () => {
    assert.match(welcomeSource, /PublicPageSection/);
    assert.match(welcomeSource, /PublicFeatureCard/);
    assert.match(welcomeSource, /PublicSiteFooter/);
});

test('public welcome page includes a responsive public hero and CTA structure', () => {
    assert.ok(
        welcomeSource.includes(
            'lg:grid-cols-[minmax(0,1.1fr)_minmax(21rem,27rem)]',
        ),
    );
    assert.ok(
        welcomeSource.includes('text-[2.35rem]') &&
            welcomeSource.includes('sm:text-[3.05rem]') &&
            welcomeSource.includes('lg:text-[4.1rem]'),
    );
    assert.match(welcomeSource, /grid gap-3 sm:grid-cols-3/);
    assert.ok(welcomeSource.includes('bg-[#fffdfb]'));
    assert.match(welcomeSource, /auth\.welcome\.actions\.viewPricing/);
    assert.match(welcomeSource, /href="\/features"/);
    assert.match(welcomeSource, /href="\/pricing"/);
});

test('public welcome page exposes the requested public navbar and mobile menu', () => {
    assert.match(welcomeSource, /PublicSiteHeader/);
    assert.match(publicHeaderSource, /auth\.welcome\.nav\.home/);
    assert.match(publicHeaderSource, /auth\.welcome\.nav\.features/);
    assert.match(publicHeaderSource, /auth\.welcome\.nav\.pricing/);
    assert.doesNotMatch(publicHeaderSource, /auth\.welcome\.nav\.changelog/);
    assert.doesNotMatch(publicHeaderSource, /auth\.welcome\.nav\.customers/);
    assert.doesNotMatch(publicHeaderSource, /auth\.welcome\.nav\.aboutMe/);
    assert.match(publicHeaderSource, /auth\.welcome\.nav\.login/);
    assert.match(publicHeaderSource, /auth\.welcome\.nav\.registerFree/);
    assert.match(publicHeaderSource, /isMobileMenuOpen/);
    assert.match(publicHeaderSource, /Public primary navigation/);
    assert.match(publicHeaderSource, /href: '\/features'/);
    assert.doesNotMatch(publicHeaderSource, />\s*Beta\s*</);
});

test('public footer exposes grouped links and language switcher', () => {
    assert.match(publicFooterSource, /footerGroups/);
    assert.match(publicFooterSource, /PublicLocaleSwitcher/);
    assert.match(publicFooterSource, /Made with ♡ in Italy/);
    assert.match(publicFooterSource, /launchYear = 2026/);
    assert.match(publicFooterSource, /socialLinks/);
    assert.match(publicFooterSource, /publicProfileLinks\.website/);
    assert.match(publicFooterSource, /publicProfileLinks\.linkedin/);
    assert.match(publicFooterSource, /publicProfileLinks\.github/);
    assert.match(publicFooterSource, /href: '\/customers'/);
    assert.match(
        publicFooterSource,
        /auth\.welcome\.footer\.groups\.features\.title/,
    );
    assert.match(publicFooterSource, /features\(\)/);
    assert.match(publicFooterSource, /pricing\(\)/);
});

test('public footer uses the reduced link set and developer wording', () => {
    const companySectionStart = publicFooterSource.indexOf(
        "title: t('auth.welcome.footer.groups.company.title')",
    );
    const companySectionEnd = publicFooterSource.indexOf(
        ']);',
        companySectionStart,
    );
    const companyLinksBlock =
        companySectionStart >= 0 && companySectionEnd >= 0
            ? publicFooterSource.slice(companySectionStart, companySectionEnd)
            : null;

    assert.doesNotMatch(publicFooterSource, /forTeams/);
    assert.doesNotMatch(publicFooterSource, /compare/);
    assert.doesNotMatch(publicFooterSource, /templates/);
    assert.doesNotMatch(publicFooterSource, /productivity/);
    assert.doesNotMatch(publicFooterSource, /integrations/);
    assert.doesNotMatch(publicFooterSource, /api/);
    assert.doesNotMatch(publicFooterSource, /workWithUs/);
    assert.doesNotMatch(publicFooterSource, /inspiration/);
    assert.doesNotMatch(publicFooterSource, /press/);
    assert.doesNotMatch(
        publicFooterSource,
        /auth\.welcome\.footer\.legal\.security/,
    );
    assert.ok(companyLinksBlock);
    assert.match(
        companyLinksBlock,
        /auth\.welcome\.footer\.groups\.company\.links\.about/,
    );
    assert.match(
        publicFooterSource,
        /auth\.welcome\.footer\.groups\.resources\.links\.help/,
    );
    assert.doesNotMatch(companyLinksBlock, /LinkedIn/);
    assert.doesNotMatch(companyLinksBlock, /GitHub/);
    assert.match(
        publicFooterSource,
        /flex flex-row gap-4 text-slate-800 lg:flex-col/,
    );
    assert.match(
        publicFooterSource,
        /grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center/,
    );
    assert.match(authMessagesSource, /title: 'Developer'/);
    assert.match(authMessagesSource, /about: 'Chi sono'/);
});

test('public welcome copy is product-oriented instead of the default Laravel starter content', () => {
    assert.match(
        authMessagesSource,
        /Porta ordine in budget, conti e movimenti senza aggiungere complessità/,
    );
    assert.match(authMessagesSource, /Software in evoluzione continua/);
    assert.match(
        authMessagesSource,
        /Bring order to budgets, accounts, and transactions without adding complexity/,
    );
    assert.match(
        authMessagesSource,
        /A personal finance foundation that stays clear as it grows/,
    );
    assert.match(authMessagesSource, /Gratis da usare, utile da sostenere/);
    assert.match(
        authMessagesSource,
        /Inizia a mettere ordine nei tuoi conti con uno strumento chiaro e già utile/,
    );
    assert.match(authMessagesSource, /dashboard pesante o complessa/);
    assert.doesNotMatch(
        authMessagesSource,
        /Laravel has an incredibly rich ecosystem/,
    );
    assert.match(authMessagesSource, /Registrati gratis/);
});
