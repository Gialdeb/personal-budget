<?php

namespace App\Services\Search;

use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UniversalEntrySearchService
{
    protected const MAX_RESULTS = 60;

    protected const MAX_RESULTS_PER_SCOPE = 40;

    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array<string, mixed>,
     *     total_results: int,
     *     groups: array<int, array{
     *         month_key: string,
     *         month_start: string,
     *         items: array<int, array<string, mixed>>
     *     }>
     * }
     */
    public function search(User $user, array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        if (! $this->hasActiveSearch($normalizedFilters)) {
            return [
                'filters' => $normalizedFilters,
                'total_results' => 0,
                'groups' => [],
            ];
        }

        $items = collect();

        if (in_array($normalizedFilters['scope'], ['all', 'transactions'], true)) {
            $items = $items->concat(
                $this->searchTransactions($user, $normalizedFilters)->all(),
            );
        }

        if (in_array($normalizedFilters['scope'], ['all', 'recurring'], true)) {
            $items = $items->concat(
                $this->searchRecurringEntries($user, $normalizedFilters)->all(),
            );
        }

        $sortedItems = $this->sortItems($items)
            ->take(self::MAX_RESULTS)
            ->values();

        return [
            'filters' => $normalizedFilters,
            'total_results' => $sortedItems->count(),
            'groups' => $this->groupItemsByMonth($sortedItems),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchTransactions(User $user, array $filters): Collection
    {
        $accessibleAccountIds = $this->accessibleAccountsQuery->ids($user);

        if ($accessibleAccountIds === []) {
            return collect();
        }

        $query = Transaction::query()
            ->with([
                'account:id,uuid,name,currency,currency_code',
                'category:id,uuid,name',
                'trackedItem:id,uuid,name',
            ])
            ->whereIn('account_id', $accessibleAccountIds)
            ->whereNull('deleted_at');

        $this->applyMonthConstraintToTransactions($query, $filters);
        $this->applySharedFiltersToTransactions($query, $filters);
        $this->applyTextSearchToTransactions($query, $filters['q']);

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(self::MAX_RESULTS_PER_SCOPE)
            ->get()
            ->map(function (Transaction $transaction): array {
                $date = $transaction->transaction_date?->toDateString() ?? now()->toDateString();
                $dateObject = CarbonImmutable::parse($date);
                $title = $transaction->description
                    ?: $transaction->trackedItem?->name
                    ?: $transaction->reference_code
                    ?: $transaction->category?->name
                    ?: $transaction->account?->name
                    ?: __('transactions.title');
                $subtitleParts = array_values(array_filter([
                    $transaction->category?->name,
                    $transaction->account?->name,
                    $transaction->trackedItem?->name ?: $transaction->reference_code,
                ]));

                return [
                    'id' => $transaction->uuid,
                    'kind' => 'transaction',
                    'title' => $title,
                    'subtitle' => $subtitleParts === [] ? null : implode(' • ', $subtitleParts),
                    'amount' => $transaction->amount !== null ? (float) $transaction->amount : null,
                    'currency_code' => $transaction->currency,
                    'date' => $date,
                    'month_key' => $dateObject->format('Y-m'),
                    'month_start' => $dateObject->startOfMonth()->toDateString(),
                    'target_url' => route('transactions.show', [
                        'year' => $dateObject->year,
                        'month' => $dateObject->month,
                        'highlight' => $transaction->uuid,
                    ]),
                    'highlight_key' => $transaction->uuid,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchRecurringEntries(User $user, array $filters): Collection
    {
        $accessibleAccountIds = $this->accessibleAccountsQuery->ids($user);

        $query = RecurringEntry::query()
            ->with([
                'account:id,uuid,name,currency,currency_code',
                'category:id,uuid,name',
                'trackedItem:id,uuid,name',
            ])
            ->where(function (Builder $accessQuery) use ($user, $accessibleAccountIds): void {
                $accessQuery->where('user_id', $user->id);

                if ($accessibleAccountIds !== []) {
                    $accessQuery->orWhereIn('account_id', $accessibleAccountIds);
                }
            });

        $this->applyMonthConstraintToRecurringEntries($query, $filters);
        $this->applySharedFiltersToRecurringEntries($query, $filters);
        $this->applyTextSearchToRecurringEntries($query, $filters['q']);

        return $query
            ->orderByDesc('next_occurrence_date')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(self::MAX_RESULTS_PER_SCOPE)
            ->get()
            ->map(function (RecurringEntry $entry): array {
                $dateObject = CarbonImmutable::parse(
                    $entry->next_occurrence_date?->toDateString()
                    ?? $entry->start_date?->toDateString()
                    ?? now()->toDateString(),
                );
                $subtitleParts = array_values(array_filter([
                    $entry->account?->name,
                    $entry->category?->name,
                    $entry->trackedItem?->name,
                    $entry->status?->value,
                ]));

                return [
                    'id' => $entry->uuid,
                    'kind' => 'recurring',
                    'title' => $entry->title,
                    'subtitle' => $subtitleParts === [] ? null : implode(' • ', $subtitleParts),
                    'amount' => $entry->expected_amount !== null
                        ? (float) $entry->expected_amount
                        : ($entry->total_amount !== null ? (float) $entry->total_amount : null),
                    'currency_code' => $entry->currency ?: $entry->account?->currency_code,
                    'date' => $dateObject->toDateString(),
                    'month_key' => $dateObject->format('Y-m'),
                    'month_start' => $dateObject->startOfMonth()->toDateString(),
                    'target_url' => route('recurring-entries.index', [
                        'year' => $dateObject->year,
                        'month' => $dateObject->month,
                        'highlight' => $entry->uuid,
                    ]),
                    'highlight_key' => $entry->uuid,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'scope' => in_array(($filters['scope'] ?? 'all'), ['all', 'transactions', 'recurring'], true)
                ? (string) $filters['scope']
                : 'all',
            'across_months' => (bool) ($filters['across_months'] ?? false),
            'current_year' => isset($filters['current_year']) ? (int) $filters['current_year'] : now()->year,
            'current_month' => isset($filters['current_month']) && $filters['current_month'] !== null
                ? (int) $filters['current_month']
                : now()->month,
            'account_uuid' => filled($filters['account_uuid'] ?? null) ? (string) $filters['account_uuid'] : null,
            'category_uuid' => filled($filters['category_uuid'] ?? null) ? (string) $filters['category_uuid'] : null,
            'direction' => filled($filters['direction'] ?? null) ? (string) $filters['direction'] : null,
            'amount_min' => isset($filters['amount_min']) && $filters['amount_min'] !== null && $filters['amount_min'] !== ''
                ? (float) $filters['amount_min']
                : null,
            'amount_max' => isset($filters['amount_max']) && $filters['amount_max'] !== null && $filters['amount_max'] !== ''
                ? (float) $filters['amount_max']
                : null,
            'with_notes' => (bool) ($filters['with_notes'] ?? false),
            'with_reference' => (bool) ($filters['with_reference'] ?? false),
            'recurring_status' => (($filters['scope'] ?? 'all') === 'recurring' && filled($filters['recurring_status'] ?? null))
                ? (string) $filters['recurring_status']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function hasActiveSearch(array $filters): bool
    {
        return $filters['q'] !== ''
            || $filters['account_uuid'] !== null
            || $filters['category_uuid'] !== null
            || $filters['direction'] !== null
            || $filters['amount_min'] !== null
            || $filters['amount_max'] !== null
            || $filters['with_notes']
            || $filters['with_reference']
            || $filters['recurring_status'] !== null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyMonthConstraintToTransactions(Builder $query, array $filters): void
    {
        if ($filters['across_months']) {
            return;
        }

        $start = CarbonImmutable::create($filters['current_year'], $filters['current_month'], 1)->startOfMonth();
        $end = $start->endOfMonth();

        $query->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyMonthConstraintToRecurringEntries(Builder $query, array $filters): void
    {
        if ($filters['across_months']) {
            return;
        }

        $start = CarbonImmutable::create($filters['current_year'], $filters['current_month'], 1)->startOfMonth();
        $end = $start->endOfMonth();

        $query->where(function (Builder $monthQuery) use ($start, $end): void {
            $monthQuery
                ->whereBetween('next_occurrence_date', [$start->toDateString(), $end->toDateString()])
                ->orWhere(function (Builder $fallbackQuery) use ($start, $end): void {
                    $fallbackQuery
                        ->whereNull('next_occurrence_date')
                        ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()]);
                });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applySharedFiltersToTransactions(Builder $query, array $filters): void
    {
        if ($filters['account_uuid'] !== null) {
            $query->whereHas('account', function (Builder $accountQuery) use ($filters): void {
                $accountQuery->where('uuid', $filters['account_uuid']);
            });
        }

        if ($filters['category_uuid'] !== null) {
            $query->whereHas('category', function (Builder $categoryQuery) use ($filters): void {
                $categoryQuery->where('uuid', $filters['category_uuid']);
            });
        }

        if ($filters['direction'] !== null) {
            $query->where('direction', $filters['direction']);
        }

        if ($filters['amount_min'] !== null) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if ($filters['amount_max'] !== null) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        if ($filters['with_notes']) {
            $query->whereNotNull('notes')->where('notes', '!=', '');
        }

        if ($filters['with_reference']) {
            $query->where(function (Builder $referenceQuery): void {
                $referenceQuery
                    ->whereNotNull('tracked_item_id')
                    ->orWhere(function (Builder $codeQuery): void {
                        $codeQuery->whereNotNull('reference_code')->where('reference_code', '!=', '');
                    });
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applySharedFiltersToRecurringEntries(Builder $query, array $filters): void
    {
        if ($filters['account_uuid'] !== null) {
            $query->whereHas('account', function (Builder $accountQuery) use ($filters): void {
                $accountQuery->where('uuid', $filters['account_uuid']);
            });
        }

        if ($filters['category_uuid'] !== null) {
            $query->whereHas('category', function (Builder $categoryQuery) use ($filters): void {
                $categoryQuery->where('uuid', $filters['category_uuid']);
            });
        }

        if ($filters['direction'] !== null) {
            $query->where('direction', $filters['direction']);
        }

        if ($filters['amount_min'] !== null) {
            $query->whereRaw('COALESCE(expected_amount, total_amount, 0) >= ?', [$filters['amount_min']]);
        }

        if ($filters['amount_max'] !== null) {
            $query->whereRaw('COALESCE(expected_amount, total_amount, 0) <= ?', [$filters['amount_max']]);
        }

        if ($filters['with_notes']) {
            $query->whereNotNull('notes')->where('notes', '!=', '');
        }

        if ($filters['with_reference']) {
            $query->whereNotNull('tracked_item_id');
        }

        if ($filters['recurring_status'] !== null) {
            $query->where('status', $filters['recurring_status']);
        }
    }

    protected function applyTextSearchToTransactions(Builder $query, string $searchTerm): void
    {
        if ($searchTerm === '') {
            return;
        }

        $needle = '%'.mb_strtolower($searchTerm).'%';

        $query->where(function (Builder $searchQuery) use ($needle): void {
            $searchQuery
                ->whereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(reference_code, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('CAST(amount AS TEXT) LIKE ?', [$needle])
                ->orWhereHas('category', function (Builder $categoryQuery) use ($needle): void {
                    $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                })
                ->orWhereHas('account', function (Builder $accountQuery) use ($needle): void {
                    $accountQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                })
                ->orWhereHas('trackedItem', function (Builder $trackedItemQuery) use ($needle): void {
                    $trackedItemQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                });
        });
    }

    protected function applyTextSearchToRecurringEntries(Builder $query, string $searchTerm): void
    {
        if ($searchTerm === '') {
            return;
        }

        $needle = '%'.mb_strtolower($searchTerm).'%';

        $query->where(function (Builder $searchQuery) use ($needle): void {
            $searchQuery
                ->whereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('CAST(COALESCE(expected_amount, total_amount, 0) AS TEXT) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(recurrence_type, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('CAST(COALESCE(recurrence_interval, 0) AS TEXT) LIKE ?', [$needle])
                ->orWhereHas('category', function (Builder $categoryQuery) use ($needle): void {
                    $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                })
                ->orWhereHas('account', function (Builder $accountQuery) use ($needle): void {
                    $accountQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                })
                ->orWhereHas('trackedItem', function (Builder $trackedItemQuery) use ($needle): void {
                    $trackedItemQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                });
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function sortItems(Collection $items): Collection
    {
        $sorted = $items->all();

        usort($sorted, function (array $left, array $right): int {
            return [$right['month_key'], $right['date'], $left['kind'], $left['title']]
                <=> [$left['month_key'], $left['date'], $right['kind'], $right['title']];
        });

        return collect($sorted);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array{
     *     month_key: string,
     *     month_start: string,
     *     items: array<int, array<string, mixed>>
     * }>
     */
    protected function groupItemsByMonth(Collection $items): array
    {
        return $items
            ->groupBy('month_key')
            ->map(function (Collection $group, string $monthKey): array {
                return [
                    'month_key' => $monthKey,
                    'month_start' => (string) ($group->first()['month_start'] ?? "{$monthKey}-01"),
                    'items' => $group->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
