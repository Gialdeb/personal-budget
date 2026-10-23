import assert from 'node:assert/strict';
import test from 'node:test';
import {
    userErrorMessage,
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

test('local errors and previews stay quiet until a form is actually submitted', async () => {
    const { readFileSync } = await import('node:fs');
    const inputError = readFileSync(
        new URL(
            '../../resources/js/components/InputError.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const form = readFileSync(
        new URL(
            '../../resources/js/composables/useNotifiedForm.ts',
            import.meta.url,
        ),
        'utf8',
    );
    assert.doesNotMatch(inputError, /notifyValidation|watch\(/);
    assert.doesNotMatch(form, /notifyValidation|setError\s*=/);
    const f = fixture();
    const preview = {
        method: 'post',
        url: '/transactions/2026/10/exchange-preview',
        async: true,
    };
    f.emit('start', { visit: preview });
    f.emit('error', { errors: { amount: 'Invalid' } });
    f.emit('success', {
        page: { props: { flash: { success: 'Unexpected' } } },
    });
    assert.deepEqual(f.calls, []);
});

test('reference helpers prepare drafts silently rather than persisting from a selection', async () => {
    const { isReferenceDraftRequest } =
        await import('../../resources/js/lib/notification-feedback.js');
    assert.equal(isReferenceDraftRequest('/transactions/tracked-items'), true);
    assert.equal(
        isReferenceDraftRequest('/recurring-entries/tracked-items'),
        true,
    );
    assert.equal(
        isReferenceDraftRequest('/shared-expenses/counterparties'),
        true,
    );
    assert.equal(isReferenceDraftRequest('/settings/tracked-items'), false);
    const { readFileSync } = await import('node:fs');
    const requests = readFileSync(
        new URL('../../resources/js/lib/notifications.ts', import.meta.url),
        'utf8',
    );
    assert.match(requests, /draft: true/);
    assert.match(requests, /notification === 'silent' \|\| preview/);
    const counterparties = readFileSync(
        new URL(
            '../../resources/js/components/shared-expenses/CounterpartySelect.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const selection = counterparties.slice(
        counterparties.indexOf('function selectCounterparty'),
    );
    assert.doesNotMatch(
        selection.split('</script>')[0],
        /createCounterparty\(/,
    );
});

test('technical identifiers are replaced while meaningful field errors remain specific', async () => {
    const { userErrorMessage } =
        await import('../../resources/js/lib/notification-feedback.js');
    assert.equal(
        userErrorMessage(
            'Il campo tracked_item_uuid deve essere un UUID valido.',
        ),
        'La selezione per riferimento non è valida. Seleziona nuovamente l’elemento.',
    );
    assert.match(
        userErrorMessage('category_uuid non disponibile'),
        /categoria non disponibile/,
    );
    assert.doesNotMatch(
        userErrorMessage('SQLSTATE[23000] transactions.tracked_item_id'),
        /SQLSTATE|tracked_item_id/,
    );
    assert.equal(
        userErrorMessage('L’importo deve essere maggiore di zero.'),
        'L’importo deve essere maggiore di zero.',
    );
    assert.doesNotMatch(
        userErrorMessage(
            'Riferimento 7341f423-87e2-484c-9a12-821f0e8cbdef non disponibile',
        ),
        /7341f423/,
    );
});

test('fetch helper sends read only draft requests and keeps previews quiet but acknowledges a submitted form', async () => {
    const { readFileSync } = await import('node:fs');
    const { default: typescript } = await import('typescript');
    const { transpileModule, ModuleKind } = typescript;
    const feedbackUrl = new URL(
        '../../resources/js/lib/notification-feedback.js',
        import.meta.url,
    ).href;
    const source = readFileSync(
        new URL('../../resources/js/lib/notifications.ts', import.meta.url),
        'utf8',
    );
    let code = transpileModule(source, {
        compilerOptions: { module: ModuleKind.ESNext },
    }).outputText;
    const notices = [];
    const requests = [];
    globalThis.__formFeedbackPush = Object.fromEntries(
        ['success', 'warning', 'error', 'info', 'promise'].map((type) => [
            type,
            (item) => {
                notices.push([type, item]);

                return { clear() {} };
            },
        ]),
    );
    code = code
        .replace(
            "import { push } from 'notivue';",
            'const push = globalThis.__formFeedbackPush;',
        )
        .replaceAll(
            "'./notification-feedback.js'",
            JSON.stringify(feedbackUrl),
        );
    const module = await import(
        'data:text/javascript;base64,' + Buffer.from(code).toString('base64')
    );
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async (url, init) => {
        requests.push([url, init]);

        return new Response(
            JSON.stringify(
                url.includes('preview')
                    ? { errors: { amount: ['Importo non valido'] } }
                    : { message: 'Salvato' },
            ),
            {
                status: url.includes('preview') ? 422 : 200,
                headers: { 'Content-Type': 'application/json' },
            },
        );
    };

    try {
        module.configureNotifications((text) => text);
        await module.fetchWithNotifications('/transactions/tracked-items', {
            method: 'POST',
            body: JSON.stringify({ name: 'Provvisorio' }),
        });
        assert.equal(JSON.parse(requests[0][1].body).draft, true);
        assert.deepEqual(notices, []);
        await module.fetchWithNotifications(
            '/transactions/2026/10/exchange-preview',
            { method: 'POST' },
        );
        assert.deepEqual(notices, []);
        await module.fetchWithNotifications('/transactions/2026/10', {
            method: 'POST',
        });
        assert.equal(notices.length, 1);
        assert.equal(notices[0][0], 'success');
    } finally {
        globalThis.fetch = previousFetch;
        delete globalThis.__formFeedbackPush;
    }
});

test('error sanitization uses English labels and fallback messages for English pages', () => {
    const previous = globalThis.document;
    globalThis.document = { documentElement: { lang: 'en' } };

    try {
        assert.equal(
            userErrorMessage('Failed to fetch'),
            'Connection unavailable. Check your network and try again.',
        );
        assert.equal(
            userErrorMessage('The account_uuid field is required.'),
            'The account field is required.',
        );
        assert.equal(
            userErrorMessage('Invalid UUID'),
            'The selection is invalid. Search and select the item again before saving.',
        );
        assert.equal(
            userErrorMessage('TypeError: broken'),
            'Unable to complete this operation. Try again; if the problem persists, contact support.',
        );
    } finally {
        if (previous === undefined) {
            delete globalThis.document;
        } else {
            globalThis.document = previous;
        }
    }
});
