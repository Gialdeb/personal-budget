import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const banksSource = readFileSync(
    new URL('../../resources/js/pages/settings/Banks.vue', import.meta.url),
    'utf8',
);
const accountsSource = readFileSync(
    new URL('../../resources/js/pages/settings/Accounts.vue', import.meta.url),
    'utf8',
);
const profileSource = readFileSync(
    new URL('../../resources/js/pages/settings/Profile.vue', import.meta.url),
    'utf8',
);
const pushBroadcastsSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/PushBroadcasts/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const toastStackSource = readFileSync(
    new URL('../../resources/js/components/ui/AppToastStack.vue', import.meta.url),
    'utf8',
);
const toastComposableSource = readFileSync(
    new URL(
        '../../resources/js/composables/useToastFeedback.ts',
        import.meta.url,
    ),
    'utf8',
);
const bankSelectSource = readFileSync(
    new URL(
        '../../resources/js/components/banks/BankSearchSelect.vue',
        import.meta.url,
    ),
    'utf8',
);

test('mobile-friendly toast layer is shared across high-value settings and admin pages', () => {
    assert.match(toastStackSource, /fixed inset-x-4 bottom-4/);
    assert.match(toastStackSource, /sm:right-6 sm:bottom-6 sm:w-full sm:max-w-sm/);
    assert.match(toastComposableSource, /showFeedback/);
    assert.match(banksSource, /AppToastStack :items="\[feedback\]"/);
    assert.match(accountsSource, /AppToastStack :items="\[feedback\]"/);
    assert.match(profileSource, /AppToastStack/);
    assert.match(pushBroadcastsSource, /AppToastStack :items="\[feedback\]"/);
});

test('bank selection UI uses display_name fallback when available', () => {
    assert.match(banksSource, /display_name: option\.display_name/);
    assert.match(banksSource, /bank\.display_name \?\? bank\.name/);
    assert.match(bankSelectSource, /option\.display_name \?\? option\.name/);
});
