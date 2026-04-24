import { accountsMessages } from '@/i18n/messages/accounts';
import { adminMessages } from '@/i18n/messages/admin';
import { appMessages } from '@/i18n/messages/app';
import { authMessages } from '@/i18n/messages/auth';
import { categoriesMessages } from '@/i18n/messages/categories';
import { dashboardMessages } from '@/i18n/messages/dashboard';
import { entrySearchMessages } from '@/i18n/messages/entry-search';
import { exportMessages } from '@/i18n/messages/export';
import { importsMessages } from '@/i18n/messages/imports';
import { legalMessages } from '@/i18n/messages/legal';
import { navMessages } from '@/i18n/messages/nav';
import { planningMessages } from '@/i18n/messages/planning';
import { reportsMessages } from '@/i18n/messages/reports';
import { settingsMessages } from '@/i18n/messages/settings';
import { trackedItemsMessages } from '@/i18n/messages/trackedItems';
import { transactionsMessages } from '@/i18n/messages/transactions';

export const messages = {
    it: {
        admin: adminMessages.it,
        app: appMessages.it,
        auth: authMessages.it,
        legal: legalMessages.it,
        nav: navMessages.it,
        dashboard: dashboardMessages.it,
        entrySearch: entrySearchMessages.it,
        export: exportMessages.it,
        planning: planningMessages.it,
        reports: reportsMessages.it,
        imports: importsMessages.it,
        accounts: accountsMessages.it,
        categories: categoriesMessages.it,
        trackedItems: trackedItemsMessages.it,
        transactions: transactionsMessages.it,
        settings: settingsMessages.it,
    },
    en: {
        admin: adminMessages.en,
        app: appMessages.en,
        auth: authMessages.en,
        legal: legalMessages.en,
        nav: navMessages.en,
        dashboard: dashboardMessages.en,
        entrySearch: entrySearchMessages.en,
        export: exportMessages.en,
        planning: planningMessages.en,
        reports: reportsMessages.en,
        imports: importsMessages.en,
        accounts: accountsMessages.en,
        categories: categoriesMessages.en,
        trackedItems: trackedItemsMessages.en,
        transactions: transactionsMessages.en,
        settings: settingsMessages.en,
    },
} as const;
