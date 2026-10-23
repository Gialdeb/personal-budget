import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@vue/compiler-sfc';
import { transformSync } from 'esbuild';
import { computed, effectScope, nextTick, reactive, ref, watch } from 'vue';

function categoryHarness(categories) {
    const source = readFileSync(
        'resources/js/components/admin/BrandCategoryManager.vue',
        'utf8',
    );
    const script = parse(source).descriptor.scriptSetup.content.replace(
        /^import[\s\S]*?from ['"][^'"]+['"];$/gm,
        '',
    );
    const module = { exports: {} };
    runInNewContext(
        transformSync(
            readFileSync('resources/js/lib/tracked-item-options.ts', 'utf8'),
            { loader: 'ts', format: 'cjs' },
        ).code,
        { module, exports: module.exports },
    );
    const props = reactive({ categories, selected: ['selected-brand'] });
    const calls = [],
        events = [];
    const context = {
        computed,
        ref,
        watch,
        normalizeReferenceSearch: module.exports.normalizeReferenceSearch,
        defineProps: () => props,
        defineEmits:
            () =>
            (...event) =>
                events.push(event),
        useUiCopy: () => (text) => text,
        useForm: (defaults) => {
            const form = {
                ...structuredClone(defaults),
                processing: false,
                errors: {},
                reset() {
                    Object.assign(this, structuredClone(defaults));
                },
                clearErrors() {
                    this.errors = {};
                },
            };

            for (const method of ['post', 'patch', 'delete']) {
                form[method] = (url, options) =>
                    calls.push({
                        method,
                        url,
                        options,
                        data: JSON.parse(JSON.stringify(form)),
                    });
            }

            return form;
        },
        store: { url: () => '/categories' },
        update: { url: (uuid) => `/categories/${uuid}` },
        destroy: { url: (uuid) => `/categories/${uuid}` },
        move: { url: () => '/move' },
    };
    const scope = effectScope();
    scope.run(() =>
        runInNewContext(
            transformSync(
                script +
                    '\nglobalThis.result = {search,sort,pageSize,page,pageCount,rows,filtered,targets,show,submit,viewBrands,form,confirmed,mode,open,requestError};',
                { loader: 'ts' },
            ).code,
            context,
        ),
    );

    return {
        ...context.result,
        calls,
        events,
        props,
        close: () => scope.stop(),
    };
}

test('category table filters normalized names sorts copies and paginates 10 25 and 50 rows', async () => {
    const categories = Array.from({ length: 63 }, (_, index) => ({
        uuid: `category-${index}`,
        name: `Categoria ${String(index).padStart(3, '0')}`,
        brands_count: index,
    }));
    categories.reverse();
    const before = categories.map((category) => category.uuid);
    const h = categoryHarness(categories);
    assert.equal(h.rows.value.length, 10);
    assert.equal(h.pageCount.value, 7);
    assert.equal(h.rows.value[0].name, 'Categoria 000');
    h.page.value = 7;
    assert.equal(h.rows.value.length, 3);

    for (const size of [25, 50]) {
        h.pageSize.value = size;
        await nextTick();
        assert.equal(h.page.value, 1);
        assert.equal(h.rows.value.length, size);
    }

    h.sort.value = 'desc';
    await nextTick();
    assert.equal(h.rows.value[0].name, 'Categoria 062');
    h.search.value = '  CATEGORÍA   012  ';
    await nextTick();
    assert.equal(h.rows.value.length, 1);
    assert.equal(h.rows.value[0].name, 'Categoria 012');
    h.search.value = 'non presente';
    await nextTick();
    assert.equal(h.rows.value.length, 0);
    assert.equal(h.pageCount.value, 1);
    assert.deepEqual(
        categories.map((category) => category.uuid),
        before,
    );
    h.search.value = '';
    h.pageSize.value = 10;
    await nextTick();
    h.page.value = 7;
    h.props.categories = categories.slice(0, 2);
    await nextTick();
    assert.equal(h.page.value, 1);
    h.close();
});

test('category deletion with transfer requires confirmation and submits a single atomic request with recoverable errors', () => {
    const source = { uuid: 'source', name: 'Origine', brands_count: 25 };
    const h = categoryHarness([
        source,
        { uuid: 'target', name: 'Destinazione', brands_count: 0 },
    ]);
    h.show('delete', source);
    assert.equal(h.targets.value.length, 1);
    h.submit();
    h.form.category_uuid = 'target';
    h.submit();
    assert.equal(h.calls.length, 0);
    h.confirmed.value = true;
    h.submit();
    assert.equal(h.calls.length, 1);
    assert.equal(h.calls[0].url, '/move');
    assert.equal(h.calls[0].data.delete_source, true);
    assert.equal(h.calls[0].data.source_category_uuid, 'source');
    h.calls[0].options.onError();
    assert.match(h.requestError.value, /Impossibile completare/);
    assert.equal(h.mode.value, 'delete');
    h.form.processing = true;
    h.submit();
    assert.equal(h.calls.length, 1);
    h.form.processing = false;
    h.calls[0].options.onSuccess();
    assert.equal(h.mode.value, 'list');
    assert.deepEqual(h.events, [['removed', 'Origine'], ['moved']]);
    h.close();
});

test('category counts open the existing filtered brand list and empty category deletion uses the original endpoint', () => {
    const category = { uuid: 'empty', name: 'Vuota', brands_count: 0 };
    const h = categoryHarness([category]);
    h.show('list');
    h.viewBrands(category);
    assert.equal(h.open.value, false);
    assert.deepEqual(h.events, [['view', 'Vuota']]);
    h.show('delete', category);
    h.submit();
    assert.equal(h.calls[0].method, 'delete');
    assert.equal(h.calls[0].url, '/categories/empty');
    h.close();
});
