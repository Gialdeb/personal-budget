import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, watch } from 'vue';
import '../css/app.css';
import PwaStatusBanner from '@/components/PwaStatusBanner.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { createAppI18n } from '@/i18n';
import { initializeAnalytics } from '@/lib/analytics';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const ASSET_VERSION_META = 'meta[name="soamco-asset-version"]';
const ASSET_VERSION_ENDPOINT_META =
    'meta[name="soamco-asset-version-endpoint"]';
const ASSET_VERSION_CHECK_INTERVAL_MS = 60 * 1000;
const pages = {
    ...import.meta.glob<DefineComponent>('./pages/*.vue'),
    ...import.meta.glob<DefineComponent>('./pages/**/*.vue'),
};

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, pages),
    setup({ el, App, props, plugin }) {
        const i18n = createAppI18n(props.initialPage.props);
        document.documentElement.lang = i18n.global.locale.value;
        syncMoneyPreferences(props.initialPage.props.auth?.user);
        initializeAnalytics(props.initialPage);

        watch(i18n.global.locale, (locale) => {
            document.documentElement.lang = locale;
        });

        watch(
            () => props.initialPage.props.auth?.user,
            (user) => {
                syncMoneyPreferences(user);
            },
            { deep: true },
        );

        createApp({
            render: () =>
                h('div', { class: 'relative' }, [
                    h(App, props),
                    h(PwaStatusBanner),
                ]),
        })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
bootstrapAssetVersionGuard();

function syncMoneyPreferences(
    user:
        | {
              format_locale?: string | null;
              base_currency_code?: string | null;
          }
        | null
        | undefined,
): void {
    document.documentElement.dataset.formatLocale =
        user?.format_locale || 'it-IT';
    document.documentElement.dataset.baseCurrencyCode =
        user?.base_currency_code || 'EUR';
}

function bootstrapAssetVersionGuard(): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const currentVersion = document
        .querySelector<HTMLMetaElement>(ASSET_VERSION_META)
        ?.content?.trim();
    const endpoint = document
        .querySelector<HTMLMetaElement>(ASSET_VERSION_ENDPOINT_META)
        ?.content?.trim();

    if (!currentVersion || !endpoint) {
        return;
    }

    let isReloading = false;

    const reloadIfVersionChanged = async (): Promise<void> => {
        if (isReloading) {
            return;
        }

        try {
            const response = await fetch(`${endpoint}?t=${Date.now()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as { version?: string };

            if (!payload.version || payload.version.trim() === currentVersion) {
                return;
            }

            isReloading = true;
            window.location.reload();
        } catch {
            // Ignore transient failures and retry on the next check.
        }
    };

    void reloadIfVersionChanged();

    const intervalId = window.setInterval(
        () => void reloadIfVersionChanged(),
        ASSET_VERSION_CHECK_INTERVAL_MS,
    );

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void reloadIfVersionChanged();
        }
    });

    window.addEventListener('beforeunload', () => {
        window.clearInterval(intervalId);
    });
}
