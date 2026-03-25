<?php

namespace App\Services\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\UserYearService;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TransactionNavigationService
{
    public function __construct(
        protected UserYearService $userYearService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, int $year, ?int $month = null): array
    {
        $periodEndDate = $this->resolvePeriodEndDate($year, $month);
        $effectiveCoverageTotalMonths = $month !== null
            ? $periodEndDate->month
            : $this->resolveCoverageTotalMonths($year, $periodEndDate);
        $transactionQuery = $this->accessibleTransactionQuery($user);

        $monthlyCounts = (clone $transactionQuery)
            ->selectRaw($this->datePartExpression('month', 'transaction_date').' as month')
            ->selectRaw('COUNT(*) as aggregate')
            ->whereYear('transaction_date', $year)
            ->whereDate('transaction_date', '<=', $periodEndDate->toDateString())
            ->groupBy('month')
            ->pluck('aggregate', 'month');

        $coverageMonthsCount = $monthlyCounts->filter(
            fn ($count): bool => (int) $count > 0
        )->count();

        $selectedMonthCount = $month !== null
            ? (int) ($monthlyCounts->get($month) ?? 0)
            : null;

        $selectedRecordsCount = (clone $transactionQuery)
            ->whereYear('transaction_date', $year)
            ->whereDate('transaction_date', '<=', $periodEndDate->toDateString())
            ->count();

        $lastRecordedAt = (clone $transactionQuery)
            ->whereYear('transaction_date', $year)
            ->whereDate('transaction_date', '<=', $periodEndDate->toDateString())
            ->max('transaction_date');

        return [
            'enabled' => true,
            'context' => [
                'year' => $year,
                'month' => $month,
                'active_year' => $user->settings?->active_year,
                'available_years' => $this->userYearService->availableYears($user),
                'is_month_selected' => $month !== null,
                'period_label' => $month === null
                    ? __('app.common.current_year', ['year' => $year])
                    : $this->fullMonthLabel($year, $month),
            ],
            'months' => collect(range(1, 12))
                ->map(function (int $value) use ($year, $month, $monthlyCounts): array {
                    $count = (int) ($monthlyCounts->get($value) ?? 0);

                    return [
                        'value' => $value,
                        'label' => mb_strtolower((string) PeriodOptions::monthLabel($value)),
                        'full_label' => $this->fullMonthLabel($year, $value),
                        'count' => $count,
                        'has_data' => $count > 0,
                        'is_selected' => $month === $value,
                        'href' => route('transactions.show', [
                            'year' => $year,
                            'month' => $value,
                        ]),
                    ];
                })
                ->all(),
            'summary' => [
                'records_count' => $selectedRecordsCount,
                'status' => $month === null
                    ? ($coverageMonthsCount > 0 ? __('transactions.navigation.coverage_available') : __('app.common.none_recorded'))
                    : (($selectedMonthCount ?? 0) > 0 ? __('transactions.navigation.with_data') : __('app.common.none_recorded')),
                'status_tone' => $month === null
                    ? ($coverageMonthsCount > 0 ? 'data' : 'empty')
                    : (($selectedMonthCount ?? 0) > 0 ? 'data' : 'empty'),
                'records_label' => $month === null
                    ? __('transactions.navigation.records_in_period')
                    : __('transactions.navigation.cumulative_records'),
                'coverage_months_count' => $coverageMonthsCount,
                'coverage_total_months' => $effectiveCoverageTotalMonths,
                'coverage_percentage' => $effectiveCoverageTotalMonths > 0
                    ? (int) round(($coverageMonthsCount / $effectiveCoverageTotalMonths) * 100)
                    : 0,
                'coverage_label' => "{$coverageMonthsCount} mesi con registrazioni su {$effectiveCoverageTotalMonths}",
                'last_recorded_at' => $lastRecordedAt !== null
                    ? CarbonImmutable::parse($lastRecordedAt)->toDateString()
                    : null,
                'period_end_at' => $periodEndDate->toDateString(),
            ],
        ];
    }

    public function resolveLandingMonth(User $user, int $year, ?int $preferredMonth = null): int
    {
        if (PeriodOptions::isValidMonth($preferredMonth, allowNull: false)) {
            return $preferredMonth;
        }

        $latestMonthWithData = $this->accessibleTransactionQuery($user)
            ->whereYear('transaction_date', $year)
            ->selectRaw('MAX('.$this->datePartExpression('month', 'transaction_date').') as month')
            ->value('month');

        if (is_numeric($latestMonthWithData)) {
            return (int) $latestMonthWithData;
        }

        return 1;
    }

    protected function fullMonthLabel(int $year, int $month): string
    {
        return CarbonImmutable::create($year, $month, 1)
            ->locale(app()->getLocale())
            ->translatedFormat('F Y');
    }

    protected function resolvePeriodEndDate(int $year, ?int $month): CarbonImmutable
    {
        $now = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $periodEndDate = $month === null
            ? CarbonImmutable::create($year, 12, 31)->endOfDay()
            : CarbonImmutable::create($year, $month, 1)->endOfMonth()->endOfDay();

        if ($year === $now->year && $periodEndDate->greaterThan($now)) {
            return $now;
        }

        return $periodEndDate;
    }

    protected function resolveCoverageTotalMonths(int $year, CarbonImmutable $periodEndDate): int
    {
        $now = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        if ($year === $now->year) {
            return max(1, $periodEndDate->month);
        }

        return 12;
    }

    protected function datePartExpression(string $part, string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => match ($part) {
                'year' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
                'month' => "CAST(strftime('%m', {$column}) AS INTEGER)",
                default => "CAST(strftime('%d', {$column}) AS INTEGER)",
            },
            'mysql', 'mariadb' => match ($part) {
                'year' => "YEAR({$column})",
                'month' => "MONTH({$column})",
                default => "DAY({$column})",
            },
            default => 'EXTRACT('.strtoupper($part)." FROM {$column})::int",
        };
    }

    protected function accessibleTransactionQuery(User $user)
    {
        return Transaction::query()
            ->whereIn('account_id', $this->accessibleAccountsQuery->ids($user));
    }
}
