export type OnboardingState = {
    enabled: boolean;
    tours: Record<string, 'completed' | 'skipped'>;
    quick_start_dismissed: boolean;
    legacy_imported: boolean;
    revision: number;
};

export type OnboardingStep = {
    id: string;
    target: string;
    setup?: boolean;
};

export type OnboardingSection =
    | 'dashboard'
    | 'transactions'
    | 'recurring'
    | 'shared-expenses';

export const screenForComponent: Record<string, OnboardingSection> = {
    'dashboard/Analysis': 'dashboard',
    'transactions/Show': 'transactions',
    'transactions/recurring/Index': 'recurring',
    SharedExpenses: 'shared-expenses',
};

export const tours: Record<OnboardingSection, OnboardingStep[]> = {
    dashboard: [
        { id: 'setup', target: 'dashboard.welcome', setup: true },
        { id: 'navigation', target: 'chrome.navigation' },
        { id: 'dashboardPeriod', target: 'dashboard.period' },
        { id: 'timeline', target: 'dashboard.timeline' },
        { id: 'capacity', target: 'dashboard.capacity' },
        { id: 'pending', target: 'dashboard.pending' },
        { id: 'budget', target: 'dashboard.budget' },
        { id: 'agenda', target: 'dashboard.agenda' },
    ],
    transactions: [
        { id: 'transactionPeriod', target: 'transactions.period' },
        { id: 'transactionCreate', target: 'transactions.create' },
        { id: 'transactionSummary', target: 'transactions.summary' },
        { id: 'transactionReferences', target: 'transactions.references' },
        { id: 'transactionRegister', target: 'transactions.register' },
        { id: 'quickActions', target: 'chrome.quickActions' },
    ],
    recurring: [
        { id: 'recurringPeriod', target: 'recurring.period' },
        { id: 'recurringCreate', target: 'recurring.create' },
        { id: 'recurringSummary', target: 'recurring.summary' },
        { id: 'recurringCalendar', target: 'recurring.calendar' },
        { id: 'recurringFilters', target: 'recurring.filters' },
        { id: 'recurringPayments', target: 'recurring.payments' },
    ],
    'shared-expenses': [
        { id: 'sharedBalances', target: 'shared.balances' },
        { id: 'sharedPeople', target: 'shared.people' },
        { id: 'sharedFilters', target: 'shared.filters' },
        { id: 'sharedStatus', target: 'shared.status' },
        { id: 'sharedRegister', target: 'shared.register' },
        { id: 'sharedShares', target: 'shared.shares' },
        { id: 'sharedRepayments', target: 'shared.repayments' },
    ],
};
