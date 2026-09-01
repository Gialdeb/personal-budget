<?php

namespace App\Services\Dashboard;

use App\Enums\CreditDebtTypeEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardAnalysisService
{
    public function build(array $dashboard, int $year, ?int $month, array $accountContext): array
    {
        $overview = $dashboard['overview'];
        $creditsDebts = $dashboard['credits_debts'];
        $budgetTotal = (float) $overview['budget_total'];
        $expenses = (float) $overview['expense_total'];
        $remainingBudget = round($budgetTotal - $expenses, 2);
        $timeline = $this->timeline($year, $month, $accountContext, (float) $overview['previous_balance_total'], (float) $overview['current_balance_total']);
        $selectedPoint = collect($timeline)->firstWhere('is_selected', true) ?? $timeline[0];
        $selectedPeriod = CarbonImmutable::create($selectedPoint['year'], $selectedPoint['month'], 1, 0, 0, 0, config('app.timezone'));
        $detailTimeline = $this->timeline(
            $year,
            $month,
            $accountContext,
            (float) $overview['previous_balance_total'],
            (float) $overview['current_balance_total'],
            $selectedPeriod->subMonths(3)->startOfMonth(),
            $selectedPeriod->addMonths(3)->endOfMonth(),
        );
        $commitmentMonthly = round(
            (float) $selectedPoint['composition']['recurring_raw']
            + (float) $selectedPoint['composition']['installments_raw']
            + (float) $selectedPoint['open_debts_raw'],
            2,
        );
        $commitmentRatio = $budgetTotal > 0 ? round(($commitmentMonthly / $budgetTotal) * 100, 1) : null;

        return [
            'period' => [
                'current_balance_raw' => (float) $overview['current_balance_total'],
                'income_raw' => (float) $overview['income_total'],
                'expenses_raw' => $expenses,
                'net_raw' => (float) $overview['net_total'],
            ],
            'spending_capacity' => [
                'available' => $budgetTotal > 0,
                'source' => 'budget',
                'budget_total_raw' => $budgetTotal,
                'expenses_raw' => $expenses,
                'remaining_budget_raw' => $remainingBudget,
                'spendable_amount_raw' => $budgetTotal > 0 ? max(0, $remainingBudget) : null,
                'is_budget_exceeded' => $budgetTotal > 0 && $remainingBudget < 0,
                'included_components' => [
                    'real_expenses' => true,
                    'future_commitments' => false,
                    'open_debts' => false,
                    'open_credits' => false,
                ],
                'simulation_impacts' => [
                    'forecast_expenses_raw' => round(-((float) $selectedPoint['known_expense_raw']), 2),
                    'open_debts_raw' => round(-((float) $selectedPoint['open_debts_raw']), 2),
                    'open_credits_raw' => round((float) $selectedPoint['open_credits_raw'], 2),
                ],
            ],
            'credits_debts' => [
                'open_credits_raw' => (float) $creditsDebts['credits_open_total'],
                'open_debts_raw' => (float) $creditsDebts['debts_open_total'],
                'net_expected_raw' => (float) $creditsDebts['net_expected_total'],
                'overdue_count' => (int) $creditsDebts['overdue_count'],
            ],
            'timeline' => $timeline,
            'economic_commitment' => [
                'monthly_raw' => $commitmentMonthly,
                'budget_ratio' => $commitmentRatio,
                'remaining_monthly_raw' => $budgetTotal > 0 ? round($budgetTotal - $commitmentMonthly, 2) : null,
                'months_count' => 1,
                'composition' => [
                    'recurring_raw' => (float) $selectedPoint['composition']['recurring_raw'],
                    'installments_raw' => (float) $selectedPoint['composition']['installments_raw'],
                    'debts_raw' => (float) $selectedPoint['open_debts_raw'],
                ],
            ],
            'month_detail' => $this->monthDetail($dashboard, $selectedPoint, $detailTimeline),
            'insights' => $this->insights($budgetTotal, $remainingBudget, $timeline, $creditsDebts),
        ];
    }

    private function timeline(int $year, ?int $month, array $accountContext, float $selectedStartingBalance, float $selectedEndingBalance, ?CarbonImmutable $rangeStart = null, ?CarbonImmutable $rangeEnd = null): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $selectedMonth = $month ?? ($year === (int) $today->year ? (int) $today->month : 1);
        $selected = CarbonImmutable::create($year, $selectedMonth, 1, 0, 0, 0, config('app.timezone'));
        $start = $rangeStart ?? $selected->startOfYear();
        $end = $rangeEnd ?? $selected->endOfYear();
        $accountIds = $accountContext['account_ids'] !== [] ? $accountContext['account_ids'] : [0];
        $ownerIds = $accountContext['owner_ids'] !== [] ? $accountContext['owner_ids'] : [0];

        $transactions = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereIn('kind', [
                TransactionKindEnum::MANUAL->value,
                TransactionKindEnum::SCHEDULED->value,
            ])
            ->where('is_transfer', false)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->where(fn (Builder $query): Builder => $query->whereNull('tracked_item_id')->orWhereHas('trackedItem', fn (Builder $tracked): Builder => $tracked->whereIn('user_id', $ownerIds)))
            ->get(['id', 'transaction_date', 'direction', 'amount', 'currency', 'currency_code', 'base_currency_code', 'converted_base_amount']);

        $occurrences = RecurringEntryOccurrence::query()
            ->whereIn('status', [RecurringOccurrenceStatusEnum::PENDING->value, RecurringOccurrenceStatusEnum::GENERATED->value])
            ->whereNull('matched_transaction_id')
            ->whereNull('converted_transaction_id')
            ->whereBetween('expected_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('recurringEntry', function (Builder $query) use ($accountIds, $ownerIds): void {
                $query->whereIn('account_id', $accountIds)
                    ->where(fn (Builder $tracked): Builder => $tracked->whereNull('tracked_item_id')->orWhereHas('trackedItem', fn (Builder $item): Builder => $item->whereIn('user_id', $ownerIds)));
            })
            ->with('recurringEntry:id,account_id,direction,currency,entry_type,tracked_item_id')
            ->get(['id', 'recurring_entry_id', 'expected_date', 'expected_amount']);

        $scheduled = ScheduledEntry::query()
            ->whereIn('account_id', $accountIds)
            ->whereIn('status', [ScheduledEntryStatusEnum::PLANNED->value, ScheduledEntryStatusEnum::DUE->value])
            ->whereNull('matched_transaction_id')
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->where(fn (Builder $query): Builder => $query->whereNull('tracked_item_id')->orWhereHas('trackedItem', fn (Builder $tracked): Builder => $tracked->whereIn('user_id', $ownerIds)))
            ->get(['id', 'scheduled_date', 'direction', 'expected_amount', 'currency']);

        $creditDebts = CreditDebtItem::query()
            ->whereIn('account_id', $accountIds)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->withSum('payments as paid_amount_sum', 'amount')
            ->get(['id', 'type', 'total_amount', 'currency_code', 'due_date']);

        $periods = collect();
        for ($period = $start->startOfMonth(); $period->lte($end); $period = $period->addMonth()) {
            $periods->push($period);
        }

        $points = $periods->map(function (CarbonImmutable $period) use ($accountContext, $creditDebts, $occurrences, $scheduled, $selected, $today, $transactions): array {
            $key = $period->format('Y-m');
            $actual = $transactions->filter(fn (Transaction $transaction): bool => $transaction->transaction_date?->format('Y-m') === $key);
            $actualIncome = $this->sumTransactions($actual, $accountContext['base_currency'], TransactionDirectionEnum::INCOME);
            $actualExpense = $this->sumTransactions($actual, $accountContext['base_currency'], TransactionDirectionEnum::EXPENSE);
            $recurring = $occurrences->filter(fn (RecurringEntryOccurrence $occurrence): bool => $occurrence->expected_date?->format('Y-m') === $key);
            $planned = $scheduled->filter(fn (ScheduledEntry $entry): bool => $entry->scheduled_date?->format('Y-m') === $key);
            $due = $creditDebts->filter(fn (CreditDebtItem $item): bool => $item->due_date?->format('Y-m') === $key);
            $recurringExpense = $this->sumOccurrences($recurring, $accountContext['base_currency'], TransactionDirectionEnum::EXPENSE, RecurringEntryTypeEnum::RECURRING);
            $installmentExpense = $this->sumOccurrences($recurring, $accountContext['base_currency'], TransactionDirectionEnum::EXPENSE, RecurringEntryTypeEnum::INSTALLMENT);
            $recurringIncome = $this->sumOccurrences($recurring, $accountContext['base_currency'], TransactionDirectionEnum::INCOME);
            $scheduledExpense = $this->sumScheduled($planned, $accountContext['base_currency'], TransactionDirectionEnum::EXPENSE);
            $scheduledIncome = $this->sumScheduled($planned, $accountContext['base_currency'], TransactionDirectionEnum::INCOME);
            $openCredits = $this->sumCreditDebts($due, $accountContext['base_currency'], CreditDebtTypeEnum::CREDIT);
            $openDebts = $this->sumCreditDebts($due, $accountContext['base_currency'], CreditDebtTypeEnum::DEBIT);
            $knownExpense = round($recurringExpense + $installmentExpense + $scheduledExpense, 2);
            $knownIncome = round($recurringIncome + $scheduledIncome, 2);

            return [
                'key' => $key,
                'year' => (int) $period->year,
                'month' => (int) $period->month,
                'state' => $period->endOfMonth()->lt($today) ? 'past' : ($period->startOfMonth()->gt($today->endOfMonth()) ? 'future' : 'current'),
                'is_selected' => $period->isSameMonth($selected),
                'is_current' => $period->isSameMonth($today),
                'actual_income_raw' => $actualIncome,
                'actual_expense_raw' => $actualExpense,
                'known_income_raw' => $knownIncome,
                'known_expense_raw' => $knownExpense,
                'projected_income_raw' => round($actualIncome + $knownIncome, 2),
                'projected_expense_raw' => round($actualExpense + $knownExpense, 2),
                'actual_net_raw' => round($actualIncome - $actualExpense, 2),
                'projected_net_flow_raw' => round(($actualIncome + $knownIncome) - ($actualExpense + $knownExpense), 2),
                'open_credits_raw' => $openCredits,
                'open_debts_raw' => $openDebts,
                'composition' => ['actual_raw' => $actualExpense, 'recurring_raw' => $recurringExpense, 'installments_raw' => $installmentExpense, 'scheduled_raw' => $scheduledExpense],
                'income_composition' => ['actual_raw' => $actualIncome, 'recurring_raw' => $recurringIncome, 'scheduled_raw' => $scheduledIncome],
                'weight' => 'normal',
            ];
        })->values();

        $nonZero = $points->where('projected_expense_raw', '>', 0);
        $heavyKey = $nonZero->sortByDesc('projected_expense_raw')->first()['key'] ?? null;
        $lightKey = $nonZero->sortBy('projected_expense_raw')->first()['key'] ?? null;

        $points = $points->map(function (array $point) use ($heavyKey, $lightKey): array {
            $point['weight'] = $point['key'] === $heavyKey ? 'heavy' : ($point['key'] === $lightKey ? 'light' : 'normal');

            return $point;
        })->values();

        $points = $points->all();
        $selectedIndex = array_search(true, array_column($points, 'is_selected'), true);
        $points[$selectedIndex]['availability_start_raw'] = round($selectedStartingBalance, 2);
        $points[$selectedIndex]['availability_end_raw'] = round($selectedEndingBalance + $points[$selectedIndex]['known_income_raw'] - $points[$selectedIndex]['known_expense_raw'], 2);

        for ($index = $selectedIndex + 1; $index < count($points); $index++) {
            $points[$index]['availability_start_raw'] = $points[$index - 1]['availability_end_raw'];
            $points[$index]['availability_end_raw'] = round($points[$index]['availability_start_raw'] + $points[$index]['projected_net_flow_raw'], 2);
        }

        for ($index = $selectedIndex - 1; $index >= 0; $index--) {
            $points[$index]['availability_end_raw'] = $points[$index + 1]['availability_start_raw'];
            $points[$index]['availability_start_raw'] = round($points[$index]['availability_end_raw'] - $points[$index]['actual_net_raw'], 2);
        }

        return $points;
    }

    private function monthDetail(array $dashboard, array $point, array $detailTimeline): array
    {
        $detailPoints = collect($detailTimeline);
        $windowValues = $detailPoints->map(fn (array $timelinePoint): float => $timelinePoint['state'] === 'past'
            ? (float) $timelinePoint['actual_expense_raw']
            : (float) $timelinePoint['projected_expense_raw']);
        $selectedValue = $point['state'] === 'past'
            ? (float) $point['actual_expense_raw']
            : (float) $point['projected_expense_raw'];
        $comparisonPoints = $detailPoints->filter(fn (array $timelinePoint): bool => $timelinePoint['key'] !== $point['key'] && $timelinePoint['state'] !== 'future');
        $comparisonAverage = $comparisonPoints->isNotEmpty()
            ? round((float) $comparisonPoints->avg(fn (array $timelinePoint): float => $timelinePoint['state'] === 'past'
                ? (float) $timelinePoint['actual_expense_raw']
                : (float) $timelinePoint['projected_expense_raw']), 2)
            : null;
        $futurePoints = $detailPoints->where('state', 'future');

        return [
            'period' => ['key' => $point['key'], 'year' => $point['year'], 'month' => $point['month'], 'state' => $point['state']],
            'real' => [
                'income_raw' => $point['actual_income_raw'],
                'expense_raw' => $point['actual_expense_raw'],
                'net_raw' => $point['actual_net_raw'],
                'starting_balance_raw' => (float) $dashboard['overview']['previous_balance_total'],
                'ending_balance_raw' => (float) $dashboard['overview']['current_balance_total'],
            ],
            'forecast' => ['income_raw' => $point['known_income_raw'], 'expense_raw' => $point['known_expense_raw'], 'projected_net_flow_raw' => $point['projected_net_flow_raw'], 'composition' => $point['composition']],
            'income_composition' => $point['income_composition'],
            'credits' => ['open_raw' => $point['open_credits_raw']],
            'debts' => ['open_raw' => $point['open_debts_raw']],
            'budget' => ['total_raw' => (float) $dashboard['overview']['budget_total'], 'remaining_raw' => (float) $dashboard['overview']['actual_vs_budget_delta']],
            'top_expense_categories' => $dashboard['expense_by_category'],
            'top_income_categories' => $dashboard['income_by_category'],
            'narrative' => [
                'window' => $detailTimeline,
                'selected_expense_raw' => $selectedValue,
                'comparison_average_raw' => $comparisonAverage,
                'difference_from_average_raw' => $comparisonAverage === null ? null : round($selectedValue - $comparisonAverage, 2),
                'future_total_raw' => round((float) $futurePoints->sum('projected_expense_raw'), 2),
                'heavy_future_months' => $futurePoints->filter(fn (array $timelinePoint): bool => $timelinePoint['weight'] === 'heavy')->count(),
                'lightest_future' => $futurePoints->sortBy('projected_expense_raw')->first(),
            ],
        ];
    }

    private function insights(float $budgetTotal, float $remainingBudget, array $timeline, array $creditsDebts): array
    {
        $points = collect($timeline);
        $selected = $points->firstWhere('is_selected', true);
        $pastAverage = (float) $points->where('state', 'past')->avg('actual_expense_raw');
        $insights = [];

        if ($budgetTotal <= 0) {
            $insights[] = $this->insight('budget_not_configured', 'info', 40);
        } elseif ($remainingBudget < 0) {
            $insights[] = $this->insight('budget_exceeded', 'critical', 100, ['amount' => abs($remainingBudget)]);
        } elseif ($remainingBudget <= $budgetTotal * .15) {
            $insights[] = $this->insight('budget_nearly_exhausted', 'warning', 90, ['amount' => $remainingBudget]);
        } else {
            $insights[] = $this->insight('budget_remaining', 'neutral', 20, ['amount' => $remainingBudget]);
        }

        if ($pastAverage > 0 && $selected !== null) {
            $difference = round((((float) $selected['actual_expense_raw'] - $pastAverage) / $pastAverage) * 100, 1);
            $insights[] = $this->insight('spending_vs_average', $difference <= 0 ? 'positive' : 'warning', 70, ['percentage' => abs($difference), 'direction' => $difference <= 0 ? -1 : 1]);
        }

        $nextKey = CarbonImmutable::create($selected['year'], $selected['month'], 1)->addMonth()->format('Y-m');
        $next = $points->firstWhere('key', $nextKey);
        if ($next !== null) {
            $delta = round((float) $next['projected_expense_raw'] - (float) $selected['projected_expense_raw'], 2);
            $insights[] = $this->insight('next_month_difference', $delta > 0 ? 'warning' : 'positive', 80, ['amount' => abs($delta), 'direction' => $delta > 0 ? 1 : -1, 'month' => (int) $next['month'], 'year' => (int) $next['year']]);
        }

        foreach ([['heavy', 'heaviest_month', 'warning', 60], ['light', 'lightest_month', 'positive', 50]] as [$weight, $type, $severity, $priority]) {
            $point = $points->firstWhere('weight', $weight);
            if ($point !== null) {
                $insights[] = $this->insight($type, $severity, $priority, ['month' => (int) $point['month'], 'year' => (int) $point['year'], 'amount' => (float) $point['projected_expense_raw']]);
            }
        }

        if ((float) $creditsDebts['debts_open_total'] > 0) {
            $insights[] = $this->insight('open_debts', 'warning', 75, ['amount' => (float) $creditsDebts['debts_open_total']]);
        }
        if ((float) $creditsDebts['credits_open_total'] > 0) {
            $insights[] = $this->insight('open_credits', 'info', 45, ['amount' => (float) $creditsDebts['credits_open_total']]);
        }

        return collect($insights)->sortByDesc('priority')->take(5)->values()->all();
    }

    private function insight(string $type, string $severity, int $priority, array $params = []): array
    {
        return compact('type', 'severity', 'priority', 'params');
    }

    private function sumTransactions(Collection $items, string $baseCurrency, TransactionDirectionEnum $direction): float
    {
        return round($items->where('direction', $direction)->sum(fn (Transaction $transaction): float => $this->transactionAmount($transaction, $baseCurrency) ?? 0), 2);
    }

    private function transactionAmount(Transaction $transaction, string $baseCurrency): ?float
    {
        $baseCurrency = strtoupper($baseCurrency);
        if ($transaction->converted_base_amount !== null && strtoupper((string) $transaction->base_currency_code) === $baseCurrency) {
            return abs((float) $transaction->converted_base_amount);
        }

        return strtoupper((string) ($transaction->currency_code ?: $transaction->currency)) === $baseCurrency ? abs((float) $transaction->amount) : null;
    }

    private function sumOccurrences(Collection $items, string $baseCurrency, TransactionDirectionEnum $direction, ?RecurringEntryTypeEnum $type = null): float
    {
        return round($items->filter(fn (RecurringEntryOccurrence $item): bool => $item->recurringEntry?->direction === $direction && ($type === null || $item->recurringEntry?->entry_type === $type) && strtoupper((string) $item->recurringEntry?->currency) === strtoupper($baseCurrency))->sum('expected_amount'), 2);
    }

    private function sumScheduled(Collection $items, string $baseCurrency, TransactionDirectionEnum $direction): float
    {
        return round($items->filter(fn (ScheduledEntry $item): bool => $item->direction === $direction && strtoupper((string) $item->currency) === strtoupper($baseCurrency))->sum('expected_amount'), 2);
    }

    private function sumCreditDebts(Collection $items, string $baseCurrency, CreditDebtTypeEnum $type): float
    {
        return round($items->filter(fn (CreditDebtItem $item): bool => $item->type === $type && strtoupper((string) $item->currency_code) === strtoupper($baseCurrency))->sum(fn (CreditDebtItem $item): float => max(0, (float) $item->total_amount - (float) ($item->paid_amount_sum ?? 0))), 2);
    }
}
