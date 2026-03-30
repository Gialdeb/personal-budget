import type { Router, Page } from '@inertiajs/core';
import type { createHeadManager } from '@inertiajs/vue3';
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
