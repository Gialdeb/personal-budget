import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transformSync } from 'esbuild';
import * as vue from 'vue';
import * as helpers from '../../resources/js/lib/onboarding-tour.js';

function loadTourModule(path) {
    const file = new URL(`../../resources/js/${path}`, import.meta.url);
    const module = { exports: {} };
    runInNewContext(
        transformSync(readFileSync(file, 'utf8'), {
            loader: 'ts',
            format: 'cjs',
        }).code,
        {
            module,
            exports: module.exports,
            require: (name) =>
                loadTourModule(
                    new URL(`${name}.ts`, file).pathname.split(
                        '/resources/js/',
                    )[1],
                ),
        },
    );

    return module.exports;
}

const source = readFileSync(
    new URL(
        '../../resources/js/components/onboarding/OnboardingTour.vue',
        import.meta.url,
    ),
    'utf8',
);
const script = compileScript(parse(source).descriptor, {
    id: 'onboarding-test',
});
const compiled = transformSync(script.content, {
    loader: 'ts',
    format: 'cjs',
}).code;
const definitions = transformSync(
    readFileSync(
        new URL('../../resources/js/onboarding/tours.ts', import.meta.url),
        'utf8',
    ),
    { loader: 'ts', format: 'cjs' },
).code;
const definitionsModule = { exports: {} };
runInNewContext(definitions, {
    module: definitionsModule,
    exports: definitionsModule.exports,
    require: () => loadTourModule('onboarding/additional-tours.ts'),
});

