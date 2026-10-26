import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    compileScript,
    compileTemplate,
    parse,
    registerTS,
} from '@vue/compiler-sfc';
import ts from 'typescript';
import * as Vue from 'vue';
import { renderToString } from 'vue/server-renderer';

registerTS(() => ts);
function evaluate(source, require) {
    const exports = {};
    new Function(
        'require',
        'exports',
        ts.transpile(source, {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        }),
    )(require, exports);

    return exports;
}
function compile(path) {
    const filename = fileURLToPath(new URL(`../../${path}`, import.meta.url));
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), {
        filename,
    });
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, {
        id: path,
        fs: {
            fileExists: existsSync,
            readFile: (file) => readFileSync(file, 'utf8'),
        },
    });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: path,
        ssr: true,
        ssrCssVars: descriptor.cssVars,
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);

    return { script, template, descriptor };
}
const initials = evaluate(
    readFileSync(
        new URL(
            '../../resources/js/composables/useInitials.ts',
            import.meta.url,
        ),
        'utf8',
    ),
    () => ({}),
);
const compiled = compile('resources/js/components/ReferenceAvatar.vue');
const wrapper = Vue.defineComponent({
    setup:
        (_, { slots }) =>
        () =>
            Vue.h('span', slots.default?.()),
});
const image = Vue.defineComponent({
    props: ['src', 'alt'],
    setup: (props) => () => Vue.h('img', { src: props.src, alt: props.alt }),
});
const require = (name) => {
    if (name === 'vue') {
        return Vue;
    }

    if (name === 'vue/server-renderer') {
        return { ssrRenderComponent: undefined, ...SSR };
    }

    if (name === '@/composables/useInitials') {
        return initials;
    }

    if (name === '@/components/ui/avatar') {
        return { Avatar: wrapper, AvatarImage: image, AvatarFallback: wrapper };
    }

    throw new Error(`Unexpected dependency: ${name}`);
};
const SSR = await import('vue/server-renderer');
const Avatar = evaluate(compiled.script.content, require).default;
Avatar.ssrRender = evaluate(compiled.template.code, require).ssrRender;

test('reference avatars use the stored logo without cropping and retain the shared initials fallback', async () => {
    const html = await renderToString(
        Vue.createSSRApp(Avatar, {
            name: 'Roberta Delta',
            logoUrl: '/storage/references/roberta.png',
        }),
    );
    assert.match(html, /src="\/storage\/references\/roberta.png"/);
    assert.match(html, / alt(?:=""|(?=\s|>))/);
    assert.match(html, /object-contain/);
    assert.match(html, /RD/);
    assert.match(html, /aria-hidden="true"/);
});

test('references without a logo display their initials and never request an invented image', async () => {
    for (const [name, expected] of [
        ['Roberta Delta', 'RD'],
        ['Test', 'T'],
        ['', '?'],
    ]) {
        const html = await renderToString(
            Vue.createSSRApp(Avatar, { name, logoUrl: null }),
        );
        assert.doesNotMatch(html, /<img/);
        assert.ok(html.includes(expected));
    }
});

test('arrears and both agenda lists bind reference logos while preserving their existing amounts and links', () => {
    const analysis = compile('resources/js/pages/dashboard/Analysis.vue')
        .descriptor.template.content;
    const agenda = compile(
        'resources/js/components/dashboard/FinancialAgenda.vue',
    ).descriptor.template.content;
    assert.match(analysis, /:name="item.title"/);
    assert.match(analysis, /:logo-url="item.logo_url"/);
    assert.match(analysis, /:href="item.source_url"/);
    assert.match(agenda, /:logo-url="entry.logo_url"/);
    assert.match(agenda, /:logo-url="payee.logo_url"/);
    assert.match(agenda, /money\(entry.expected_amount_raw\)/);
    assert.match(agenda, /money\(payee.total_amount_raw\)/);
    assert.match(agenda, /min-w-0 flex-1/);
});
