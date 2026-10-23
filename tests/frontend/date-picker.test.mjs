import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import ts from 'typescript';
const source = readFileSync(
    new URL('../../resources/js/lib/date-picker.ts', import.meta.url),
    'utf8',
);
const js = ts.transpile(source.replace(/export /g, ''), {
    target: ts.ScriptTarget.ES2022,
});
const {
    parseDatePickerValue,
    datePickerValue,
    formatDatePickerValue,
    parseDatePickerInput,
    datePickerValueAllowed,
} = new Function(
    `${js}; return {parseDatePickerValue,datePickerValue,formatDatePickerValue,parseDatePickerInput,datePickerValueAllowed};`,
)();

test('date picker preserves local calendar dates and does not shift dates with timezones', () => {
    for (const timezone of [
        'Europe/Rome',
        'America/Los_Angeles',
        'Pacific/Kiritimati',
    ]) {
        const result = spawnSync(
            process.execPath,
            [
                '-e',
                `${js}; process.stdout.write(datePickerValue(parseDatePickerValue('2026-10-16')) + ' ' + datePickerValue(parseDatePickerValue('2026-10-16T14:30'),true));`,
            ],
            { env: { ...process.env, TZ: timezone }, encoding: 'utf8' },
        );
        assert.equal(result.status, 0);
        assert.equal(result.stdout, '2026-10-16 2026-10-16T14:30');
    }
});

test('date picker accepts leap days and rejects impossible dates and times', () => {
    assert.equal(
        datePickerValue(parseDatePickerValue('2024-02-29')),
        '2024-02-29',
    );

    for (const invalid of [
        '2026-02-29',
        '2026-04-31',
        '2026-13-01',
        '2026-00-10',
        '2026-10-00',
        '2026-10-16T24:00',
        '2026-10-16T14:60',
        'garbage',
    ]) {
        assert.equal(parseDatePickerValue(invalid), null, invalid);
    }
});

test('date picker formats and parses Italian and English while emitting ISO values', () => {
    assert.equal(formatDatePickerValue('2026-10-16', 'it'), '16/10/2026');
    assert.equal(formatDatePickerValue('2026-10-16', 'en'), '10/16/2026');
    assert.equal(parseDatePickerInput('16/10/2026', 'it'), '2026-10-16');
    assert.equal(parseDatePickerInput('10/16/2026', 'en'), '2026-10-16');
    assert.equal(parseDatePickerInput('2026-10-16', 'en'), '2026-10-16');
    assert.equal(
        parseDatePickerInput('16/10/2026 14:30', 'it', true),
        '2026-10-16T14:30',
    );
    assert.equal(
        parseDatePickerInput('10/16/2026 14:30', 'en', true),
        '2026-10-16T14:30',
    );
    assert.equal(parseDatePickerInput('31/02/2026', 'it'), null);
    assert.equal(parseDatePickerInput('16/10/26', 'it'), null);
    assert.equal(parseDatePickerInput('', 'it'), '');
});

test('date picker enforces inclusive date and date-time bounds', () => {
    assert.equal(
        datePickerValueAllowed('2026-10-16', '2026-10-16', '2026-10-16'),
        true,
    );
    assert.equal(
        datePickerValueAllowed('2026-10-15', '2026-10-16', null),
        false,
    );
    assert.equal(
        datePickerValueAllowed('2026-10-17', null, '2026-10-16'),
        false,
    );
    assert.equal(
        datePickerValueAllowed('2026-10-16T14:30', '2026-10-16T14:31', null),
        false,
    );
    assert.equal(
        datePickerValueAllowed('2026-10-16T14:30', null, '2026-10-16'),
        true,
    );
    assert.equal(
        datePickerValueAllowed('2026-10-16T14:30', null, '2026-10-16T14:29'),
        false,
    );
});

function vueFiles(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) =>
        entry.isDirectory()
            ? vueFiles(new URL(`${entry.name}/`, dir))
            : entry.name.endsWith('.vue')
              ? [new URL(entry.name, dir)]
              : [],
    );
}

test('every date and date-time field uses the shared VCalendar component', () => {
    const files = vueFiles(new URL('../../resources/js/', import.meta.url));
    let fields = 0;

    for (const file of files) {
        const contents = readFileSync(file, 'utf8');
        assert.doesNotMatch(
            contents,
            /<(?:input|Input)\b[^>]*type="(?:date|datetime-local)"/s,
            file.pathname,
        );
        const count = (contents.match(/<AppDatePicker\b/g) ?? []).length;
        fields += count;

        if (count) {
            assert.match(
                contents,
                /import AppDatePicker from '@\/components\/AppDatePicker.vue'/,
            );
        }
    }

    assert.ok(fields >= 38);
});

test('shared date picker compiles with left alignment and lazy calendar loading', () => {
    const contents = readFileSync(
        new URL(
            '../../resources/js/components/AppDatePicker.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const { descriptor, errors } = parse(contents);
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, { id: 'app-date-picker' });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename: 'AppDatePicker.vue',
        id: 'app-date-picker',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    assert.match(contents, /title-position="left"/);
    assert.match(contents, /await import\('v-calendar'\)/);
    assert.match(contents, /PopoverPortal/);
    assert.match(contents, /\$attrs/);
    assert.match(contents, /setCustomValidity/);
});

test('global calendar marks only today with a subtle dot that follows the day text color', () => {
    const component = readFileSync(
        new URL(
            '../../resources/js/components/AppDatePicker.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const { descriptor } = parse(component);
    const styles = descriptor.styles.map((style) => style.content).join('\n');
    const dot = styles.match(
        /\.app-date-calendar \.vc-day\.is-today \.vc-day-content::after\s*\{([^}]+)}/,
    )[1];
    assert.match(dot, /width: 3px/);
    assert.match(dot, /height: 3px/);
    assert.match(dot, /background: currentColor/);
    assert.match(dot, /opacity: 0\.7/);
    assert.match(dot, /pointer-events: none/);
    assert.match(
        styles,
        /\.app-date-calendar \.vc-day\.is-today \.vc-day-content\s*\{\s*position: relative/,
    );
});