function harness({
    enabled = true,
    legacy = true,
    dismissed = false,
    stored = {},
    setup = false,
    component = 'dashboard/Analysis',
} = {}) {
    let now = 0;
    let nextTimer = 0;
    const timers = new Map();
    const hooks = {};
    const actions = [];
    const recordedSections = [];
    const listeners = {};
    const targets = new Map();
    let overlay = false;
    let legacyRead = 0;
    class Element {
        inert = false;
        isConnected = true;
        ownerDocument;
        constructor() {
            this.ownerDocument = document;
        }
        getBoundingClientRect() {
            return { top: 100, left: 20, width: 250, height: 40 };
        }
        getClientRects() {
            return [1];
        }
        closest() {
            return null;
        }
        hasAttribute() {
            return false;
        }
        getAttribute() {
            return null;
        }
        scrollIntoView() {}
        focus() {
            document.activeElement = this;
        }
    }
    const document = {
        body: { children: [] },
        activeElement: null,
        documentElement: { hasAttribute: () => false },
        addEventListener() {},
        removeEventListener() {},
        querySelectorAll(selector) {
            const target = selector.match(/data-onboarding="(.+)"/);

            return target
                ? targets.has(target[1])
                    ? [targets.get(target[1])]
                    : []
                : overlay
                  ? [new Element()]
                  : [];
        },
    };
    const style = () => ({
        visibility: 'visible',
        display: 'block',
        paddingTop: '0',
        paddingBottom: '0',
        paddingLeft: '0',
        paddingRight: '0',
    });
    const window = {
        innerWidth: 390,
        innerHeight: 780,
        getComputedStyle: style,
        setTimeout(callback, delay) {
            timers.set(++nextTimer, { callback, at: now + delay });

            return nextTimer;
        },
        clearTimeout(id) {
            timers.delete(id);
        },
        addEventListener() {},
        removeEventListener() {},
    };
    document.defaultView = window;
    const page = vue.reactive({
        component,
        url: '/',
        props: {
            auth: { user: { uuid: 'a' } },
            quick_start: { show: setup },
            contextualHelp: {},
        },
    });
    const onboarding = {
        section: vue.computed(
            () =>
                definitionsModule.exports.screenForComponent[page.component] ??
                null,
        ),
        hasTour: vue.computed(
            () =>
                definitionsModule.exports.screenForComponent[page.component] !=
                null,
        ),
        state: vue.ref({
            enabled,
            tours: stored,
            quick_start_dismissed: dismissed,
            legacy_imported: legacy,
            revision: 0,
        }),
        replayRequest: vue.ref(0),
        saveFailed: vue.ref(false),
        async persist(action, value) {
            actions.push(action);
            recordedSections.push(onboarding.section.value);

            if (action === 'migrate') {
                Object.assign(onboarding.state.value, {
                    legacy_imported: true,
                    quick_start_dismissed: value,
                });
            }

            if (action === 'reset') {
                Object.assign(onboarding.state.value, {
                    enabled: true,
                    tours: {},
                });
            }

            return true;
        },
        openGuide() {
            actions.push('guide');
        },
    };
    const modules = {
        vue: {
            ...vue,
            onMounted(fn) {
                hooks.mount = fn;
            },
            onBeforeUnmount(fn) {
                hooks.unmount = fn;
            },
        },
        '@inertiajs/vue3': {
            usePage: () => page,
            router: {
                on(event, fn) {
                    listeners[event] = fn;

                    return () => {
                        delete listeners[event];
                    };
                },
            },
        },
        'vue-i18n': { useI18n: () => ({ t: (key) => key }) },
        '@/composables/useOnboarding': { useOnboarding: () => onboarding },
        '@/lib/onboarding-tour.js': helpers,
        '@/lib/dashboard-quick-start.js': {
            readDashboardQuickStartDismissed() {
                legacyRead += 1;

                return dismissed;
            },
        },
        '@/onboarding/tours': definitionsModule.exports,
    };
    const module = { exports: {} };
    runInNewContext(compiled, {
        module,
        exports: module.exports,
        require: (key) => modules[key] ?? {},
        document,
        window,
        HTMLElement: Element,
        getComputedStyle: style,
        Date: { now: () => now },
        requestAnimationFrame: () => 1,
        cancelAnimationFrame() {},
        MutationObserver: class {
            observe() {}
            disconnect() {}
        },
        ResizeObserver: class {
            observe() {}
            disconnect() {}
        },
    });
    const bindings = module.exports.default.setup({}, { expose() {} });
    const background = new Element();
    document.body.children = [background];
    document.activeElement = new Element();
    bindings.dialog.value = new Element();

    for (const step of definitionsModule.exports.tours[
        onboarding.section.value
    ] ?? []) {
        targets.set(step.target, new Element());
    }

    const flush = async () => {
        for (let i = 0; i < 8; i += 1) {
            await vue.nextTick();
        }
    };

    return {
        bindings,
        recordedSections,
        actions,
        onboarding,
        page,
        targets,
        document,
        background,
        hooks,
        listeners,
        flush,
        get legacyRead() {
            return legacyRead;
        },
        setOverlay(value) {
            overlay = value;
            bindings.position();
        },
        async tick() {
            const [id, timer] =
                [...timers].sort((a, b) => a[1].at - b[1].at)[0] ?? [];

            if (!timer) {
                return false;
            }

            timers.delete(id);
            now = timer.at;
            timer.callback();
            await flush();

            return true;
        },
    };
}

test('dashboard tour completes and restores focus/background without completing other sections', async () => {
    const h = harness();
    const originalFocus = h.document.activeElement;
    await h.bindings.start();
    await h.flush();
    assert.equal(h.bindings.active.value.id, 'navigation');
    assert.equal(h.background.inert, true);

    for (let i = 0; i < 7; i += 1) {
        h.bindings.next();
        await h.flush();
    }

    assert.deepEqual(h.actions, ['complete']);
    assert.equal(h.background.inert, false);
    assert.equal(h.document.activeElement, originalFocus);
});

test('Escape skips, manual restart works when disabled, and overlays suspend the tour', async () => {
    const h = harness({ enabled: false });
    await h.bindings.start();
    assert.equal(h.bindings.active.value, undefined);
    await h.bindings.start(true);
    await h.flush();
    assert.equal(h.bindings.visible.value, true);
    h.setOverlay(true);
    assert.equal(h.bindings.visible.value, false);
    assert.equal(h.background.inert, false);
    h.setOverlay(false);
    await h.flush();
    assert.equal(h.bindings.visible.value, true);
    let prevented = false;
    h.bindings.onKeydown({
        key: 'Escape',
        preventDefault() {
            prevented = true;
        },
        stopPropagation() {},
    });
    assert.equal(prevented, true);
    assert.deepEqual(h.actions, ['reset', 'skip']);
});

