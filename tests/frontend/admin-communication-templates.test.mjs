import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const indexSource = readFileSync(
    new URL('../../resources/js/pages/admin/CommunicationTemplates/Index.vue', import.meta.url),
    'utf8',
);
const showSource = readFileSync(
    new URL('../../resources/js/pages/admin/CommunicationTemplates/Show.vue', import.meta.url),
    'utf8',
);
const editSource = readFileSync(
    new URL('../../resources/js/pages/admin/CommunicationTemplates/Edit.vue', import.meta.url),
    'utf8',
);
const listSource = readFileSync(
    new URL('../../resources/js/components/admin/communication-templates/CommunicationTemplatesList.vue', import.meta.url),
    'utf8',
);
const filtersSource = readFileSync(
    new URL('../../resources/js/components/admin/communication-templates/CommunicationTemplateFilters.vue', import.meta.url),
    'utf8',
);
const layoutSource = readFileSync(
    new URL('../../resources/js/layouts/admin/Layout.vue', import.meta.url),
    'utf8',
);

test('admin communication templates index wires filters and paginated list', () => {
    assert.match(indexSource, /CommunicationTemplatesList/);
    assert.match(indexSource, /CommunicationTemplateFilters/);
    assert.match(indexSource, /admin\.communicationTemplates\.title/);
    assert.match(indexSource, /search/);
    assert.match(indexSource, /template_mode/);
    assert.match(indexSource, /override_state/);
});

test('admin communication templates list has dedicated mobile cards desktop table and pagination', () => {
    assert.match(listSource, /md:hidden/);
    assert.match(listSource, /md:block/);
    assert.match(listSource, /admin\.communicationTemplates\.actions\.open/);
    assert.match(listSource, /admin\.communicationTemplates\.actions\.editOverride/);
    assert.match(listSource, /admin\.communicationTemplates\.actions\.disableOverride/);
    assert.match(listSource, /admin\.communicationTemplates\.pagination\.page/);
});

test('admin communication templates show exposes base override and preview sections', () => {
    assert.match(showSource, /admin\.communicationTemplates\.detail\.sections\.general/);
    assert.match(showSource, /admin\.communicationTemplates\.detail\.sections\.base/);
    assert.match(showSource, /admin\.communicationTemplates\.detail\.sections\.override/);
    assert.match(showSource, /admin\.communicationTemplates\.detail\.sections\.resolved/);
    assert.match(showSource, /admin\.communicationTemplates\.detail\.sections\.preview/);
});

test('admin communication template edit page exposes live preview and editable fields', () => {
    assert.match(editSource, /subject_template/);
    assert.match(editSource, /title_template/);
    assert.match(editSource, /body_template/);
    assert.match(editSource, /cta_label_template/);
    assert.match(editSource, /cta_url_template/);
    assert.match(editSource, /is_active/);
    assert.match(editSource, /livePreview/);
    assert.match(editSource, /admin\.communicationTemplates\.edit\.sections\.preview/);
});

test('admin communication template filters expose search and server-side filter controls', () => {
    assert.match(filtersSource, /admin\.communicationTemplates\.filters\.searchLabel/);
    assert.match(filtersSource, /admin\.communicationTemplates\.filters\.channelLabel/);
    assert.match(filtersSource, /admin\.communicationTemplates\.filters\.templateModeLabel/);
    assert.match(filtersSource, /admin\.communicationTemplates\.filters\.overrideStateLabel/);
    assert.match(filtersSource, /admin\.communicationTemplates\.filters\.lockStateLabel/);
});

test('admin layout exposes communication templates navigation entry', () => {
    assert.match(layoutSource, /admin\.sections\.communicationTemplates/);
    assert.match(layoutSource, /communicationTemplatesIndex/);
});
