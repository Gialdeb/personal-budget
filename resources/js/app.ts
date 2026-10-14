import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, nextTick, watch } from 'vue';
import '../css/app.css';
import MaintenanceStateOverlay from '@/components/MaintenanceStateOverlay.vue';
import NavigationProgress from '@/components/NavigationProgress.vue';
import PwaStatusBanner from '@/components/PwaStatusBanner.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeAppTouchGuards } from '@/composables/useAppTouchGuards';
import { initializeNavigationFeedback } from '@/composables/useNavigationFeedback';
import { createAppI18n } from '@/i18n';
import { initializeAnalytics } from '@/lib/analytics';
import { initializeSessionIsolation } from '@/lib/session-isolation';
import {
    destroy as destroyPushTokenAction,
    store as storePushTokenAction,
} from '@/routes/settings/profile/push-tokens';
import type { CurrencyCatalogItem, LocaleSharedData } from '@/types';

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
        initializeNavigationFeedback();
        initializeSessionIsolation(
            props.initialPage.props.auth?.user?.uuid ?? null,
            document.documentElement.hasAttribute('data-session-private'),
        );

        const i18n = createAppI18n(props.initialPage.props);
        document.documentElement.lang = i18n.global.locale.value;
        syncMoneyPreferences(
            props.initialPage.props.auth?.user,
            props.initialPage.props.locale,
        );
        initializeAnalytics(props.initialPage);

        watch(i18n.global.locale, (locale) => {
            document.documentElement.lang = locale;
        });

        watch(
            () => props.initialPage.props.auth?.user,
            (user) => {
                syncMoneyPreferences(user, props.initialPage.props.locale);
            },
            { deep: true },
        );

        createApp({
            render: () =>
                h('div', { class: 'relative' }, [
                    h(
                        'div',
                        {
                            'data-maintenance-content-root': '',
                        },
                        [h(App, props), h(PwaStatusBanner)],
                    ),
                    h(MaintenanceStateOverlay),
                    h(NavigationProgress),
                ]),
        })
            .use(plugin)
            .use(i18n)
            .mount(el);

        void nextTick(() => {
            performance.mark('soamco:mounted');
            window.dispatchEvent(new Event('soamco:ready'));
            // Allow the first frame to paint before loading optional SDKs.
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    bootstrapAssetVersionGuard();
                    void initializePushAfterRender(props.initialPage.props);
                });
            });
        });
    },
    progress: false,
}).catch(() => {
    window.dispatchEvent(new Event('soamco:startup-error'));
});

// This will set light / dark mode on page load...
initializeTheme();
initializeAppTouchGuards();

async function initializePushAfterRender(pageProps: {
    auth?: { user?: { locale?: string } | null };
    features?: { push_notifications_enabled?: boolean };
}): Promise<void> {
    try {
        const {
            cleanupLegacyFirebaseMessagingServiceWorker,
            initializeForegroundPushNotifications,
            synchronizeCurrentBrowserPushRegistration,
        } = await import('@/lib/push-notifications');
        await Promise.allSettled([
            cleanupLegacyFirebaseMessagingServiceWorker(),
            initializeForegroundPushNotifications(),
            synchronizeCurrentBrowserPushRegistration({
                isAuthenticated: pageProps.auth?.user != null,
                featureEnabled:
                    pageProps.features?.push_notifications_enabled === true,
                locale: pageProps.auth?.user?.locale,
                storeUrl: storePushTokenAction().url,
                destroyUrl: destroyPushTokenAction().url,
            }),
        ]);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn('[push] post-render initialization skipped', error);
        }
    }
}

function syncMoneyPreferences(
    user:
        | {
              format_locale?: string | null;
              number_thousands_separator?: string | null;
              number_decimal_separator?: string | null;
              date_format?: string | null;
              base_currency_code?: string | null;
          }
        | null
        | undefined,
    locale?: LocaleSharedData | null | undefined,
): void {
    document.documentElement.dataset.formatLocale =
        user?.format_locale || 'it-IT';
    document.documentElement.dataset.numberThousandsSeparator =
        user?.number_thousands_separator === 'space'
            ? ' '
            : user?.number_thousands_separator || '.';
    document.documentElement.dataset.numberDecimalSeparator =
        user?.number_decimal_separator || ',';
    document.documentElement.dataset.dateFormat =
        user?.date_format || 'D MMM YYYY';
    document.documentElement.dataset.baseCurrencyCode =
        user?.base_currency_code || 'EUR';
    window.__soamcoBudgetCurrencyCatalog = cloneCurrencyCatalog(
        locale?.currencies,
    );
}

function cloneCurrencyCatalog(
    currencies: LocaleSharedData['currencies'] | null | undefined,
): Record<string, CurrencyCatalogItem> {
    if (!currencies || typeof currencies !== 'object') {
        return {};
    }

    return Object.fromEntries(
        Object.entries(currencies).map(([code, currency]) => [
            code,
            { ...currency },
        ]),
    );
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
