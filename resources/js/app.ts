import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, watch } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import { createAppI18n } from '@/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const i18n = createAppI18n(props.initialPage.props);
        document.documentElement.lang = i18n.global.locale.value;
        syncMoneyPreferences(props.initialPage.props.auth?.user);

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

        createApp({ render: () => h(App, props) })
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

function syncMoneyPreferences(user: {
    format_locale?: string | null;
    base_currency_code?: string | null;
} | null | undefined): void {
    document.documentElement.dataset.formatLocale = user?.format_locale || 'it-IT';
    document.documentElement.dataset.baseCurrencyCode =
        user?.base_currency_code || 'EUR';
}
