import assert from 'node:assert/strict';
import test from 'node:test';
import {
    HEADER_INFO_EXPANDED_STORAGE_KEY,
    persistHeaderInfoExpanded,
    readHeaderInfoExpanded,
} from '../../resources/js/lib/header-preferences.js';

test('header info expanded preference stores a minimal boolean payload', () => {
    const storage = new Map();

    global.window = {
        localStorage: {
            getItem(key) {
                return storage.has(key) ? storage.get(key) : null;
            },
            setItem(key, value) {
                storage.set(key, value);
            },
        },
    };

    persistHeaderInfoExpanded(false);

    assert.equal(storage.get(HEADER_INFO_EXPANDED_STORAGE_KEY), 'false');
    assert.equal(readHeaderInfoExpanded(), false);

    persistHeaderInfoExpanded(true);

    assert.equal(storage.get(HEADER_INFO_EXPANDED_STORAGE_KEY), 'true');
    assert.equal(readHeaderInfoExpanded(), true);

    delete global.window;
});

test('header info expanded preference ignores quota failures without throwing', async () => {
    global.window = {
        localStorage: {
            getItem() {
                return 'true';
            },
            setItem() {
                throw new DOMException(
                    'The quota has been exceeded',
                    'QuotaExceededError',
                );
            },
        },
    };

    await assert.doesNotReject(async () => {
        persistHeaderInfoExpanded(false);
    });

    assert.equal(readHeaderInfoExpanded(), true);

    delete global.window;
});

test('header info expanded preference falls back when storage cannot be read', () => {
    global.window = {
        localStorage: {
            getItem() {
                throw new DOMException(
                    'Storage is unavailable',
                    'SecurityError',
                );
            },
            setItem() {},
        },
    };

    assert.doesNotThrow(() => {
        assert.equal(readHeaderInfoExpanded(), true);
    });

    delete global.window;
});
