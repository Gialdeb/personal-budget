import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const indexSource = readFileSync(
    new URL('../../resources/js/pages/admin/Automation/Index.vue', import.meta.url),
    'utf8',
);
const showSource = readFileSync(
    new URL('../../resources/js/pages/admin/Automation/Show.vue', import.meta.url),
    'utf8',
);
const overviewSource = readFileSync(
    new URL('../../resources/js/components/admin/automation/AutomationPipelineOverview.vue', import.meta.url),
    'utf8',
);
const filtersSource = readFileSync(
    new URL('../../resources/js/components/admin/automation/AutomationFilters.vue', import.meta.url),
    'utf8',
);
const tableSource = readFileSync(
    new URL('../../resources/js/components/admin/automation/AutomationRunsTable.vue', import.meta.url),
    'utf8',
);

test('automation admin index wires overview, filters, and runs table', () => {
    assert.match(indexSource, /AutomationPipelineOverview/);
    assert.match(indexSource, /AutomationFilters/);
    assert.match(indexSource, /AutomationRunsTable/);
    assert.match(indexSource, /admin\.automation\.title/);
    assert.match(indexSource, /admin\.automation\.description/);
    assert.match(indexSource, /runAutomationPipeline/);
    assert.match(indexSource, /retryAutomationRun/);
});

test('automation admin index refreshes statuses and runs after run and retry actions', () => {
    assert.match(indexSource, /router\.reload\(\{\s*only: refreshOnly/);
    assert.match(indexSource, /const refreshOnly = \['runs', 'statuses'\]/);
    assert.match(indexSource, /setInterval\(\(\) => \{\s*reloadAutomationData\(\);/);
    assert.match(indexSource, /setTimeout\(\(\) => \{\s*stopRefreshPolling\(\);/);
    assert.match(indexSource, /onSuccess: \(\) => \{\s*reloadAutomationData\(\);\s*startRefreshPolling\(\);/);
});

test('automation overview exposes pipeline health labels and manual run action', () => {
    assert.match(overviewSource, /admin\.automation\.overview\.title/);
    assert.match(overviewSource, /admin\.automation\.overview\.latestRun/);
    assert.match(overviewSource, /admin\.automation\.overview\.latestTrigger/);
    assert.match(overviewSource, /admin\.automation\.overview\.latestDuration/);
    assert.match(overviewSource, /admin\.automation\.actions\.runNow/);
    assert.match(overviewSource, /pipeline\.critical/);
    assert.match(overviewSource, /pipeline\.enabled/);
});

test('automation filters expose pipeline, status, and trigger controls', () => {
    assert.match(filtersSource, /admin\.automation\.filters\.pipelineLabel/);
    assert.match(filtersSource, /admin\.automation\.filters\.statusLabel/);
    assert.match(filtersSource, /admin\.automation\.filters\.triggerLabel/);
    assert.match(filtersSource, /admin\.automation\.filters\.reset/);
    assert.match(filtersSource, /<Select/);
});

test('automation runs table renders retry only when backend marks the run as retryable', () => {
    assert.match(tableSource, /admin\.automation\.table\.pipeline/);
    assert.match(tableSource, /admin\.automation\.table\.status/);
    assert.match(tableSource, /admin\.automation\.actions\.runInfo/);
    assert.match(tableSource, /v-if="run\.is_retryable"/);
    assert.match(tableSource, /automationShow/);
});

test('automation show page renders summary, metrics, and readable payload sections', () => {
    assert.match(showSource, /admin\.automation\.show\.sections\.summary/);
    assert.match(showSource, /admin\.automation\.show\.sections\.metrics/);
    assert.match(showSource, /admin\.automation\.show\.sections\.errorDetails/);
    assert.match(showSource, /admin\.automation\.show\.sections\.context/);
    assert.match(showSource, /admin\.automation\.show\.sections\.result/);
    assert.match(showSource, /prettyPayload/);
    assert.match(showSource, /props\.run\.is_retryable/);
});
