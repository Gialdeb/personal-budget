import { accountsMessages } from '@/i18n/messages/accounts';
import { appMessages } from '@/i18n/messages/app';
import { authMessages } from '@/i18n/messages/auth';
import { categoriesMessages } from '@/i18n/messages/categories';
import { dashboardMessages } from '@/i18n/messages/dashboard';
import { importsMessages } from '@/i18n/messages/imports';
import { navMessages } from '@/i18n/messages/nav';
import { planningMessages } from '@/i18n/messages/planning';
import { settingsMessages } from '@/i18n/messages/settings';
import { trackedItemsMessages } from '@/i18n/messages/trackedItems';
import { transactionsMessages } from '@/i18n/messages/transactions';

export const messages = {
    it: {
        app: appMessages.it,
        auth: authMessages.it,
        nav: navMessages.it,
        dashboard: dashboardMessages.it,
        planning: planningMessages.it,
        imports: importsMessages.it,
        accounts: accountsMessages.it,
        categories: categoriesMessages.it,
        trackedItems: trackedItemsMessages.it,
        transactions: transactionsMessages.it,
        settings: settingsMessages.it,
    },
    en: {
        app: appMessages.en,
        auth: authMessages.en,
        nav: navMessages.en,
        dashboard: dashboardMessages.en,
        planning: planningMessages.en,
        imports: importsMessages.en,
        accounts: accountsMessages.en,
        categories: categoriesMessages.en,
        trackedItems: trackedItemsMessages.en,
        transactions: transactionsMessages.en,
        settings: settingsMessages.en,
    },
} as const;
