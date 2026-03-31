import type { Router, Page } from '@inertiajs/core';
import type { createHeadManager } from '@inertiajs/vue3';
import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';
import type { AnalyticsSharedData } from '@/types/analytics';
import type { Auth } from '@/types/auth';
import type { LocaleSharedData } from '@/types/locale';
import type { TransactionsNavigation } from '@/types/transactions';
import type { AppMeta } from '@/types/ui';

type SettingsNavigationSharedData = {
    has_shared_categories: boolean;
};

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_REVERB_APP_KEY?: string;
        readonly VITE_REVERB_HOST?: string;
        readonly VITE_REVERB_PORT?: string;
        readonly VITE_REVERB_SCHEME?: string;
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
            name: string;
            app: AppMeta;
            auth: Auth;
            locale: LocaleSharedData;
            analytics: AnalyticsSharedData;
            sidebarOpen: boolean;
            transactionsNavigation: TransactionsNavigation | null;
            settingsNavigation: SettingsNavigationSharedData;
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
        Pusher?: typeof Pusher;
    }
}
