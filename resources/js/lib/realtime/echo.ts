import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type RealtimeConfig = {
    appKey: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
    debug: boolean;
};

function readEnvValue(value: string | boolean | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

export function resolveRealtimeConfig(
    env: ImportMetaEnv = import.meta.env,
): RealtimeConfig | null {
    const appKey = readEnvValue(env.VITE_REVERB_APP_KEY);
    const host = readEnvValue(env.VITE_REVERB_HOST);
    const port = Number.parseInt(readEnvValue(env.VITE_REVERB_PORT), 10);
    const scheme =
        readEnvValue(env.VITE_REVERB_SCHEME) === 'http' ? 'http' : 'https';

    if (!appKey || !host || Number.isNaN(port)) {
        return null;
    }

    return {
        appKey,
        host,
        port,
        scheme,
        debug: env.DEV,
    };
}

function readCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function logRealtimeDebug(message: string, context?: unknown): void {
    if (!window.__soamcoBudgetRealtimeDebugEnabled) {
        return;
    }

    console.debug(`[realtime] ${message}`, context);
}

function attachDebugListeners(echo: Echo<'reverb'>): void {
    const connection = echo.connector.pusher.connection;

    connection.bind('state_change', (states: unknown) => {
        logRealtimeDebug('connection state changed', states);
    });

    connection.bind('error', (error: unknown) => {
        logRealtimeDebug('connection error', error);
    });
}

function createRealtimeClient(config: RealtimeConfig): Echo<'reverb'> {
    window.Pusher = Pusher;
    window.__soamcoBudgetRealtimeDebugEnabled = config.debug;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: config.appKey,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': readCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    attachDebugListeners(echo);

    return echo;
}

export function getRealtimeClient(): Echo<'reverb'> | null {
    if (window.__soamcoBudgetEcho !== undefined) {
        return window.__soamcoBudgetEcho;
    }

    const config = resolveRealtimeConfig();

    if (config === null) {
        window.__soamcoBudgetEcho = null;

        return null;
    }

    window.__soamcoBudgetEcho = createRealtimeClient(config);

    return window.__soamcoBudgetEcho;
}

export function listenOnPrivateChannel<TPayload>(
    channelName: string,
    eventName: string,
    handler: (payload: TPayload) => void,
): () => void {
    const echo = getRealtimeClient();

    if (echo === null) {
        return () => {};
    }

    const channel = echo.private(channelName);
    const qualifiedEventName = `.${eventName}`;

    channel.listen(qualifiedEventName, (payload: TPayload) => {
        logRealtimeDebug(`received [${eventName}]`, payload);
        handler(payload);
    });

    return () => {
        channel.stopListening(qualifiedEventName);
        echo.leave(channelName);
    };
}

export function listenOnPublicChannel<TPayload>(
    channelName: string,
    eventName: string,
    handler: (payload: TPayload) => void,
): () => void {
    const echo = getRealtimeClient();

    if (echo === null) {
        return () => {};
    }

    const channel = echo.channel(channelName);
    const qualifiedEventName = `.${eventName}`;

    channel.listen(qualifiedEventName, (payload: TPayload) => {
        logRealtimeDebug(`received [${eventName}]`, payload);
        handler(payload);
    });

    return () => {
        channel.stopListening(qualifiedEventName);
        echo.leave(channelName);
    };
}
