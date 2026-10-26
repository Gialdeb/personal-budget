import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { baseParse } from '@vue/compiler-dom';
import {
    compileScript,
    compileTemplate,
    parse,
    registerTS,
} from '@vue/compiler-sfc';
import ts from 'typescript';
import * as Vue from 'vue';

registerTS(() => ts);
const filename = fileURLToPath(
    new URL(
        '../../resources/js/components/dashboard/ManagePaymentDialog.vue',
        import.meta.url,
    ),
);
const source = readFileSync(filename, 'utf8');
const { descriptor, errors } = parse(source, { filename });
const script = compileScript(descriptor, {
    id: filename,
    fs: {
        fileExists: existsSync,
        readFile: (file) => readFileSync(file, 'utf8'),
    },
});
function evaluate(code, require) {
    const exports = {};
    new Function(
        'require',
        'exports',
        ts.transpile(code, {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        }),
    )(require, exports);

    return exports;
}
const paymentHelpers = evaluate(
    readFileSync(
        new URL(
            '../../resources/js/lib/payment-management.ts',
            import.meta.url,
        ),
        'utf8',
    ),
    () => ({}),
);

function setupDialog(height, offsetTop = 0, visualViewport = true) {
    const previousWindow = Object.getOwnPropertyDescriptor(
        globalThis,
        'window',
    );
    const viewport = Object.assign(new EventTarget(), { height, offsetTop });
    const window = Object.assign(new EventTarget(), {
        innerHeight: height,
        visualViewport: visualViewport ? viewport : undefined,
    });
    Object.defineProperty(globalThis, 'window', {
        value: window,
        configurable: true,
    });
    const mounted = [];
    const cleanup = [];
    const component = evaluate(script.content, (name) => {
        if (name === 'vue') {
            return {
                ...Vue,
                useModel: () => Vue.ref(false),
                onMounted: (callback) => mounted.push(callback),
                onBeforeUnmount: (callback) => cleanup.push(callback),
                watch: (...args) => {
                    const stop = Vue.watch(...args);
                    cleanup.push(stop);

                    return stop;
                },
            };
        }

        if (name === '@/i18n/ui-copy') {
            return { useUiCopy: () => (value) => value };
        }

        if (name === 'vue-i18n') {
            return { useI18n: () => ({ locale: Vue.ref('it') }) };
        }

        if (name === '@/composables/useNotifiedForm') {
            return { useForm: (data) => Vue.reactive(data) };
        }

        if (name === '@/lib/payment-management') {
            return paymentHelpers;
        }

        return {};
    }).default;
    const bindings = component.setup(
        { item: null, currency: 'EUR' },
        { expose: () => {} },
    );
    mounted.forEach((callback) => callback());

    return {
        bindings,
        viewport,
        window,
        unmount() {
            cleanup.forEach((callback) => callback());

            if (previousWindow) {
                Object.defineProperty(globalThis, 'window', previousWindow);
            } else {
                delete globalThis.window;
            }
        },
    };
}

for (const [device, height, top] of [
    ['smartphone', 667, 0],
    ['tablet', 1024, 0],
    ['desktop', 900, 0],
    ['mobile keyboard', 285, 135],
    ['landscape keyboard', 180, 25],
]) {
    test(`dialog follows the available visual viewport on ${device}`, () => {
        const instance = setupDialog(height, top);

        try {
            const style = instance.bindings.viewportStyle.value;
            assert.equal(
                style.maxHeight,
                `calc(${height}px - env(safe-area-inset-top) - env(safe-area-inset-bottom) - 1rem)`,
            );
            assert.equal(
                style.top,
                `calc(${top + height / 2}px + (env(safe-area-inset-top) - env(safe-area-inset-bottom)) / 2)`,
            );
        } finally {
            instance.unmount();
        }
    });
}

