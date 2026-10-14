import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve, dirname } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse as parseTemplate } from '@vue/compiler-dom';
import { parse as parseVue } from '@vue/compiler-sfc';
import ts from 'typescript';
import { createI18n } from 'vue-i18n';

const require = createRequire(import.meta.url);
const cache = new Map();
function load(path) {
    path = resolve(path);

    if (cache.has(path)) {
        return cache.get(path);
    }

    const module = { exports: {} };
    runInNewContext(
        ts.transpile(readFileSync(path, 'utf8'), {
            module: ts.ModuleKind.CommonJS,
        }),
        {
            exports: module.exports,
            require: (name) =>
                name.startsWith('@/')
                    ? load(`resources/js/${name.slice(2)}.ts`)
                    : name.startsWith('.')
                      ? load(resolve(dirname(path), `${name}.ts`))
                      : require(name),
        },
    );
    cache.set(path, module.exports);

    return module.exports;
}
const { messages } = load('resources/js/i18n/messages.ts');
const { uiCopy, uiCopyMessages } = load('resources/js/i18n/ui-copy.ts');
function vueFiles(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = `${directory}/${entry.name}`;

        return entry.isDirectory()
            ? vueFiles(path)
            : entry.name.endsWith('.vue')
              ? [path]
              : [];
    });
}
function leaves(value, prefix = '') {
    return Object.entries(value).flatMap(([key, item]) =>
        typeof item === 'object' && item !== null
            ? leaves(item, `${prefix}${key}.`)
            : [[`${prefix}${key}`, item]],
    );
}

test('all frontend dictionary keys and interpolation placeholders exist in both languages', () => {
    const italian = Object.fromEntries(leaves(messages.it));
    const english = Object.fromEntries(leaves(messages.en));
    assert.deepEqual(Object.keys(italian).sort(), Object.keys(english).sort());

    for (const [key, value] of Object.entries(italian)) {
        assert.equal(typeof english[key], typeof value, key);

        if (typeof value !== 'string') {
            continue;
        }

        assert.equal(Boolean(value.trim()), Boolean(english[key].trim()), key);
        assert.deepEqual(
            [...value.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort(),
            [...english[key].matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort(),
            key,
        );
    }
});

test('supplementary copy renders and changes reactively without translating user data', () => {
    const i18n = createI18n({
        legacy: false,
        locale: 'it',
        fallbackLocale: false,
        messages,
    });

    for (const [key, value] of Object.entries(uiCopyMessages.it)) {
        const values = Object.fromEntries(
            [...value.matchAll(/\{(\w+)\}/g)].map((m) => [m[1], 'Mario 50']),
        );
        const path = `uiCopy.${key}`;

        for (const locale of ['it', 'en']) {
            i18n.global.locale.value = locale;
            const rendered = i18n.global.t(path, values);
            assert.notEqual(rendered, path);
            assert.ok(!rendered.includes('{name}'), path);

            if (Object.keys(values).length) {
                assert.ok(rendered.includes('Mario 50'), path);
            }
        }
    }

    assert.ok(Object.keys(uiCopy).length > 400);
});

test('literal translation keys in every Vue screen resolve in both languages', () => {
    const flatten = Object.fromEntries(leaves(messages.it));
    const missing = [];
    function visit(directory) {
        for (const entry of readdirSync(directory, { withFileTypes: true })) {
            const path = `${directory}/${entry.name}`;

            if (entry.isDirectory()) {
                visit(path);
            } else if (entry.name.endsWith('.vue')) {
                const source = readFileSync(path, 'utf8');

                for (const match of source.matchAll(
                    /\bt\(\s*['"]([\w.-]+)['"]/g,
                )) {
                    if (!Object.hasOwn(flatten, match[1])) {
                        missing.push(`${path}: ${match[1]}`);
                    }
                }
            }
        }
    }
    visit('resources/js');
    assert.deepEqual([...new Set(missing)], []);
});

test('Vue templates contain no untranslated static prose or accessible labels', () => {
    const languageNeutral = new Set([
        'Soamco Budget',
        'Soamco',
        'III.',
        'beta',
        'stable',
        'Slug',
        'Email',
        'new / improved / fixed',
        'getting-started',
        'connect-bank-account',
    ]);
    const missing = [];

    for (const path of vueFiles('resources/js')) {
        const { descriptor } = parseVue(readFileSync(path, 'utf8'));

        if (!descriptor.template) {
            continue;
        }

        function inspect(value) {
            value = value.trim().replace(/\s+/g, ' ');

            if (
                !/[a-zA-Z]{3}/.test(value) ||
                languageNeutral.has(value) ||
                /^[·\s]*(?:EUR|USD|GBP|JPY)$/.test(value) ||
                value.startsWith('https://')
            ) {
                return;
            }

            missing.push(`${path}: ${value}`);
        }
        function visit(node) {
            if (node.type === 2) {
                inspect(node.content);
            }

            if (
                node.type === 6 &&
                ['title', 'alt', 'placeholder', 'aria-label'].includes(
                    node.name,
                ) &&
                node.value
            ) {
                inspect(node.value.content);
            }

            for (const prop of node.props ?? []) {
                visit(prop);
            }

            for (const child of node.children ?? []) {
                visit(child);
            }
        }
        visit(parseTemplate(descriptor.template.content));
    }

    assert.deepEqual(missing, []);
});
