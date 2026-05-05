import type { Router, Page } from '@inertiajs/core';
import type { createHeadManager } from '@inertiajs/vue3';
import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';
import type { AnalyticsSharedData } from '@/types/analytics';
import type { Auth } from '@/types/auth';
import type { CurrentContextualHelpSharedData } from '@/types/contextual-help';
import type { EntrySearchSharedData } from '@/types/entry-search';
import type { PublicIntegrationsSharedData } from '@/types/integrations';
import type { CurrencyCatalogItem, LocaleSharedData } from '@/types/locale';
import type { MaintenanceStateSharedData } from '@/types/maintenance';
import type { NotificationInboxPreview } from '@/types/notifications';
import type { PublicSeoSharedData } from '@/types/seo';
import type { SessionWarningSharedData } from '@/types/session';
import type { TransactionsNavigation } from '@/types/transactions';
import type { AppMeta } from '@/types/ui';

type SettingsNavigationSharedData = {
    has_shared_categories: boolean;
};

type FeatureFlagsSharedData = {
    imports_enabled: boolean;
    push_notifications_enabled: boolean;
    reports_enabled: boolean;
};

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_REVERB_APP_KEY?: string;
        readonly VITE_REVERB_HOST?: string;
        readonly VITE_REVERB_PORT?: string;
        readonly VITE_REVERB_SCHEME?: string;
        readonly VITE_FIREBASE_API_KEY?: string;
        readonly VITE_FIREBASE_AUTH_DOMAIN?: string;
        readonly VITE_FIREBASE_PROJECT_ID?: string;
        readonly VITE_FIREBASE_STORAGE_BUCKET?: string;
        readonly VITE_FIREBASE_MESSAGING_SENDER_ID?: string;
        readonly VITE_FIREBASE_APP_ID?: string;
        readonly VITE_FIREBASE_VAPID_PUBLIC_KEY?: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            app?: AppMeta;
            auth: Auth;
            locale: LocaleSharedData;
            analytics?: AnalyticsSharedData;
            maintenanceState?: MaintenanceStateSharedData | null;
            notificationInbox?: NotificationInboxPreview | null;
            sidebarOpen?: boolean;
            entrySearch?: EntrySearchSharedData | null;
            transactionsNavigation?: TransactionsNavigation | null;
            sessionWarning?: SessionWarningSharedData | null;
            publicSeo?: PublicSeoSharedData | null;
            publicIntegrations?: PublicIntegrationsSharedData;
            settingsNavigation?: SettingsNavigationSharedData;
            features: FeatureFlagsSharedData;
            contextualHelp?: CurrentContextualHelpSharedData | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

declare global {
    interface BeforeInstallPromptEvent extends Event {
        readonly platforms: string[];
        readonly userChoice: Promise<{
            outcome: 'accepted' | 'dismissed';
            platform: string;
        }>;
        prompt(): Promise<void>;
    }

    interface Navigator {
        standalone?: boolean;
    }

    interface WindowEventMap {
        beforeinstallprompt: BeforeInstallPromptEvent;
        'soamco-budget:cookie-consent-updated': CustomEvent<{
            analytics: boolean;
        }>;
    }

    interface Window {
        umami?: {
            track: (
                payload?:
                    | Record<string, unknown>
                    | string
                    | ((
                          payload: Record<string, unknown>,
                      ) => Record<string, unknown>),
                data?: Record<string, unknown>,
            ) => void;
        };
        __soamcoBudgetUmamiInitialized?: boolean;
        __soamcoBudgetUmamiLastTrackedPage?: string | null;
        __soamcoBudgetEcho?: Echo | null;
        __soamcoBudgetRealtimeDebugEnabled?: boolean;
        __soamcoBudgetCurrencyCatalog?: Record<string, CurrencyCatalogItem>;
        __soamcoBudgetAppTouchGuardsInitialized?: boolean;
        Tawk_API?: Record<string, unknown>;
        Tawk_LoadStart?: Date;
        Pusher?: typeof Pusher;
    }
}
