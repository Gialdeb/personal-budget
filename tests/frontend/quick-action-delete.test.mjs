import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(
    new URL(
        '../../resources/js/pages/settings/QuickActions.vue',
        import.meta.url,
    ),
    'utf8',
);
const handlers = source
    .slice(
        source.indexOf('function remove('),
        source.indexOf('const localTemplates'),
    )
    .replace(/: Template/g, '')
    .replace(/: void/g, '');

function fixture() {
    const visits = [];
    const context = {
        deletingTemplate: { value: null },
        deleting: { value: false },
        deleteError: { value: '' },
        ui: (message) => message,
        destroy: { url: (uuid) => `/templates/${uuid}` },
        router: { delete: (url, options) => visits.push({ url, options }) },
    };
    runInNewContext(handlers, context);

    return { context, visits };
}

test('delete opens the existing named confirmation dialog without making a request', () => {
    const { context, visits } = fixture();
    context.remove({ uuid: 'deco', name: 'Decò' });
    assert.equal(context.deletingTemplate.value.name, 'Decò');
    assert.equal(visits.length, 0);
    assert.doesNotMatch(source, /window\.confirm/);
    assert.match(source, /from '@\/components\/ui\/dialog'/);
    assert.match(source, /Eliminare questa azione rapida\?/);
    assert.match(source, /name: deletingTemplate\?\.name/);
    assert.match(source, /variant="destructive"[\s\S]*?@click="confirmDelete"/);
});

test('cancel clears confirmation and never deletes', () => {
    const { context, visits } = fixture();
    context.remove({ uuid: 'deco', name: 'Decò' });
    context.closeDeleteDialog();
    context.confirmDelete();
    assert.equal(context.deletingTemplate.value, null);
    assert.equal(visits.length, 0);
});

test('confirmation submits once and blocks dismissal while processing', () => {
    const { context, visits } = fixture();
    context.remove({ uuid: 'deco', name: 'Decò' });
    context.confirmDelete();
    context.confirmDelete();
    context.closeDeleteDialog();
    context.remove({ uuid: 'other', name: 'Other' });
    assert.equal(visits.length, 1);
    assert.equal(visits[0].url, '/templates/deco');
    assert.equal(visits[0].options.preserveScroll, true);
    assert.equal(context.deleting.value, true);
    assert.equal(context.deletingTemplate.value.uuid, 'deco');
    assert.match(
        source,
        /@escape-key-down="deleting && \$event\.preventDefault\(\)"/,
    );
    assert.match(
        source,
        /@interact-outside="deleting && \$event\.preventDefault\(\)"/,
    );
    assert.match(source, /:show-close-button="!deleting"/);
    assert.match(source, /:aria-busy="deleting"/);
});

test('server errors stay visible in the open dialog and allow retry', () => {
    const { context, visits } = fixture();
    context.remove({ uuid: 'deco', name: 'Decò' });
    context.confirmDelete();
    visits[0].options.onError({ delete: 'Eliminazione non riuscita' });
    visits[0].options.onFinish();
    assert.equal(context.deletingTemplate.value.uuid, 'deco');
    assert.equal(context.deleteError.value, 'Eliminazione non riuscita');
    assert.equal(context.deleting.value, false);
    assert.match(source, /v-if="deleteError"[\s\S]*?role="alert"/);
    context.confirmDelete();
    assert.equal(visits.length, 2);
    assert.equal(context.deleteError.value, '');
});

test('unsuccessful or cancelled visits show a generic error rather than silently closing', () => {
    const { context, visits } = fixture();
    context.remove({ uuid: 'deco', name: 'Decò' });
    context.confirmDelete();
    visits[0].options.onFinish();
    assert.ok(context.deletingTemplate.value);
    assert.match(context.deleteError.value, /Impossibile eliminare/);
    assert.equal(context.deleting.value, false);
});

test('success closes confirmation and the list follows updated Inertia props', () => {
    const { context, visits } = fixture();
    context.remove({ uuid: 'deco', name: 'Decò' });
    context.confirmDelete();
    visits[0].options.onSuccess();
    visits[0].options.onFinish();
    assert.equal(context.deletingTemplate.value, null);
    assert.equal(context.deleting.value, false);
    assert.equal(context.deleteError.value, '');
    assert.match(
        source,
        /\(\) => props\.templates,[\s\S]*?localTemplates\.value = value/,
    );
    assert.doesNotMatch(handlers, /reload\(|location\./);
});
