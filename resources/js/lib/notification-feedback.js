/** Auxiliary reference choices are provisional until the containing form is submitted. */
export function isReferenceDraftRequest(url) {
    const path = new URL(String(url), 'https://notifications.local').pathname;

    return (
        /\/(?:transactions|recurring-entries)\/tracked-items\/?$/.test(path) ||
        /\/shared-expenses\/counterparties\/?$/.test(path)
    );
}

/** Keep useful validation details while replacing implementation-only identifiers. */
export function userErrorMessage(message) {
    const english =
        typeof document !== 'undefined' &&
        document.documentElement?.lang?.startsWith('en');
    const localized = (it, en) => (english ? en : it);

    if (typeof message !== 'string') {
        return '';
    }

    if (/fetch failed|Failed to fetch|NetworkError/i.test(message)) {
        return localized(
            'Connessione non disponibile. Verifica la rete e riprova.',
            'Connection unavailable. Check your network and try again.',
        );
    }

    if (
        /SQLSTATE|(?:PDO|Query|Type|Reference)Exception|Stack trace|\bat [\w/.-]+\.php:\d+|TypeError|ReferenceError|SyntaxError|Cannot read properties|Unexpected token/i.test(
            message,
        )
    ) {
        return localized(
            'Non è stato possibile completare l’operazione. Riprova; se il problema continua, contatta l’assistenza.',
            'Unable to complete this operation. Try again; if the problem persists, contact support.',
        );
    }

    const labels = {
        shared_expense_counterparty: localized(
            'controparte della spesa condivisa',
            'shared expense counterparty',
        ),
        shared_counterparty: localized('controparte', 'counterparty'),
        destination_account: localized(
            'conto di destinazione',
            'destination account',
        ),
        destination: localized('destinazione', 'destination'),
        source: localized('origine', 'source'),
        tracked_item: localized('riferimento', 'reference'),
        reference: localized('riferimento', 'reference'),
        counterparty: localized('controparte', 'counterparty'),
        account: localized('conto', 'account'),
        category: localized('categoria', 'category'),
        scope: localized('ambito', 'scope'),
        parent: localized('riferimento superiore', 'parent reference'),
        transaction: localized('movimento', 'transaction'),
        recurring_entry: localized('ricorrenza', 'recurring entry'),
        import_format: localized('formato di importazione', 'import format'),
    };
    let copy = message.replace(
        /\b([a-z_]+)_(?:uuid|id)\b/gi,
        (_, name) =>
            labels[name.toLowerCase()] ??
            localized('elemento selezionato', 'selected item'),
    );
    copy = copy.replace(
        /\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/gi,
        localized('elemento selezionato', 'selected item'),
    );

    copy = copy.replace(
        /Il campo (.+?) deve essere un UUID valido\./gi,
        localized(
            'La selezione per $1 non è valida. Seleziona nuovamente l’elemento.',
            'The selection for $1 is invalid. Please select the item again.',
        ),
    );

    if (/\buuid\b/i.test(copy)) {
        return localized(
            'La selezione non è valida. Cerca e seleziona nuovamente l’elemento prima di salvare.',
            'The selection is invalid. Search and select the item again before saving.',
        );
    }

    return copy;
}

/** Validation payloads can contain error bags, nested fields and arrays. */
export function validationMessages(errors) {
    const messages = [];
    const visit = (value) => {
        if (typeof value === 'string' && value.trim()) {
            messages.push(userErrorMessage(value.trim()));
        } else if (Array.isArray(value)) {
            value.forEach(visit);
        } else if (value && typeof value === 'object') {
            Object.values(value).forEach(visit);
        }
    };
    visit(errors);

    return [...new Set(messages)];
}

/** @returns {Array<{type: 'success'|'error'|'warning'|'info', message: string}>} */
export function notificationFlash(flash) {
    if (!flash || typeof flash !== 'object') {
        return [];
    }

    return ['success', 'error', 'warning', 'warn', 'info', 'status']
        .filter((type) => typeof flash[type] === 'string' && flash[type].trim())
        .map((type) => ({
            type:
                type === 'warn' ? 'warning' : type === 'status' ? 'info' : type,
            message: flash[type].trim(),
        }));
}