test('late targets are awaited and all missing targets never produce a false completion', async () => {
    const h = harness();
    const navigation = h.targets.get('chrome.navigation');
    h.targets.delete('chrome.navigation');
    await h.bindings.start();
    assert.equal(h.bindings.visible.value, false);
    h.targets.set('chrome.navigation', navigation);
    await h.tick();
    assert.equal(h.bindings.visible.value, true);
    h.bindings.stop();
    h.targets.clear();
    await h.bindings.start();

    for (let i = 0; i < 150; i += 1) {
        if (!(await h.tick())) {
            break;
        }
    }

    assert.equal(h.bindings.active.value, undefined);
    assert.equal(h.bindings.unavailable.value, true);
    assert.deepEqual(h.actions, []);
});

test('legacy dismissal suppresses setup only and does not complete the dashboard', async () => {
    const h = harness({ legacy: false, dismissed: true, setup: true });
    await h.bindings.start();
    await h.flush();
    assert.equal(h.legacyRead, 1);
    assert.equal(h.bindings.active.value.id, 'navigation');
    assert.deepEqual(h.actions, ['migrate']);
});

test('Inertia navigation interrupts without completion and never autostarts other sections', async () => {
    const h = harness();
    h.hooks.mount();
    await h.tick();
    assert.equal(h.bindings.visible.value, true);
    h.listeners.start();
    assert.equal(h.bindings.visible.value, false);
    h.page.component = 'help-center/Index';
    h.page.url = '/help-center';
    await h.flush();
    h.listeners.finish();
    assert.equal(await h.tick(), false);
    assert.deepEqual(h.actions, []);
    h.hooks.unmount();
    assert.equal(h.background.inert, false);
});

test('automatic start respects saved completion and session verification', async () => {
    const completed = harness({ stored: { dashboard: 'completed' } });
    await completed.bindings.start();
    assert.equal(completed.bindings.active.value, undefined);
    assert.deepEqual(completed.actions, []);
    const unverified = harness();
    unverified.document.documentElement.hasAttribute = () => true;
    unverified.hooks.mount();
    await unverified.tick();
    assert.equal(unverified.bindings.active.value, undefined);
    assert.deepEqual(unverified.actions, []);
    unverified.document.documentElement.hasAttribute = () => false;
    unverified.bindings.scheduleAutoStart();
    await unverified.tick();
    assert.equal(unverified.bindings.visible.value, true);
    unverified.hooks.unmount();
});

for (const component of Object.keys(
    definitionsModule.exports.screenForComponent,
).filter((page) => page !== 'dashboard/Analysis')) {
    test(`${component}: uses its own completion, independent of dashboard, and can replay`, async () => {
        const h = harness({
            component,
            legacy: false,
            stored: { dashboard: 'completed' },
        });
        await h.bindings.start();
        await h.flush();
        const section = definitionsModule.exports.screenForComponent[component];
        assert.equal(h.bindings.visible.value, true);
        const count = h.bindings.steps.value.length;

        for (let i = 0; i < count; i += 1) {
            h.bindings.next();
            await h.flush();
        }

        assert.deepEqual(h.actions, ['complete']);
        assert.deepEqual(h.recordedSections, [section]);
        const completed = harness({
            component,
            stored: { [section]: 'completed' },
        });
        await completed.bindings.start();
        assert.equal(completed.bindings.active.value, undefined);
        await completed.bindings.start(true);
        await completed.flush();
        assert.equal(completed.bindings.visible.value, true);
        assert.deepEqual(completed.actions, ['reset']);
    });
}

test('an unavailable final dynamic target returns to the last usable step and allows explicit completion', async () => {
    const h = harness({ component: 'SharedExpenses' });
    h.targets.delete('shared.repayments');
    await h.bindings.start();
    await h.flush();

    for (let i = 0; i < 6; i += 1) {
        h.bindings.next();
        await h.flush();
    }

    for (let i = 0; i < 22; i += 1) {
        if (!(await h.tick())) {
            break;
        }
    }

    assert.equal(h.bindings.unavailable.value, false);
    assert.equal(h.bindings.active.value.id, 'sharedShares');
    assert.deepEqual(h.actions, []);
    h.bindings.next();
    await h.flush();
    assert.deepEqual(h.actions, ['complete']);
    assert.deepEqual(h.recordedSections, ['shared-expenses']);
});

