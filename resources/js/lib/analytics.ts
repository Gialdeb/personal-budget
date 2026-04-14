import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import {
    COOKIE_CONSENT_STORAGE_KEY,
    COOKIE_CONSENT_UPDATED_EVENT,
} from '@/composables/useCookieConsent';
import type { AnalyticsSharedData } from '@/types/analytics';

type SharedPageProps = {
    analytics?: AnalyticsSharedData;
    locale?: {
        current?: string;
    };
};

type PublicEventContext = {
    placement: string;
    target?: string;
};

const UMAMI_SCRIPT_SELECTOR =
    'script[data-website-id][data-auto-track="false"]';

let currentPage: Page<SharedPageProps> | null = null;
let scriptLoadListenerBound = false;

function getAnalytics(
    page: Page<SharedPageProps> | null,
): AnalyticsSharedData | null {
    return page?.props.analytics ?? null;
}

function getRouteName(page: Page<SharedPageProps> | null): string | null {
    return getAnalytics(page)?.current_route_name ?? null;
}

function getLocale(page: Page<SharedPageProps> | null): string | null {
    return page?.props.locale?.current ?? null;
}

function normalizeUrl(url: string): string {
    return url.split('#')[0] ?? url;
}

function getTrackedSignature(page: Page<SharedPageProps>): string {
    return `${getRouteName(page) ?? 'unknown'}:${normalizeUrl(page.url)}`;
}

function hasAnalyticsConsent(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const rawValue = window.localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY);

    if (!rawValue) {
        return false;
    }

    try {
        const parsed = JSON.parse(rawValue) as {
            preferences?: {
                analytics?: boolean;
            };
        };

        return parsed.preferences?.analytics === true;
    } catch {
        return false;
    }
}

function isEnabled(page: Page<SharedPageProps> | null): boolean {
    const analytics = getAnalytics(page);

    return Boolean(
        analytics?.umami.enabled &&
        analytics.umami.website_id &&
        analytics.umami.public_route_names.length > 0,
    );
}

function isPublicTrackedRoute(page: Page<SharedPageProps>): boolean {
    const analytics = getAnalytics(page);
    const routeName = getRouteName(page);

    if (!analytics || !routeName) {
        return false;
    }

    return analytics.umami.public_route_names.includes(routeName);
}

function buildEventData(
    page: Page<SharedPageProps>,
    context: PublicEventContext,
): Record<string, unknown> {
    return {
        page: normalizeUrl(page.url),
        locale: getLocale(page),
        placement: context.placement,
        target: context.target ?? null,
        environment: getAnalytics(page)?.umami.environment_tag ?? null,
    };
}

function trackerReady(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.umami?.track === 'function'
    );
}

function bindTrackerReadyListener(): void {
    if (typeof document === 'undefined' || scriptLoadListenerBound) {
        return;
    }

    const script = document.querySelector<HTMLScriptElement>(
        UMAMI_SCRIPT_SELECTOR,
    );

    if (!script) {
        return;
    }

    scriptLoadListenerBound = true;
    script.addEventListener(
        'load',
        () => {
            if (currentPage !== null) {
                void trackPageView(currentPage, { force: true });
            }
        },
        { once: true },
    );
}

function shouldTrackPage(page: Page<SharedPageProps>, force = false): boolean {
    if (
        !isEnabled(page) ||
        !isPublicTrackedRoute(page) ||
        !hasAnalyticsConsent()
    ) {
        return false;
    }

    const signature = getTrackedSignature(page);

    if (!force && window.__soamcoBudgetUmamiLastTrackedPage === signature) {
        return false;
    }

    return trackerReady();
}

export function initializeAnalytics(initialPage: Page<SharedPageProps>): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentPage = initialPage;

    if (window.__soamcoBudgetUmamiInitialized) {
        return;
    }

    window.__soamcoBudgetUmamiInitialized = true;
    window.__soamcoBudgetUmamiLastTrackedPage = null;

    bindTrackerReadyListener();
    void trackPageView(initialPage);

    router.on('navigate', (event) => {
        currentPage = event.detail.page as Page<SharedPageProps>;
        void trackPageView(currentPage);
    });

    window.addEventListener(COOKIE_CONSENT_UPDATED_EVENT, (event) => {
        if (event.detail.analytics && currentPage !== null) {
            void trackPageView(currentPage, { force: true });
        }
    });
}

export async function trackPageView(
    page: Page<SharedPageProps>,
    options: { force?: boolean } = {},
): Promise<void> {
    currentPage = page;

    if (!shouldTrackPage(page, options.force)) {
        bindTrackerReadyListener();

        return;
    }

    window.umami?.track((payload) => ({
        ...payload,
        url: normalizeUrl(page.url),
        title: document.title,
        tag: getAnalytics(page)?.umami.environment_tag ?? undefined,
    }));

    window.__soamcoBudgetUmamiLastTrackedPage = getTrackedSignature(page);
}

export function trackPublicCta(
    page: Page<SharedPageProps>,
    eventName: string,
    context: PublicEventContext,
): void {
    if (
        !isEnabled(page) ||
        !isPublicTrackedRoute(page) ||
        !hasAnalyticsConsent()
    ) {
        return;
    }

    if (!trackerReady()) {
        bindTrackerReadyListener();

        return;
    }

    window.umami?.track(eventName, buildEventData(page, context));
}