export function isSilentNotificationRequest(url) {
    return (
        isNotificationPreview(url) ||
        isReferenceDraftRequest(url) ||
        /\/(?:toggle-active|reorder|mark-read|read-all)(?:[/?#]|$)/i.test(
            String(url),
        )
    );
}

export function isNotificationPreview(url) {
    return /(?:preview|exchange-preview)(?:[/?#]|$)/i.test(String(url));
}

/** Inertia owns requests; these observers never change callbacks or validation. */
export function bindNotificationFeedback(router, feedback) {
    const mutations = new Set();
    const foreground = new Set();
    let method = 'post';
    let mutationUrl = '';
    const stops = [
        router.on('start', ({ detail: { visit } }) => {
            if (!visit.async && !visit.prefetch) {
                foreground.add(visit);
            }

            if (
                visit.method === 'get' ||
                visit.prefetch ||
                visit.async ||
                isSilentNotificationRequest(visit.url ?? '')
            ) {
                return;
            }

            mutations.add(visit);
            method = visit.method;
            mutationUrl = String(visit.url ?? '');
            feedback.start();
        }),
        router.on('success', ({ detail: { page } }) => {
            if (page.component === 'Error') {
                feedback.failure(500);

                return;
            }

            const flash = notificationFlash(page.props.flash);

            if (flash.length && mutations.size) {
                flash.forEach((item) =>
                    feedback.message(item.type, item.message),
                );
            } else if (!flash.length && mutations.size) {
                feedback.success(method, mutationUrl);
            }
        }),
        router.on('error', ({ detail: { errors } }) => {
            if (mutations.size) {
                feedback.validation(errors);
            }
        }),
        router.on('invalid', (event) => {
            if (mutations.size || foreground.size) {
                event.preventDefault();
                feedback.failure(event.detail.response.status);
            }
        }),
        router.on('exception', (event) => {
            if (mutations.size || foreground.size) {
                event.preventDefault();
                feedback.failure(0);
            }
        }),
        router.on('finish', ({ detail: { visit } }) => {
            foreground.delete(visit);
            mutations.delete(visit);

            if (!mutations.size) {
                feedback.finish();
            }
        }),
    ];

    return () => {
        stops.forEach((stop) => stop());
        mutations.clear();
        foreground.clear();
        feedback.finish();
    };
}

/** Fallback only when the server did not provide a more precise flash message. */
export function mutationSuccessCopy(method, url = '') {
    if (method === 'delete') {
        return 'Eliminazione completata.';
    }

    const path = new URL(String(url), 'https://notifications.local').pathname;

    if (/\/login$/.test(path)) {
        return 'Accesso effettuato.';
    }

    if (/\/logout$/.test(path)) {
        return 'Sessione terminata.';
    }

    if (/\/register$/.test(path)) {
        return 'Registrazione completata.';
    }

    if (/\/transactions(?:\/|$)/.test(path)) {
        return 'Movimento salvato correttamente.';
    }

    if (/\/recurring-entries(?:\/|$)/.test(path)) {
        return 'Ricorrenza aggiornata correttamente.';
    }

    if (/\/budgets(?:\/|$)/.test(path)) {
        return 'Budget aggiornato correttamente.';
    }

    if (/\/categories(?:\/|$)/.test(path)) {
        return 'Categoria aggiornata correttamente.';
    }

    if (/\/tracked-items(?:\/|$)/.test(path)) {
        return 'Riferimento aggiornato correttamente.';
    }

    if (/\/accounts(?:\/|$)/.test(path)) {
        return 'Conto aggiornato correttamente.';
    }

    if (/\/settings(?:\/|$)/.test(path)) {
        return 'Impostazioni aggiornate correttamente.';
    }

    return 'Operazione completata correttamente.';
}
