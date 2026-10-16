import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import vue from '@vitejs/plugin-vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { createI18n } from 'vue-i18n';

test('shared search input renders exactly one accessible clear control only for editable nonempty searches', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': resolve('resources/js') } },
        server: { middlewareMode: true },
        optimizeDeps: { noDiscovery: true, include: [] },
    });

    try {
        const { default: Input } = await server.ssrLoadModule(
            '/resources/js/components/ui/input/Input.vue',
        );
        const { uiCopyMessages } = await server.ssrLoadModule(
            '/resources/js/i18n/ui-copy.ts',
        );
        const render = (props) =>
            renderToString(
                createSSRApp({ render: () => h(Input, props) }).use(
                    createI18n({
                        legacy: false,
                        locale: 'en',
                        messages: { en: { uiCopy: uiCopyMessages.en } },
                    }),
                ),
            );
        const filled = await render({
            type: 'search',
            modelValue: 'TEST',
            'aria-label': 'Search history',
            id: 'history',
            class: 'h-11 pl-11',
        });
        assert.equal((filled.match(/<button/g) ?? []).length, 1);
        assert.match(filled, /aria-label="Clear search"/);
        assert.match(filled, /aria-label="Search history"/);
        assert.match(filled, /id="history"/);
        assert.match(filled, /type="button"/);
        assert.match(filled, /pr-10/);
        assert.match(filled, /value="TEST"/);

        for (const props of [
            { type: 'search', modelValue: '' },
            { type: 'search', modelValue: 'TEST', disabled: true },
            { type: 'search', modelValue: 'TEST', readonly: true },
            { type: 'password', modelValue: 'TEST' },
        ]) {
            assert.doesNotMatch(await render(props), /<button/);
        }

        assert.doesNotMatch(
            await render({ type: 'text', modelValue: 'value' }),
            /data-slot="search-input"/,
        );
    } finally {
        await server.close();
    }
});
