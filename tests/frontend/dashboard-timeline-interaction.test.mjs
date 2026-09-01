import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../', import.meta.url);

async function interactionModule() {
    const source = await readFile(
        new URL('resources/js/lib/dashboard-timeline-interaction.ts', root),
        'utf8',
    );
    const moduleSource = source
        .replace(/type TimelineClickParams = \{[\s\S]*?};\n\n/, '')
        .replace(/<T>/g, '')
        .replace(/: TimelineClickParams/g, '')
        .replace(/: T\[]/g, '')
        .replace(/: T \| null/g, '')
        .replace(/: number \| null/g, '')
        .replace(/: number/g, '')
        .replace(/: boolean/g, '');

    return import(`data:text/javascript,${encodeURIComponent(moduleSource)}`);
}

test('centres the selected mobile month while respecting January and December edges', async () => {
    const { selectedMonthScrollLeft } = await interactionModule();

    assert.equal(selectedMonthScrollLeft(32, 360, 736), 0);
    assert.equal(selectedMonthScrollLeft(456, 360, 736), 276);
    assert.equal(selectedMonthScrollLeft(704, 360, 736), 376);
});

test('clicking a monthly bar resolves the exact clicked month, including March and September', async () => {
    const { resolveTimelineClickPoint } = await interactionModule();

    const points = Array.from({ length: 12 }, (_unused, index) => ({
        key: `2026-${String(index + 1).padStart(2, '0')}`,
        month: index + 1,
    }));

    const marchClick = resolveTimelineClickPoint(
        { componentType: 'series', seriesType: 'bar', dataIndex: 2 },
        points,
    );
    assert.equal(marchClick?.month, 3);

    const septemberClick = resolveTimelineClickPoint(
        { componentType: 'series', seriesType: 'bar', dataIndex: 8 },
        points,
    );
    assert.equal(septemberClick?.month, 9);
});

test('only the bar itself is clickable, not axis labels or empty canvas areas', async () => {
    const { resolveTimelineClickPoint } = await interactionModule();

    const points = Array.from({ length: 12 }, (_unused, index) => ({
        key: `2026-${String(index + 1).padStart(2, '0')}`,
        month: index + 1,
    }));

    assert.equal(
        resolveTimelineClickPoint(
            { componentType: 'xAxis', dataIndex: 2 },
            points,
        ),
        null,
    );
    assert.equal(
        resolveTimelineClickPoint({ componentType: 'series' }, points),
        null,
    );
});

test('financial timeline selects a month by wiring the native ECharts bar click event, without coordinate math', async () => {
    const timeline = await readFile(
        new URL(
            'resources/js/components/dashboard/FinancialTimeline.vue',
            root,
        ),
        'utf8',
    );

    assert.match(timeline, /chart\.value\.on\('click', onTimelineChartClick\)/);
    assert.match(
        timeline,
        /resolveTimelineClickPoint\(params, props\.points\)/,
    );
    assert.match(timeline, /emit\('select', point\)/);
    assert.match(timeline, /scrollSelectedMonthIntoView/);
    assert.doesNotMatch(timeline, /convertFromPixel/);
    assert.doesNotMatch(timeline, /pointerdown\.capture/);
    assert.doesNotMatch(timeline, /pointerup\.capture/);
});
