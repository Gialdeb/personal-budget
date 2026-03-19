<?php

namespace App\Supports;

use App\Models\Budget;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardPeriodResolver
{
    public static function resolve(Request $request, User $user): array
    {
        $settings = $user->settings;

        $availableYears = static::resolveAvailableYears($user);

        $fallbackYear = $settings?->active_year
            ?: (! empty($availableYears) ? max($availableYears) : now()->year);

        $requestedYear = (int) (
            $request->integer('year')
                ?: session('dashboard_year')
                ?: $fallbackYear
        );

        $year = empty($availableYears) || in_array($requestedYear, $availableYears, true)
            ? $requestedYear
            : $fallbackYear;

        $month = PeriodOptions::normalizeMonth(
            $request->has('month')
                ? $request->input('month')
                : ($request->has('year') ? null : session('dashboard_month'))
        );

        return [
            'year' => $year,
            'month' => $month,
        ];
    }

    public static function persist(User $user, int $year, ?int $month): void
    {
        session([
            'dashboard_year' => $year,
            'dashboard_month' => $month,
        ]);

        if ($user->settings?->active_year !== $year) {
            $user->settings()->updateOrCreate([], [
                'active_year' => $year,
            ]);
            $user->unsetRelation('settings');
            $user->load('settings');
        }
    }

    protected static function resolveAvailableYears(User $user): array
    {
        return $user->years()
            ->pluck('year')
            ->merge(static::pluckYearValues(
                static::baseTransactionYearsQuery($user->id),
                'transaction_date'
            ))
            ->merge(
                static::baseBudgetYearsQuery($user->id)
                    ->pluck('year')
            )
            ->merge(static::pluckYearValues(
                static::baseScheduledYearsQuery($user->id),
                'scheduled_date'
            ))
            ->merge(static::pluckYearValues(
                static::baseRecurringOccurrenceYearsQuery($user->id),
                'expected_date'
            ))
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected static function baseTransactionYearsQuery(int $userId): Builder
    {
        $query = Transaction::query()
            ->where('transactions.user_id', $userId);

        return static::applyTrackedItemOwnershipConstraint(
            $query,
            'transactions.tracked_item_id',
            $userId
        );
    }

    protected static function baseBudgetYearsQuery(int $userId): Builder
    {
        $query = Budget::query()
            ->where('budgets.user_id', $userId);

        return static::applyTrackedItemOwnershipConstraint(
            $query,
            'budgets.tracked_item_id',
            $userId
        );
    }

    protected static function baseScheduledYearsQuery(int $userId): Builder
    {
        $query = ScheduledEntry::query()
            ->where('scheduled_entries.user_id', $userId);

        return static::applyTrackedItemOwnershipConstraint(
            $query,
            'scheduled_entries.tracked_item_id',
            $userId
        );
    }

    protected static function baseRecurringOccurrenceYearsQuery(int $userId): Builder
    {
        return RecurringEntryOccurrence::query()
            ->whereHas('recurringEntry', function (Builder $query) use ($userId) {
                $query->where('user_id', $userId);

                static::applyTrackedItemOwnershipConstraint(
                    $query,
                    'recurring_entries.tracked_item_id',
                    $userId
                );
            });
    }

    protected static function pluckYearValues(Builder $query, string $column): array
    {
        $yearExpression = static::datePartExpression('year', $column);

        return $query->selectRaw("{$yearExpression} as year")
            ->distinct()
            ->pluck('year')
            ->all();
    }

    protected static function applyTrackedItemOwnershipConstraint(
        Builder $query,
        string $qualifiedColumn,
        int $userId,
        string $relation = 'trackedItem'
    ): Builder {
        return $query->where(function (Builder $trackedItemQuery) use (
            $qualifiedColumn,
            $relation,
            $userId
        ) {
            $trackedItemQuery->whereNull($qualifiedColumn)
                ->orWhereHas($relation, function (Builder $ownedTrackedItemQuery) use ($userId) {
                    $ownedTrackedItemQuery->where('user_id', $userId);
                });
        });
    }

    protected static function datePartExpression(string $part, string $column): string
    {
        $driver = Transaction::query()->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => match ($part) {
                'year' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
                'month' => "CAST(strftime('%m', {$column}) AS INTEGER)",
                'day' => "CAST(strftime('%d', {$column}) AS INTEGER)",
            },
            'mysql', 'mariadb' => match ($part) {
                'year' => "YEAR({$column})",
                'month' => "MONTH({$column})",
                'day' => "DAY({$column})",
            },
            default => 'EXTRACT('.strtoupper($part)." FROM {$column})::int",
        };
    }
}
