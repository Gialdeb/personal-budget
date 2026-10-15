import assert from 'node:assert/strict';
import test from 'node:test';
import {
    validationMessages,
    notificationFlash,
    isNotificationPreview,
    bindNotificationFeedback,
} from '../../resources/js/lib/notification-feedback.js';

test('flattens validation bags and hidden translated field errors without repeating messages', () => {
    assert.deepEqual(
        validationMessages({
            email: ['Invalid email'],
            bag: {
                hidden_id: 'Choose an account',
                'translations.en.title': 'Required',
            },
            repeat: 'Required',
            null: null,
        }),
        ['Invalid email', 'Choose an account', 'Required'],
    );
    assert.deepEqual(validationMessages({}), []);
});
test('maps flash levels and excludes non-text payloads', () => {
    assert.deepEqual(
        notificationFlash({
            success: 'Saved',
            warn: 'Check',
            info: 'Queued',
            status: 'Ready',
            other: 'Ignored',
            error: {},
        }),
        [
            { type: 'success', message: 'Saved' },
            { type: 'warning', message: 'Check' },
            { type: 'info', message: 'Queued' },
            { type: 'info', message: 'Ready' },
        ],
    );
    assert.equal(
        isNotificationPreview('/transactions/2026/10/exchange-preview'),
        true,
    );
    assert.equal(
        isNotificationPreview(
            '/transactions/2026/10/balance-adjustment-preview',
        ),
        true,
    );
    assert.equal(isNotificationPreview('/categories'), false);
});
function fixture() {
    const callbacks = new Map();
    const calls = [];
    const feedback = Object.fromEntries(
        ['start', 'finish', 'success', 'failure', 'validation', 'message'].map(
            (key) => [key, (...args) => calls.push([key, ...args])],
        ),
    );
    const stop = bindNotificationFeedback(
        {
            on: (name, callback) => {
                callbacks.set(name, callback);

                return () => callbacks.delete(name);
            },
        },
        feedback,
    );

    return {
        calls,
        stop,
        emit(name, detail) {
            let prevented = false;
            callbacks.get(name)?.({
                detail,
                preventDefault() {
                    prevented = true;
                },
            });

            return prevented;
        },
    };
}
test('GET visits stay silent while successful writes use contextual flash without a second success', () => {
    const f = fixture();
    f.emit('start', { visit: { method: 'get' } });
    f.emit('success', { page: { props: {} } });
    assert.deepEqual(f.calls, []);
    const visit = { method: 'post' };
    f.emit('start', { visit });
    f.emit('success', {
        page: { props: { flash: { success: 'Transaction saved' } } },
    });
    f.emit('finish', { visit });
    assert.deepEqual(f.calls, [
        ['start'],
        ['message', 'success', 'Transaction saved'],
        ['finish'],
    ]);
    f.calls.length = 0;
    f.emit('success', {
        page: { props: { flash: { success: 'Transaction saved' } } },
    });
    assert.deepEqual(f.calls, []);
});
test('validation, failed responses, offline operations and cancellations produce no false success', () => {
    const f = fixture();
    const visit = { method: 'patch' };
    f.emit('start', { visit });
    f.emit('error', { errors: { hidden: 'Required' } });
    assert.equal(f.emit('invalid', { response: { status: 500 } }), true);
    assert.equal(
        f.emit('exception', { exception: new Error('offline') }),
        true,
    );
    f.emit('finish', { visit });
    assert.deepEqual(f.calls, [
        ['start'],
        ['validation', { hidden: 'Required' }],
        ['failure', 500],
        ['failure', 0],
        ['finish'],
    ]);
    f.stop();
    assert.equal(f.emit('invalid', { response: { status: 500 } }), false);
});
test('writes without flash receive a success fallback and concurrent finishes keep progress alive', () => {
    const f = fixture();
    const a = { method: 'delete' },
        b = { method: 'patch' };
    f.emit('start', { visit: a });
    f.emit('start', { visit: b });
    f.emit('success', { page: { props: {} } });
    f.emit('finish', { visit: a });
    assert.equal(
        f.calls.some((call) => call[0] === 'finish'),
        false,
    );
    f.emit('finish', { visit: b });
    assert.ok(f.calls.some((call) => call[0] === 'success'));
    assert.equal(f.calls.at(-1)[0], 'finish');
});

test('notification configuration enforces the requested shared behavior', async () => {
    const { notificationConfig } =
        await import('../../resources/js/lib/notification-config.js');
    assert.equal(notificationConfig.position, 'top-right');
    assert.equal(notificationConfig.limit, 3);
    assert.equal(notificationConfig.avoidDuplicates, true);
    assert.equal(notificationConfig.pauseOnTouch, true);
    assert.equal(notificationConfig.enqueue, true);
});

test('fallback success messages reflect the saved domain', async () => {
    const { mutationSuccessCopy } =
        await import('../../resources/js/lib/notification-feedback.js');
    assert.equal(
        mutationSuccessCopy('post', '/transactions/2026/10'),
        'Movimento salvato correttamente.',
    );
    assert.equal(
        mutationSuccessCopy('patch', '/recurring-entries/test'),
        'Ricorrenza aggiornata correttamente.',
    );
    assert.equal(mutationSuccessCopy('post', '/login'), 'Accesso effettuato.');
    assert.equal(
        mutationSuccessCopy('delete', '/categories/test'),
        'Eliminazione completata.',
    );
});

test('a valid Inertia server-error page never produces a save success', () => {
    const f = fixture();
    f.emit('start', { visit: { method: 'post' } });
    f.emit('success', {
        page: { component: 'Error', props: { message: 'Server error' } },
    });
    assert.deepEqual(f.calls, [['start'], ['failure', 500]]);
});

test('foreground navigation failures are visible while background failures stay quiet', () => {
    const f = fixture();
    const visit = { method: 'get', async: false };
    f.emit('start', { visit });
    assert.equal(f.emit('invalid', { response: { status: 500 } }), true);
    f.emit('finish', { visit });
    f.calls.length = 0;
    f.emit('start', { visit: { method: 'get', async: true } });
    assert.equal(
        f.emit('exception', { exception: new Error('background') }),
        false,
    );
    assert.deepEqual(f.calls, []);
});
