<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserYearService
{
    /**
     * @return array<int, int>
     */
    public function availableYears(User $user): array
    {
        return $user->years()
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->values()
            ->all();
    }

    public function syncActiveYear(User $user, int $year): void
    {
        if ($user->settings?->active_year === $year) {
            return;
        }

        $user->settings()->updateOrCreate([], [
            'active_year' => $year,
        ]);

        $user->unsetRelation('settings');
        $user->load('settings');
    }

    public function ensureYearIsOpen(User $user, int $year, string $errorKey = 'year'): void
    {
        $userYear = UserYear::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        if ($userYear === null) {
            throw ValidationException::withMessages([
                $errorKey => "L'anno {$year} non è disponibile tra gli anni di gestione.",
            ]);
        }

        if ($userYear->is_closed) {
            throw ValidationException::withMessages([
                $errorKey => "L'anno {$year} è chiuso. Puoi consultare i dati, ma non modificarli finché non lo riapri.",
            ]);
        }
    }

    public function ensureDateYearIsOpen(User $user, string $date, string $errorKey = 'date'): void
    {
        $year = CarbonImmutable::parse($date)->year;

        $this->ensureYearIsOpen($user, $year, $errorKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildNextYearSuggestion(User $user, int $currentYear, ?CarbonImmutable $now = null): ?array
    {
        $availableYears = $this->availableYears($user);

        if ($availableYears === []) {
            return null;
        }

        $highestYear = max($availableYears);
        $now = $now ?? CarbonImmutable::now(config('app.timezone'));
        $currentCalendarYear = $now->year;
        $currentCalendarYearAvailable = in_array($currentCalendarYear, $availableYears, true);

        if (
            ! $currentCalendarYearAvailable
            && (
                ($now->month >= 11 && $highestYear === $currentCalendarYear - 1)
                || $highestYear < $currentCalendarYear
            )
            && $currentYear === $highestYear
        ) {
            return [
                'next_year' => $currentCalendarYear,
                'current_year' => $currentYear,
                'title' => "Prepara l'anno {$currentCalendarYear}",
                'message' => "L'anno {$currentCalendarYear} non è ancora aperto nel gestionale. Puoi crearlo ora senza generare dati in automatico.",
            ];
        }

        $nextYear = $highestYear + 1;

        if ($currentYear !== $highestYear || $currentYear !== $currentCalendarYear || $now->month < 11) {
            return null;
        }

        if (in_array($nextYear, $availableYears, true)) {
            return null;
        }

        return [
            'next_year' => $nextYear,
            'current_year' => $currentYear,
            'title' => "Prepara l'anno {$nextYear}",
            'message' => "Stai lavorando sull'anno più recente. Puoi aprire ora il {$nextYear} senza creare nulla in automatico.",
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function usageSummary(User $user): array
    {
        $years = $this->availableYears($user);

        if ($years === []) {
            return [];
        }

        $usage = collect($years)
            ->mapWithKeys(fn (int $year): array => [
                $year => [
                    'counts' => [
                        'budgets' => 0,
                        'transactions' => 0,
                        'scheduled_entries' => 0,
                        'recurring_occurrences' => 0,
                        'recurring_entries' => 0,
                    ],
                ],
            ])
            ->all();

        $this->mergeYearCounts(
            $usage,
            Budget::query()
                ->select('year', DB::raw('COUNT(*) as aggregate'))
                ->where('user_id', $user->id)
                ->groupBy('year')
                ->get(),
            'budgets'
        );

        $this->mergeYearCounts(
            $usage,
            Transaction::query()
                ->selectRaw($this->datePartExpression('transaction_date').' as year')
                ->selectRaw('COUNT(*) as aggregate')
                ->where('user_id', $user->id)
                ->groupBy('year')
                ->get(),
            'transactions'
        );

        $this->mergeYearCounts(
            $usage,
            ScheduledEntry::query()
                ->selectRaw($this->datePartExpression('scheduled_date').' as year')
                ->selectRaw('COUNT(*) as aggregate')
                ->where('user_id', $user->id)
                ->groupBy('year')
                ->get(),
            'scheduled_entries'
        );

        $this->mergeYearCounts(
            $usage,
            RecurringEntryOccurrence::query()
                ->selectRaw($this->datePartExpression('expected_date').' as year')
                ->selectRaw('COUNT(*) as aggregate')
                ->whereHas('recurringEntry', function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id);
                })
                ->groupBy('year')
                ->get(),
            'recurring_occurrences'
        );

        $recurringEntries = RecurringEntry::query()
            ->where('user_id', $user->id)
            ->get([
                'start_date',
                'end_date',
            ]);

        foreach ($recurringEntries as $recurringEntry) {
            $startDate = CarbonImmutable::parse($recurringEntry->start_date)->startOfDay();
            $endDate = $recurringEntry->end_date !== null
                ? CarbonImmutable::parse($recurringEntry->end_date)->endOfDay()
                : null;

            foreach ($years as $year) {
                $yearStart = CarbonImmutable::create($year, 1, 1)->startOfYear();
                $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfYear();

                if ($startDate->gt($yearEnd)) {
                    continue;
                }

                if ($endDate !== null && $endDate->lt($yearStart)) {
                    continue;
                }

                $usage[$year]['counts']['recurring_entries']++;
            }
        }

        foreach ($usage as $year => &$yearUsage) {
            $totalUsageCount = array_sum($yearUsage['counts']);

            $yearUsage['usage_count'] = $totalUsageCount;
            $yearUsage['used'] = $totalUsageCount > 0;
            $yearUsage['is_deletable'] = $totalUsageCount === 0;
        }

        return $usage;
    }

    /**
     * @return array<int, string>
     */
    public function deletionBlockingReasons(User $user, UserYear $userYear): array
    {
        $reasons = [];
        $availableYears = $this->availableYears($user);
        $usage = $this->usageSummary($user)[$userYear->year] ?? [
            'counts' => [],
        ];

        if (count($availableYears) <= 1) {
            $reasons[] = 'deve rimanere almeno un anno di gestione disponibile';
        }

        if ($user->settings?->active_year === $userYear->year) {
            $reasons[] = "è l'anno attivo corrente";
        }

        $counts = $usage['counts'] ?? [];

        if (($counts['budgets'] ?? 0) > 0) {
            $reasons[] = 'ha budget collegati';
        }

        if (($counts['transactions'] ?? 0) > 0) {
            $reasons[] = 'ha transazioni collegate';
        }

        if (($counts['scheduled_entries'] ?? 0) > 0) {
            $reasons[] = 'ha scadenze pianificate collegate';
        }

        if (($counts['recurring_occurrences'] ?? 0) > 0) {
            $reasons[] = 'ha occorrenze ricorrenti collegate';
        }

        if (($counts['recurring_entries'] ?? 0) > 0) {
            $reasons[] = 'ha ricorrenze attive su questo anno';
        }

        return $reasons;
    }

    /**
     * @param  array<int, array<string, mixed>>  $usage
     */
    protected function mergeYearCounts(array &$usage, iterable $rows, string $key): void
    {
        foreach ($rows as $row) {
            $year = (int) $row->year;

            if (! array_key_exists($year, $usage)) {
                continue;
            }

            $usage[$year]['counts'][$key] = (int) $row->aggregate;
        }
    }

    protected function datePartExpression(string $column): string
    {
        $driver = UserYear::query()->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            'mysql', 'mariadb' => "YEAR({$column})",
            default => "EXTRACT(YEAR FROM {$column})::int",
        };
    }
}
