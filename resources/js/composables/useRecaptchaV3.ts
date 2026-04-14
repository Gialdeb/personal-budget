import { ref } from 'vue';

type RecaptchaConfig = {
    enabled: boolean;
    siteKey: string | null;
};

type Grecaptcha = {
    ready(callback: () => void): void;
    enterprise: {
        ready(callback: () => void): void;
        execute(
            siteKey: string,
            options: { action: string },
        ): Promise<string>;
    };
};

declare global {
    interface Window {
        grecaptcha?: Grecaptcha;
    }
}

let currentScriptSiteKey: string | null = null;
let scriptPromise: Promise<void> | null = null;

export function useRecaptchaV3(config: RecaptchaConfig) {
    const error = ref<string | null>(null);

    async function execute(action: string): Promise<string | null> {
        error.value = null;

        if (!config.enabled) {
            return null;
        }

        if (!config.siteKey) {
            error.value = 'recaptcha_unavailable';

            return null;
        }

        try {
            await ensureScript(config.siteKey);
            const grecaptcha = await waitForGrecaptcha();
            const token = await grecaptcha.enterprise.execute(config.siteKey, {
                action,
            });

            if (token.trim() === '') {
                error.value = 'recaptcha_failed';

                return null;
            }

            return token;
        } catch {
            error.value = 'recaptcha_failed';

            return null;
        }
    }

    return {
        error,
        execute,
    };
}

async function ensureScript(siteKey: string): Promise<void> {
    if (window.grecaptcha && currentScriptSiteKey === siteKey) {
        return;
    }

    if (scriptPromise && currentScriptSiteKey === siteKey) {
        return scriptPromise;
    }

    currentScriptSiteKey = siteKey;

    scriptPromise = new Promise<void>((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[data-recaptcha-site-key="${siteKey}"]`,
        );

        if (existing) {
            if (window.grecaptcha) {
                resolve();

                return;
            }

            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener(
                'error',
                () => reject(new Error('reCAPTCHA failed to load.')),
                { once: true },
            );

            return;
        }

        const script = document.createElement('script');
        script.src = `https://www.google.com/recaptcha/enterprise.js?render=${encodeURIComponent(siteKey)}`;
        script.async = true;
        script.defer = true;
        script.dataset.recaptchaSiteKey = siteKey;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('reCAPTCHA failed to load.'));
        document.head.appendChild(script);
    });

    return scriptPromise;
}

function waitForGrecaptcha(): Promise<Grecaptcha> {
    return new Promise<Grecaptcha>((resolve, reject) => {
        if (!window.grecaptcha) {
            reject(new Error('reCAPTCHA is unavailable.'));

            return;
        }

        window.grecaptcha.enterprise.ready(() => {
            if (!window.grecaptcha) {
                reject(new Error('reCAPTCHA is unavailable.'));

                return;
            }

            resolve(window.grecaptcha);
        });
    });
}