test('keyboard resize, viewport panning and orientation changes update bounds and release listeners', () => {
    const instance = setupDialog(800);

    try {
        instance.viewport.height = 280;
        instance.viewport.dispatchEvent(new Event('resize'));
        assert.match(instance.bindings.viewportStyle.value.maxHeight, /280px/);
        instance.viewport.offsetTop = 100;
        instance.viewport.dispatchEvent(new Event('scroll'));
        assert.match(instance.bindings.viewportStyle.value.top, /240px/);
        instance.viewport.height = 360;
        instance.window.dispatchEvent(new Event('resize'));
        assert.match(instance.bindings.viewportStyle.value.maxHeight, /360px/);
        instance.unmount();
        instance.viewport.height = 700;
        instance.viewport.dispatchEvent(new Event('resize'));
        assert.equal(instance.bindings.viewportHeight.value, 360);
    } catch (error) {
        instance.unmount();

        throw error;
    }
});

test('window resize remains supported when VisualViewport is unavailable', () => {
    const instance = setupDialog(640, 0, false);

    try {
        instance.window.innerHeight = 320;
        instance.window.dispatchEvent(new Event('resize'));
        assert.equal(instance.bindings.viewportHeight.value, 320);
        assert.equal(instance.bindings.viewportTop.value, 0);
    } finally {
        instance.unmount();
    }
});

test('step navigation resets scroll and announces the new heading without moving the viewport', async () => {
    const instance = setupDialog(320);

    try {
        const focusCalls = [];
        instance.bindings.scrollBody.value = { scrollTop: 900 };
        instance.bindings.stepHeading.value = {
            focus: (options) => focusCalls.push(options),
        };

        for (const step of [1, 2, 1, 0]) {
            instance.bindings.scrollBody.value.scrollTop = 900;
            instance.bindings.step.value = step;
            await Vue.nextTick();
            await Vue.nextTick();
            assert.equal(instance.bindings.scrollBody.value.scrollTop, 0);
        }

        assert.deepEqual(
            focusCalls,
            Array.from({ length: 4 }, () => ({ preventScroll: true })),
        );
    } finally {
        instance.unmount();
    }
});

test('only the body scrolls while actions remain outside it at every step', () => {
    assert.deepEqual(errors, []);
    assert.deepEqual(
        compileTemplate({
            source: descriptor.template.content,
            filename,
            id: filename,
            compilerOptions: { bindingMetadata: script.bindings },
        }).errors,
        [],
    );
    const elements = (node) =>
        (node.children ?? []).filter((child) => child.type === 1);
    const attribute = (node, name) =>
        node.props.find((prop) => prop.type === 6 && prop.name === name)?.value
            ?.content ?? '';
    const dialog = elements(baseParse(descriptor.template.content))[0];
    const content = elements(dialog)[0];
    const [body, footer] = elements(content);
    assert.equal(elements(content).length, 2);
    assert.equal(attribute(body, 'ref'), 'scrollBody');
    assert.match(attribute(body, 'class'), /min-h-0/);
    assert.match(attribute(body, 'class'), /overflow-y-auto/);
    assert.equal(elements(body)[0].tag, 'DialogHeader');
    assert.equal(footer.tag, 'DialogFooter');
    assert.match(attribute(footer, 'class'), /shrink-0/);
    assert.match(attribute(footer, 'class'), /grid-cols-2/);
    assert.match(attribute(footer, 'class'), /safe-area-inset-bottom/);
    assert.match(attribute(content, 'class'), /overflow-hidden/);
    assert.match(source, /aria-live="polite"/);
    const descendants = (node) =>
        elements(node).flatMap((child) => [child, ...descendants(child)]);

    for (const select of descendants(body).filter(
        (node) => node.tag === 'select',
    )) {
        const classes = attribute(select, 'class').split(/\s+/);
        assert.ok(
            ['w-full', 'min-w-0', 'max-w-full'].every((value) =>
                classes.includes(value),
            ),
        );
    }
});
