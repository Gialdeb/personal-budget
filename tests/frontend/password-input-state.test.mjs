import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const inputSource = readFileSync(
    new URL(
        '../../resources/js/components/ui/input/Input.vue',
        import.meta.url,
    ),
    'utf8',
);

test('input component keeps internal state when used without external v-model', () => {
    assert.match(inputSource, /const internalValue = ref/);
    assert.match(
        inputSource,
        /return props\.modelValue \?\? internalValue\.value/,
    );
    assert.match(inputSource, /internalValue\.value = value;/);
    assert.match(inputSource, /watch\(\s*\(\) => props\.modelValue/);
});
