import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type CookieConsentPreferences = {
    necessary: true;
    preferences: boolean;
    analytics: boolean;
    marketing: boolean;
};

type CookieConsentState = {
    version: 1;
    updatedAt: string;
    preferences: CookieConsentPreferences;
};

export const COOKIE_CONSENT_STORAGE_KEY = 'soamco-budget-cookie-consent';
const COOKIE_CONSENT_COOKIE_NAME = 'soamco_budget_cookie_consent';
const COOKIE_CONSENT_OPEN_EVENT = 'soamco-budget:open-cookie-consent';
export const COOKIE_CONSENT_UPDATED_EVENT =
    'soamco-budget:cookie-consent-updated';

const defaultPreferences = (): CookieConsentPreferences => ({
    necessary: true,
    preferences: false,
    analytics: false,
    marketing: false,
});

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredConsent = (): CookieConsentState | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const rawValue = window.localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY);

    if (!rawValue) {
        return null;
    }

    try {
        const parsed = JSON.parse(rawValue) as unknown;

        if (
            typeof parsed !== 'object' ||
            parsed === null ||
            !('version' in parsed) ||
            !('preferences' in parsed)
        ) {
            return null;
        }

        const preferences = parsed.preferences;

        if (
            parsed.version !== 1 ||
            typeof preferences !== 'object' ||
            preferences === null ||
            !('preferences' in preferences) ||
            !('analytics' in preferences) ||
            !('marketing' in preferences) ||
            typeof preferences.preferences !== 'boolean' ||
            typeof preferences.analytics !== 'boolean' ||
            typeof preferences.marketing !== 'boolean'
        ) {
            return null;
        }

        return {
            version: 1,
            updatedAt:
                'updatedAt' in parsed && typeof parsed.updatedAt === 'string'
                    ? parsed.updatedAt
                    : new Date().toISOString(),
            preferences: {
                necessary: true,
                preferences: preferences.preferences,
                analytics: preferences.analytics,
                marketing: preferences.marketing,
            },
        };
    } catch {
        return null;
    }
};

const persistConsent = (preferences: CookieConsentPreferences): void => {
    if (typeof window === 'undefined') {
        return;
    }

    const payload: CookieConsentState = {
        version: 1,
        updatedAt: new Date().toISOString(),
        preferences,
    };

    const serialized = JSON.stringify(payload);

    window.localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY, serialized);
    setCookie(COOKIE_CONSENT_COOKIE_NAME, serialized);
};

export function openCookieConsentPreferences(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(COOKIE_CONSENT_OPEN_EVENT));
}

export function useCookieConsent() {
    const storedConsent = ref<CookieConsentState | null>(null);
    const isBannerVisible = ref(false);
    const isPreferencesOpen = ref(false);
    const draftPreferences =
        ref<CookieConsentPreferences>(defaultPreferences());

    const hasConsent = computed(() => storedConsent.value !== null);
    const effectivePreferences = computed<CookieConsentPreferences>(
        () => storedConsent.value?.preferences ?? defaultPreferences(),
    );

    function syncDraftWithStoredConsent(): void {
        draftPreferences.value = {
            ...effectivePreferences.value,
        };
    }

    function applyPreferences(preferences: CookieConsentPreferences): void {
        persistConsent({
            necessary: true,
            preferences: preferences.preferences,
            analytics: preferences.analytics,
            marketing: preferences.marketing,
        });

        storedConsent.value = getStoredConsent();
        isBannerVisible.value = false;
        isPreferencesOpen.value = false;
        syncDraftWithStoredConsent();

        if (typeof window !== 'undefined') {
            window.dispatchEvent(
                new CustomEvent(COOKIE_CONSENT_UPDATED_EVENT, {
                    detail: {
                        analytics: preferences.analytics,
                    },
                }),
            );
        }
    }

    function acceptAll(): void {
        applyPreferences({
            necessary: true,
            preferences: true,
            analytics: true,
            marketing: true,
        });
    }

    function acceptEssentialOnly(): void {
        applyPreferences(defaultPreferences());
    }

    function saveCustomPreferences(): void {
        applyPreferences({
            necessary: true,
            preferences: draftPreferences.value.preferences,
            analytics: draftPreferences.value.analytics,
            marketing: draftPreferences.value.marketing,
        });
    }

    function openPreferences(): void {
        syncDraftWithStoredConsent();
        isPreferencesOpen.value = true;
    }

    function closePreferences(): void {
        isPreferencesOpen.value = false;
        syncDraftWithStoredConsent();
    }

    const handleOpenRequest = () => {
        openPreferences();
    };

    onMounted(() => {
        storedConsent.value = getStoredConsent();
        isBannerVisible.value = storedConsent.value === null;
        syncDraftWithStoredConsent();

        window.addEventListener(COOKIE_CONSENT_OPEN_EVENT, handleOpenRequest);
    });

    onBeforeUnmount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.removeEventListener(
            COOKIE_CONSENT_OPEN_EVENT,
            handleOpenRequest,
        );
    });

    return {
        COOKIE_CONSENT_STORAGE_KEY,
        hasConsent,
        isBannerVisible,
        isPreferencesOpen,
        draftPreferences,
        effectivePreferences,
        acceptAll,
        acceptEssentialOnly,
        saveCustomPreferences,
        openPreferences,
        closePreferences,
    };
}