test('each section has a short tour, existing targets and aligned Italian/English copy', () => {
    const module = { exports: {} };
    const copy = readFileSync(
        new URL(
            '../../resources/js/i18n/messages/onboarding.ts',
            import.meta.url,
        ),
        'utf8',
    );
    runInNewContext(transformSync(copy, { loader: 'ts', format: 'cjs' }).code, {
        module,
        exports: module.exports,
        require: () => loadTourModule('i18n/messages/additional-onboarding.ts'),
    });
    const vueSources = (directory) =>
        readdirSync(
            new URL(`../../resources/js/${directory}`, import.meta.url),
            { withFileTypes: true },
        ).flatMap((entry) =>
            entry.isDirectory()
                ? vueSources(`${directory}/${entry.name}`)
                : entry.name.endsWith('.vue')
                  ? [
                        readFileSync(
                            new URL(
                                `../../resources/js/${directory}/${entry.name}`,
                                import.meta.url,
                            ),
                            'utf8',
                        ),
                    ]
                  : [],
        );
    const targets = [
        ...vueSources('pages'),
        ...vueSources('components'),
        ...vueSources('layouts'),
    ].join('\n');

    for (const steps of Object.values(definitionsModule.exports.tours)) {
        assert.ok(steps.length >= 1 && steps.length <= 8);

        for (const step of steps) {
            assert.ok(targets.includes(step.target), step.target);

            for (const locale of ['it', 'en']) {
                assert.ok(
                    module.exports.onboardingMessages[locale].steps[step.id]
                        ?.title,
                    `${locale}.${step.id}`,
                );
                assert.ok(
                    module.exports.onboardingMessages[locale].steps[step.id]
                        ?.body,
                    `${locale}.${step.id}`,
                );
            }
        }
    }
});

test('every Vue page is covered or has a specific exclusion reason', () => {
    const exclusions = loadTourModule(
        'onboarding/coverage.ts',
    ).excludedOnboardingPages;
    const walk = (directory = '') =>
        readdirSync(
            new URL(`../../resources/js/pages/${directory}`, import.meta.url),
            { withFileTypes: true },
        ).flatMap((entry) =>
            entry.isDirectory()
                ? walk(`${directory}${entry.name}/`)
                : entry.name.endsWith('.vue')
                  ? [`${directory}${entry.name.slice(0, -4)}`]
                  : [],
        );

    for (const page of walk()) {
        const section = definitionsModule.exports.screenForComponent[page];
        assert.ok(section || exclusions[page]?.length > 30, page);
        assert.ok(!(section && exclusions[page]), page);
    }
});

test('settings and reports launchers keep completion separate from their detail pages', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/composables/useOnboarding.ts',
            import.meta.url,
        ),
        'utf8',
    );
    const start = source.indexOf('    const section = computed(');
    const end = source.indexOf('    const hasTour = ', start);
    const resolve = (component, url) => {
        const binding = {
            page: { component, url },
            URL,
            computed: (callback) => callback(),
            screenForComponent: definitionsModule.exports.screenForComponent,
        };
        runInNewContext(
            transformSync(source.slice(start, end) + '\nresult = section;', {
                loader: 'ts',
            }).code,
            binding,
        );

        return binding.result;
    };
    assert.equal(resolve('settings/Profile', '/settings'), 'settings-home');
    assert.equal(resolve('settings/Profile', '/settings/profile'), 'profile');
    assert.equal(
        resolve('settings/Accounts', '/settings/accounts?mobile=launcher'),
        'settings-home',
    );
    assert.equal(
        resolve('reports/Overview', '/reports/kpis?mobile=launcher'),
        'report-hub',
    );
    assert.equal(
        resolve('reports/Overview', '/reports/kpis'),
        'report-overview',
    );
});
