import { router } from '@inertiajs/vue3';
import { identity } from '@/routes/session';

const SESSION_CHANGE_KEY = 'soamco-session-change';
const CHECK_TIMEOUT_MS = 5000;

export function initializeSessionIsolation(
    expectedUserUuid: string | null,
    privateSurface = expectedUserUuid !== null,
): void {
    const root = document.documentElement;
    const state = document.getElementById('session-state');
    let generation = 0;
    let verified = false;
    let controller: AbortController | null = null;

    const conceal = () => {
        verified = false;
        root.setAttribute('data-session-unverified', '');

        if (state) {
            state.hidden = false;
        }
    };
    const reveal = () => {
        verified = true;

        if (state) {
            state.hidden = true;
        }

        root.removeAttribute('data-session-unverified');
    };
    const invalidate = () => {
        generation++;
        controller?.abort();
        controller = null;
    };
    const showOffline = () => {
        conceal();
        const template = document.getElementById('neutral-offline-page');

        if (state && template) {
            const frame = document.createElement('iframe');
            frame.title = 'Soamco · Offline';
            frame.srcdoc = root.classList.contains('dark')
                ? template.innerHTML.replace(
                      '</style>',
                      'body{background:#0f172a;color:#f8fafc}p{color:#cbd5e1}button{border-color:#475569}</style>',
                  )
                : template.innerHTML;
            state.replaceChildren(frame);
        }
    };
    const showRetry = (server = false) => {
        conceal();

        if (!state) {
            return;
        }

        const title = document.createElement('strong');
        title.textContent = 'Soamco';
        const message = document.createElement('p');
        const italian = root.lang.startsWith('it');
        message.textContent = server
            ? italian
                ? 'Il servizio è temporaneamente non disponibile. Riproveremo automaticamente tra poco.'
                : 'The service is temporarily unavailable. We will retry shortly.'
            : italian
              ? 'Non riusciamo a caricare la pagina. Controlla la connessione e riprova.'
              : 'We cannot load the page. Check your connection and retry.';
        const button = document.createElement('button');
        button.textContent = italian ? 'Riprova' : 'Retry';
        button.addEventListener('click', () => void validate());
        state.replaceChildren(title, message, button);
    };
    const publishChange = () => {
        try {
            localStorage.setItem(SESSION_CHANGE_KEY, String(Date.now()));
        } catch {
            // Returning tabs still validate their current session.
        }
    };
    const validate = async (): Promise<void> => {
        invalidate();
        const current = generation;

        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            if (!verified) {
                showOffline();
            }

            return;
        }

        if (!verified) {
            conceal();
        }

        const active = new AbortController();
        controller = active;
        const timeout = window.setTimeout(
            () => active.abort(),
            CHECK_TIMEOUT_MS,
        );

        try {
            const response = await fetch(
                identity.url({ query: { check: Date.now() } }),
                {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: active.signal,
                },
            );

            if (current !== generation || active.signal.aborted) {
                return;
            }

            if ([401, 419].includes(response.status)) {
                conceal();
                window.location.reload();

                return;
            }

            if (!response.ok) {
                if (!verified) {
                    showRetry(response.status >= 500);
                }

                window.dispatchEvent(
                    new Event(
                        response.status >= 500
                            ? 'soamco:server-error'
                            : 'soamco:network-error',
                    ),
                );

                return;
            }

            const payload = await response.json();

            if (current !== generation || active.signal.aborted) {
                return;
            }

            if (payload.user_uuid !== expectedUserUuid) {
                conceal();
                window.location.reload();

                return;
            }

            reveal();
        } catch {
            if (current !== generation) {
                return;
            }

            if (!verified) {
                if (typeof navigator !== 'undefined' && !navigator.onLine) {
                    showOffline();
                } else {
                    showRetry();
                }
            }

            window.dispatchEvent(new Event('soamco:network-error'));
        } finally {
            window.clearTimeout(timeout);

            if (current === generation) {
                controller = null;
            }
        }
    };

    router.on('navigate', (event) => {
        const uuid = event.detail.page.props.auth?.user?.uuid ?? null;

        if (uuid !== expectedUserUuid) {
            publishChange();
        }

        const nextPrivate = event.detail.page.props.sessionVerificationRequired;

        if (
            typeof nextPrivate === 'boolean' &&
            nextPrivate !== privateSurface
        ) {
            invalidate();

            if (nextPrivate) {
                conceal();
            }

            window.location.reload();

            return;
        }

        if (uuid !== expectedUserUuid) {
            invalidate();

            if (expectedUserUuid !== null) {
                conceal();
            }

            window.location.reload();
        }
    });

    if (!privateSurface || expectedUserUuid === null) {
        reveal();

        return;
    }

    window.addEventListener('pagehide', () => {
        invalidate();
        conceal();
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            conceal();
            void validate();
        }
    });
    window.addEventListener('offline', () => {
        invalidate();

        if (!verified) {
            showOffline();
        }
    });
    window.addEventListener('online', () => void validate());
    window.addEventListener('soamco:network-restored', () => {
        if (!document.hidden) {
            void validate();
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            invalidate();
            conceal();
        } else {
            void validate();
        }
    });
    window.addEventListener('storage', (event) => {
        if (event.key === SESSION_CHANGE_KEY) {
            conceal();
            void validate();
        }
    });
    void validate();
}
