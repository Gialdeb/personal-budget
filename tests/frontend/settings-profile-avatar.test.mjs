import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const profileSource = readFileSync(
    new URL('../../resources/js/pages/settings/Profile.vue', import.meta.url),
    'utf8',
);

const cropDialogSource = readFileSync(
    new URL(
        '../../resources/js/components/profile/ProfileAvatarCropDialog.vue',
        import.meta.url,
    ),
    'utf8',
);

test('settings profile renders avatar management section', () => {
    assert.match(profileSource, /settings\.profile\.avatar\.title/);
    assert.match(profileSource, /settings\.profile\.avatar\.upload/);
    assert.match(profileSource, /settings\.profile\.avatar\.remove/);
    assert.match(profileSource, /ProfileAvatarCropDialog/);
});

test('settings profile crop dialog exposes refinement controls', () => {
    assert.match(cropDialogSource, /settings\.profile\.avatar\.crop\.title/);
    assert.match(cropDialogSource, /settings\.profile\.avatar\.crop\.zoom/);
    assert.match(cropDialogSource, /settings\.profile\.avatar\.crop\.dragHint/);
    assert.match(cropDialogSource, /@pointerdown="startDragging"/);
    assert.match(cropDialogSource, /canvas\.toBlob/);
});

test('settings profile refreshes shared auth payload after avatar save', () => {
    assert.match(profileSource, /router\.reload\(\{/);
    assert.match(profileSource, /only: \['auth'\]/);
});

test('settings profile uses upload copy for the avatar action button', () => {
    assert.doesNotMatch(profileSource, /settings\.profile\.avatar\.replace/);
    assert.match(profileSource, /settings\.profile\.avatar\.upload/);
});
