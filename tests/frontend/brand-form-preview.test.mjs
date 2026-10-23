import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@vue/compiler-sfc';
import { transformSync } from 'esbuild';
import { computed, effectScope, nextTick, reactive, ref, watch } from 'vue';

function harness(fetcher) {
    const source = readFileSync(
        'resources/js/components/admin/BrandFormPreview.vue',
        'utf8',
    );
    const script = parse(source).descriptor.scriptSetup.content.replace(
        /^import[\s\S]*?from ['"][^'"]+['"];$/gm,
        '',
    );
    const props = reactive({
        name: 'Apple Music',
        domain: '',
        category: '',
        aliases: '',
        published: true,
        mode: 'website',
        storedLogo: null,
        uploadLogo: '',
        sourceLogo: null,
    });
    const requests = [];
    const context = {
        computed,
        ref,
        watch,
        AbortController,
        setTimeout,
        clearTimeout,
        defineProps: () => props,
        useUiCopy: () => (text) => text,
        preview: { url: () => '/preview' },
        document: { querySelector: () => null },
        fetch: async (url, options) => {
            requests.push({ url, ...options });

            return fetcher(options);
        },
    };
    const scope = effectScope();
    scope.run(() =>
        runInNewContext(
            transformSync(
                script +
                    '\nglobalThis.result = {initials,logo,loading,unavailable,imageFailed,displayName};',
                { loader: 'ts' },
            ).code,
            context,
        ),
    );

    return { ...context.result, props, requests, close: () => scope.stop() };
}
const wait = () => new Promise((resolve) => setTimeout(resolve, 280));

test('brand preview follows name and logo choices with initials when no image is selected', async () => {
    const h = harness(() => ({
        ok: true,
        json: async () => ({ logo_url: 'website-logo' }),
    }));
    assert.equal(h.initials.value, 'AM');
    assert.equal(h.logo.value, '');
    h.props.name = 'Decò';
    assert.equal(h.initials.value, 'D');
    h.props.domain = 'https://example.com';
    await wait();
    assert.equal(h.logo.value, 'website-logo');
    assert.equal(
        JSON.parse(h.requests[0].body).website_url,
        'https://example.com',
    );
    h.props.mode = 'upload';
    h.props.uploadLogo = 'blob:custom';
    await nextTick();
    assert.equal(h.logo.value, 'blob:custom');
    h.props.mode = 'keep';
    h.props.sourceLogo = 'imported-logo';
    await nextTick();
    assert.equal(h.logo.value, 'imported-logo');
    h.props.sourceLogo = null;
    h.props.storedLogo = 'stored-logo';
    await nextTick();
    assert.equal(h.logo.value, 'stored-logo');
    h.props.mode = 'remove';
    await nextTick();
    assert.equal(h.logo.value, '');
    h.close();
});

test('brand preview ignores obsolete responses and cancels work on close', async () => {
    const resolvers = [];
    const h = harness(() => new Promise((resolve) => resolvers.push(resolve)));
    h.props.domain = 'old.example';
    await wait();
    h.props.domain = 'new.example';
    await nextTick();
    assert.equal(h.requests[0].signal.aborted, true);
    assert.equal(h.logo.value, '');
    await wait();
    resolvers[1]({ ok: true, json: async () => ({ logo_url: 'latest' }) });
    await new Promise((resolve) => setTimeout(resolve, 0));
    resolvers[0]({ ok: true, json: async () => ({ logo_url: 'obsolete' }) });
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(h.logo.value, 'latest');
    assert.equal(h.loading.value, false);
    h.props.domain = 'close.example';
    await nextTick();
    h.close();
    await wait();
    assert.equal(h.requests.length, 2);
});

test('missing logos and failed requests keep the initials fallback', async () => {
    for (const response of [
        { ok: true, json: async () => ({ logo_url: null }) },
        { ok: false },
    ]) {
        const h = harness(() => response);
        h.props.domain = 'no-logo.example';
        await wait();
        assert.equal(h.logo.value, '');
        assert.equal(h.unavailable.value, true);
        assert.equal(h.initials.value, 'AM');
        assert.equal(h.loading.value, false);
        h.close();
    }
});
