export function recurringPlanProgress(
    entry: {
        entry_type: string | null;
        end_mode: string | null;
        installments_count?: number | null;
        occurrences_limit?: number | null;
        end_date?: string | null;
    },
    completed: number,
): {
    openEnded: boolean;
    total: number | null;
    value: string;
    percentage: number | null;
} {
    const openEnded =
        entry.entry_type !== 'installment' &&
        !entry.end_date &&
        !entry.occurrences_limit;
    const total =
        entry.entry_type === 'installment'
            ? (entry.installments_count ?? entry.occurrences_limit ?? null)
            : entry.end_mode === 'after_occurrences'
              ? (entry.occurrences_limit ?? null)
              : null;

    return {
        openEnded,
        total,
        value: `${completed}${openEnded ? ' / ∞' : total !== null ? ` / ${total}` : ''}`,
        percentage:
            total !== null && total > 0
                ? Math.min(100, Math.max(0, (completed / total) * 100))
                : null,
    };
}

export function annualOccurrencesProgress(
    occurrences: Array<{
        uuid: string;
        expected_date: string | null;
        due_date: string | null;
        status: string | null;
        settlement_status?: string | null;
    }>,
    year: number,
): { paid: number; total: number; value: string; percentage: number | null } {
    const rows = [
        ...new Map(occurrences.map((row) => [row.uuid, row])).values(),
    ].filter(
        (row) =>
            Number((row.due_date ?? row.expected_date)?.slice(0, 4)) === year &&
            row.status !== 'cancelled',
    );
    const paid = rows.filter(
        (row) => row.settlement_status === 'settled',
    ).length;
    const total = rows.length;

    return {
        paid,
        total,
        value: `${paid} / ${total}`,
        percentage: total > 0 ? (paid / total) * 100 : null,
    };
}
