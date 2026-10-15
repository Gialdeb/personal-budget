/** Validation payloads can contain error bags, nested fields and arrays. */
export function validationMessages(errors) {
    const messages = [];
    const visit = (value) => {
        if (typeof value === 'string' && value.trim()) {
            messages.push(value.trim());
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

export function isNotificationPreview(url) {
    return /(?:preview|exchange-preview)(?:[/?#]|$)/i.test(String(url));
}

/** Inertia owns requests; these observers never change callbacks or validation. */
export function bindNotificationFeedback(router, feedback) {
    const mutations = new Set();
    const foreground = new Set();
    let method = 'post';
    let mutationUrl = '';
    let lastFlash = JSON.stringify(notificationFlash(feedback.initialFlash));
    const stops = [
        router.on('start', ({ detail: { visit } }) => {
            if (!visit.async && !visit.prefetch) {
                foreground.add(visit);
            }

            if (visit.method === 'get' || visit.prefetch) {
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
            const fingerprint = JSON.stringify(flash);

            if (flash.length && (mutations.size || fingerprint !== lastFlash)) {
                flash.forEach((item) =>
                    feedback.message(item.type, item.message),
                );
            } else if (!flash.length && mutations.size) {
                feedback.success(method, mutationUrl);
            }

            lastFlash = fingerprint;
        }),
        router.on('error', ({ detail: { errors } }) =>
            feedback.validation(errors),
        ),
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
