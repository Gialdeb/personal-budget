import { push } from 'notivue';
import type { NotificationClearMethods } from 'notivue';
import {
    isNotificationPreview,
    mutationSuccessCopy,
    validationMessages,
    userErrorMessage,
    isReferenceDraftRequest,
} from './notification-feedback.js';

type NoticeType = 'success' | 'error' | 'warning' | 'info';
type Translator = (text: string) => string;
let translate: Translator = (text) => text;
let enabled = false;
const recent = new Map<string, number>();
let pendingValidation: string[] = [];
let validationQueued = false;
const validationRecent = new Map<string, number>();

export function configureNotifications(translator: Translator): void {
    translate = translator;
    enabled = true;
}

export function notify(type: NoticeType, message: string): void {
    const statusCopy: Record<string, string> = {
        'verification-link-sent': 'Link di verifica inviato.',
        'passwords.sent': 'Link per reimpostare la password inviato.',
        'passwords.reset': 'Password reimpostata correttamente.',
        'password-updated': 'Password aggiornata correttamente.',
        'profile-information-updated': 'Profilo aggiornato correttamente.',
        'two-factor-authentication-confirmed':
            'Autenticazione a due fattori confermata.',
        'two-factor-authentication-enabled':
            'Autenticazione a due fattori attivata.',
        'two-factor-authentication-disabled':
            'Autenticazione a due fattori disattivata.',
        'recovery-codes-generated': 'Codici di recupero rigenerati.',
    };

    if (statusCopy[message]) {
        message = translate(statusCopy[message]);
    }

    message = userErrorMessage(message);

    if (!enabled || !message.trim()) {
        return;
    }

    const key = `${type}:${message}`;
    const now = Date.now();

    if (now - (recent.get(key) ?? 0) < 1500) {
        return;
    }

    for (const [key, time] of recent) {
        if (now - time > 1500) {
            recent.delete(key);
        }
    }

    recent.set(key, now);
    push[type]({
        message,
        title: translate(
            type === 'success'
                ? 'Operazione completata'
                : type === 'error'
                  ? 'Operazione non riuscita'
                  : type === 'warning'
                    ? 'Controlla i dati inseriti'
                    : 'Informazione',
        ),
    });
}

export function notifyValidation(errors: unknown): void {
    pendingValidation.push(...validationMessages(errors));

    if (validationQueued || !pendingValidation.length) {
        return;
    }

    validationQueued = true;
    queueMicrotask(() => {
        validationQueued = false;
        const now = Date.now();
        const messages = [...new Set(pendingValidation)].filter(
            (message) => now - (validationRecent.get(message) ?? 0) >= 1500,
        );

        for (const [message, time] of validationRecent) {
            if (now - time >= 1500) {
                validationRecent.delete(message);
            }
        }

        messages.forEach((message) => validationRecent.set(message, now));
        pendingValidation = [];

        if (messages.length) {
            notify(
                'warning',
                messages.map((message) => `• ${message}`).join('\n'),
            );
        }
    });
}

export function notifySuccess(method = 'post', url = ''): void {
    notify('success', translate(mutationSuccessCopy(method, url)));
}

export function notifyFailure(status = 0): void {
    notify(
        status === 419 ? 'warning' : 'error',
        translate(
            status === 419
                ? 'La sessione è scaduta. Ricarica la pagina e riprova.'
                : status === 403
                  ? 'Non hai i permessi per questa operazione.'
                  : status === 0
                    ? 'Connessione non disponibile. Verifica la rete e riprova.'
                    : 'Impossibile completare l’operazione. Riprova.',
        ),
    );
}

/** Progress appears only for operations that take long enough to need feedback. */
export function delayedNotification(): () => void {
    let pending: NotificationClearMethods | undefined;
    const timer = setTimeout(() => {
        if (enabled) {
            pending = push.promise({
                message: translate('Operazione in corso…'),
            });
        }
    }, 700);

    return () => {
        clearTimeout(timer);
        pending?.clear();
    };
}

type NotificationRequestInit = RequestInit & {
    notification?: 'preview' | 'silent' | string;
};
/** Explicitly used by user actions; background sync keeps its original fetch. */
export async function fetchWithNotifications(
    input: RequestInfo | URL,
    options: NotificationRequestInit = {},
): Promise<Response> {
    const { notification, ...init } = options;
    const url = input instanceof Request ? input.url : String(input);

    if (isReferenceDraftRequest(url) && typeof init.body === 'string') {
        const payload = JSON.parse(init.body);

        return fetch(input, {
            ...init,
            body: JSON.stringify({ ...payload, draft: true }),
        });
    }

    const method = (
        init.method ?? (input instanceof Request ? input.method : 'GET')
    ).toLowerCase();
    const writes = method !== 'get' && method !== 'head';
    const preview =
        notification === 'preview' ||
        isNotificationPreview(input instanceof Request ? input.url : input);

    if (!writes || notification === 'silent' || preview) {
        return fetch(input, init);
    }

    const finish = delayedNotification();

    try {
        const response = await fetch(input, init);
        const payload = response.headers
            .get('content-type')
            ?.includes('application/json')
            ? await response
                  .clone()
                  .json()
                  .catch(() => null)
            : null;

        if (
            response.status === 422 ||
            (payload?.errors && Object.keys(payload.errors).length)
        ) {
            notifyValidation(
                payload?.errors ??
                    payload?.message ??
                    translate(
                        'Controlla i campi obbligatori prima di continuare.',
                    ),
            );
        } else if (!response.ok) {
            notifyFailure(response.status);
        } else {
            if (
                typeof payload?.message === 'string' &&
                payload.message.trim()
            ) {
                notify('success', payload.message);
            } else if (notification) {
                notify('success', translate(notification));
            } else {
                notifySuccess(method);
            }
        }

        return response;
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            notifyFailure();
        }

        throw error;
    } finally {
        finish();
    }
}
