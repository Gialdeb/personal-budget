/** Shared visual tokens for register filters, badges and row indicators. */
export const transactionTones = {
    transfer: {
        badge: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-500/25 dark:bg-teal-500/10 dark:text-teal-200',
        row: 'bg-teal-50/40 dark:bg-teal-500/5',
        accent: 'border-teal-300 dark:border-teal-500/40',
    },
    income: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-200',
        row: 'bg-emerald-50/40 dark:bg-emerald-500/5',
        accent: 'border-emerald-300 dark:border-emerald-500/40',
    },
    expense: {
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-200',
        row: 'bg-rose-50/40 dark:bg-rose-500/5',
        accent: 'border-rose-300 dark:border-rose-500/40',
    },
    opening: {
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-200',
        row: 'bg-amber-50/60 dark:bg-amber-500/5',
        accent: 'border-amber-300 dark:border-amber-500/40',
    },
    deleted: {
        badge: 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        row: 'bg-slate-100/75 opacity-75 dark:bg-slate-900/75',
        accent: 'border-slate-300 dark:border-slate-700',
    },
    refunded: {
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-500/25 dark:bg-orange-500/10 dark:text-orange-200',
        row: 'bg-orange-50/50 dark:bg-orange-500/5',
        accent: 'border-orange-300 dark:border-orange-500/40',
    },
    shared: {
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-500/25 dark:bg-violet-500/10 dark:text-violet-200',
        row: 'bg-violet-50/60 dark:bg-violet-500/8',
        accent: 'border-violet-300 dark:border-violet-500/40',
    },
    advanced: {
        badge: 'border-dashed border-violet-300 bg-violet-100/70 text-violet-900 dark:border-violet-400/50 dark:bg-violet-500/15 dark:text-violet-100',
        row: 'bg-violet-50/40 dark:bg-violet-500/5',
        accent: 'border-violet-400 dark:border-violet-400/50',
    },
    recurring: {
        badge: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/25 dark:bg-sky-500/10 dark:text-sky-200',
        row: 'bg-sky-50/50 dark:bg-sky-500/5',
        accent: 'border-sky-300 dark:border-sky-500/40',
    },
    neutral: {
        badge: 'border-border bg-muted/60 text-muted-foreground',
        row: '',
        accent: 'border-border',
    },
};

export function economicCategory(transaction) {
    if (transaction.is_opening_balance) {
        return 'opening';
    }

    if (transaction.is_transfer) {
        return 'transfer';
    }

    return transaction.direction === 'income'
        ? 'income'
        : transaction.direction === 'expense'
          ? 'expense'
          : 'neutral';
}

export function transactionAttributes(transaction) {
    /** @type {Array<'deleted' | 'refunded' | 'shared' | 'recurring'>} */
    const attributes = [];

    if (transaction.is_deleted) {
        attributes.push('deleted');
    }

    if (transaction.is_reimbursement === true) {
        attributes.push('refunded');
    }

    if (transaction.shared_expense_counterparty_uuid) {
        attributes.push('shared');
    }

    if (transaction.is_recurring_transaction) {
        attributes.push('recurring');
    }

    return attributes;
}

export function rowCategory(transaction) {
    if (transaction.is_deleted) {
        return 'deleted';
    }

    if (transaction.is_opening_balance) {
        return 'opening';
    }

    if (transaction.is_reimbursement === true) {
        return 'refunded';
    }

    if (transaction.shared_expense_counterparty_uuid) {
        return 'shared';
    }

    if (
        transaction.is_recurring_transaction ||
        transaction.is_projected_recurring
    ) {
        return 'recurring';
    }

    return economicCategory(transaction);
}

/** Economic inclusion switches compose with a canonical reimbursement filter. */
export function matchesTransactionDisplay(transaction, filters) {
    if (filters.day && transaction.date?.slice(0, 10) !== filters.day) {
        return false;
    }

    if (transaction.is_transfer && filters.transfer === false) {
        return false;
    }

    if (transaction.is_projected_recurring) {
        return false;
    }

    if (transaction.is_opening_balance) {
        if (!filters.opening) {
            return false;
        }
    } else if (!transaction.is_transfer) {
        if (transaction.direction === 'income' && !filters.income) {
            return false;
        }

        if (transaction.direction === 'expense' && !filters.expense) {
            return false;
        }
    }

    if (filters.refunded && transaction.is_reimbursement !== true) {
        return false;
    }

    if (transaction.is_recurring_transaction && !filters.recurring) {
        return false;
    }

    if (transaction.shared_expense_counterparty_uuid && !filters.shared) {
        return false;
    }

    return true;
}

/** Ledger entries have no bank account and never pass through banking totals. */
export function matchesSharedLedgerDisplay(movement, filters) {
    return (
        movement.is_shared_ledger === true &&
        movement.shared_expense_paid_by === 'counterparty' &&
        filters.shared !== false &&
        !filters.refunded &&
        (!filters.day || movement.date?.slice(0, 10) === filters.day) &&
        (!filters.period || movement.date?.startsWith(`${filters.period}-`)) &&
        (filters.advancedBy === 'all' ||
            movement.shared_expense_counterparty_uuid === filters.advancedBy) &&
        (filters.category === 'all' ||
            movement.category_uuid === filters.category) &&
        (filters.macrogroup === 'all' ||
            movement.type_key === filters.macrogroup) &&
        filters.account === 'all'
    );
}

/** Presentation only: preserve saved text and compare labels independently of case and whitespace. */
export function cleanRecordText(value) {
    return typeof value === 'string'
        ? value.normalize('NFC').replace(/\s+/gu, ' ').trim()
        : '';
}

export function sameRecordText(left, right) {
    const key = (value) =>
        cleanRecordText(value).replace(/\s/gu, '').toLowerCase();

    return key(left) === key(right);
}

export function recordHeading(primary, secondary, fallback) {
    const title =
        primary.map(cleanRecordText).find(Boolean) || cleanRecordText(fallback);
    const subtitles = [];

    for (const value of secondary.map(cleanRecordText).filter(Boolean)) {
        if (
            !sameRecordText(value, title) &&
            !subtitles.some((text) => sameRecordText(text, value))
        ) {
            subtitles.push(value);
        }
    }

    return { title, subtitle: subtitles.join(' · ') || null };
}

export function transactionHeading(transaction, fallback) {
    const record = transaction ?? {};
    const metadata = [
        record.tracked_item_label,
        record.category_label,
        record.category_path,
        record.account_label,
        record.scope_label,
        record.shared_expense_counterparty_label,
    ];
    const detail = metadata.some(
        (label) =>
            cleanRecordText(label) && sameRecordText(label, record.detail),
    )
        ? null
        : record.detail;

    return recordHeading(
        [record.description, detail],
        [detail, record.notes],
        fallback,
    );
}

export function recurringPlanHeading(plan, fallback) {
    return recordHeading(
        [plan?.title, plan?.description],
        [plan?.description],
        fallback,
    );
}

export function recurringOccurrenceHeading(occurrence, fallback) {
    const plan = occurrence?.recurring_entry;

    return recordHeading(
        [plan?.title, occurrence?.title, occurrence?.description],
        [occurrence?.description, plan?.description],
        fallback,
    );
}
