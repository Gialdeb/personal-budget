<?php

namespace App\Http\Controllers;

use App\Enums\CreditDebtTypeEnum;
use App\Http\Requests\CreditDebts\StoreCreditDebtItemRequest;
use App\Http\Requests\CreditDebts\UpdateCreditDebtItemRequest;
use App\Http\Resources\CreditDebtItemResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\CreditDebts\CreditDebtItemService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\UserYearService;
use App\Support\Banks\BankNamePresenter;
use App\Supports\CategoryHierarchy;
use App\Supports\HierarchyOptionLabel;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CreditDebtController extends Controller
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected UserYearService $userYearService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        Gate::authorize('viewAny', CreditDebtItem::class);

        $query = CreditDebtItem::query()
            ->forUser($request->user())
            ->with([
                'account',
                'category',
                'reference',
                'payments.account',
                'payments.transaction',
            ])
            ->withCount('payments')
            ->latest();

        $this->applyIndexFilters($query, $request);

        $items = $this->filterItemsByComputedStatus($query->get(), $request);

        if ($request->expectsJson()) {
            return CreditDebtItemResource::collection($items);
        }

        return Inertia::render('credits-debts/Index', [
            'items' => CreditDebtItemResource::collection($items)->resolve($request),
            'summary' => $this->summaryPayload($request),
            'filters' => [
                ...$request->only([
                    'search',
                    'type',
                    'status',
                    'due_bucket',
                    'reference_uuid',
                    'account_uuid',
                    'category_uuid',
                    'month',
                    'selected',
                    'highlight',
                ]),
                'selected' => (string) ($request->input('selected') ?? $request->input('highlight') ?? ''),
                'year' => (string) $this->selectedYear($request),
                'month' => $this->selectedMonth($request) === null ? 'all' : (string) $this->selectedMonth($request),
            ],
            'options' => $this->formOptionsPayload($request),
            'today' => CarbonImmutable::now(config('app.timezone'))->toDateString(),
        ]);
    }

    public function show(CreditDebtItem $creditDebtItem): CreditDebtItemResource
    {
        Gate::authorize('view', $creditDebtItem);

        return CreditDebtItemResource::make($creditDebtItem->load([
            'account',
            'category',
            'reference',
            'payments.account',
            'payments.transaction',
        ])->loadCount('payments'));
    }

    public function store(StoreCreditDebtItemRequest $request, CreditDebtItemService $service): CreditDebtItemResource|RedirectResponse
    {
        Gate::authorize('create', CreditDebtItem::class);

        $item = $service->create($request->user(), $request->validated());

        if ($request->expectsJson()) {
            return CreditDebtItemResource::make($item);
        }

        return back()->with('success', 'Credito/debito creato.');
    }

    public function update(UpdateCreditDebtItemRequest $request, CreditDebtItem $creditDebtItem, CreditDebtItemService $service): CreditDebtItemResource|RedirectResponse
    {
        Gate::authorize('update', $creditDebtItem);

        $item = $service->update($request->user(), $creditDebtItem, $request->validated());

        if ($request->expectsJson()) {
            return CreditDebtItemResource::make($item);
        }

        return back()->with('success', 'Credito/debito aggiornato.');
    }

    public function destroy(Request $request, CreditDebtItem $creditDebtItem, CreditDebtItemService $service): Response|RedirectResponse
    {
        Gate::authorize('delete', $creditDebtItem);

        $service->delete($request->user(), $creditDebtItem);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Credito/debito eliminato.');
    }

    protected function applyIndexFilters(Builder $query, Request $request): void
    {
        if ($this->filledFilter($request, 'type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($this->filledFilter($request, 'account_uuid')) {
            $query->whereHas('account', fn (Builder $query): Builder => $query->where('uuid', $request->string('account_uuid')->toString()));
        }

        if ($this->filledFilter($request, 'category_uuid')) {
            $query->whereHas('category', fn (Builder $query): Builder => $query->where('uuid', $request->string('category_uuid')->toString()));
        }

        if ($this->filledFilter($request, 'reference_uuid')) {
            $query->whereHas('reference', fn (Builder $query): Builder => $query->where('uuid', $request->string('reference_uuid')->toString()));
        }

        $query->whereYear('due_date', $this->selectedYear($request));

        if ($this->selectedMonth($request) !== null) {
            $query->whereMonth('due_date', $this->selectedMonth($request));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            if ($search !== '') {
                $this->applySearchFilter($query, $search);
            }
        }

        if ($this->filledFilter($request, 'due_bucket')) {
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            $periodStart = $this->selectedMonth($request) === null
                ? $today
                : CarbonImmutable::create($this->selectedYear($request), $this->selectedMonth($request), 1, 0, 0, 0, config('app.timezone'));
            $endOfMonth = $periodStart->endOfMonth();

            match ($request->string('due_bucket')->toString()) {
                'overdue' => $query->whereDate('due_date', '<', $today->toDateString()),
                'current_month' => $query
                    ->whereDate('due_date', '>=', $today->toDateString())
                    ->whereBetween('due_date', [$periodStart->toDateString(), $endOfMonth->toDateString()]),
                'future' => $query->where(function ($query) use ($today): void {
                    $query->whereNull('due_date')->orWhereDate('due_date', '>', $today->toDateString());
                }),
                default => null,
            };
        }
    }

    protected function applySearchFilter(Builder $query, string $search): void
    {
        $like = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($like): void {
            $query
                ->whereRaw('LOWER(description) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(note, \'\')) LIKE ?', [$like])
                ->orWhereRaw('CAST(total_amount AS TEXT) LIKE ?', [$like])
                ->orWhereRaw('CAST(due_date AS TEXT) LIKE ?', [$like])
                ->orWhereRaw(
                    'CAST((total_amount - COALESCE((select sum(amount) from credit_debt_payments where credit_debt_payments.credit_debt_item_id = credit_debt_items.id and credit_debt_payments.deleted_at is null), 0)) AS TEXT) LIKE ?',
                    [$like],
                )
                ->orWhereHas('reference', function (Builder $query) use ($like): void {
                    $query
                        ->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
                })
                ->orWhereHas('category', function (Builder $query) use ($like): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$like]);
                })
                ->orWhereHas('account', function (Builder $query) use ($like): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$like]);
                });
        });
    }

    protected function filledFilter(Request $request, string $key): bool
    {
        return $request->filled($key) && $request->string($key)->toString() !== 'all';
    }

    /**
     * @param  Collection<int, CreditDebtItem>  $items
     * @return Collection<int, CreditDebtItem>
     */
    protected function filterItemsByComputedStatus(Collection $items, Request $request): Collection
    {
        if (! $this->filledFilter($request, 'status')) {
            return $items;
        }

        return $items
            ->filter(fn (CreditDebtItem $item): bool => $item->status()->value === $request->string('status')->toString())
            ->values();
    }

    /**
     * @return array<string, string|int>
     */
    protected function summaryPayload(Request $request): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $periodStart = $this->selectedMonth($request) === null
            ? $today
            : CarbonImmutable::create($this->selectedYear($request), $this->selectedMonth($request), 1, 0, 0, 0, config('app.timezone'));
        $endOfMonth = $periodStart->endOfMonth();
        $items = CreditDebtItem::query()
            ->forUser($request->user())
            ->with('payments')
            ->whereYear('due_date', $this->selectedYear($request));

        if ($this->selectedMonth($request) !== null) {
            $items->whereMonth('due_date', $this->selectedMonth($request));
        }

        $items = $items->get();

        $remaining = fn (CreditDebtItem $item): float => (float) $item->remainingAmount();
        $credits = $items->where('type', CreditDebtTypeEnum::CREDIT);
        $debts = $items->where('type', CreditDebtTypeEnum::DEBIT);
        $overdue = $items->filter(fn (CreditDebtItem $item): bool => $item->due_date !== null && $item->due_date->lt($today) && ! $item->isSettled());
        $currentMonth = $items->filter(fn (CreditDebtItem $item): bool => $item->due_date !== null && $item->due_date->gte($today) && $item->due_date->betweenIncluded($periodStart, $endOfMonth) && ! $item->isSettled());
        $future = $items->filter(fn (CreditDebtItem $item): bool => ($item->due_date === null || $item->due_date->gt($today)) && ! $item->isSettled());

        $sum = fn (Collection $collection): string => number_format($collection->sum(fn (CreditDebtItem $item): float => $remaining($item)), 2, '.', '');

        $creditsRemaining = (float) $sum($credits);
        $debtsRemaining = (float) $sum($debts);

        return [
            'credits_remaining_total' => number_format($creditsRemaining, 2, '.', ''),
            'debts_remaining_total' => number_format($debtsRemaining, 2, '.', ''),
            'overdue_count' => $overdue->count(),
            'overdue_credits_total' => $sum($overdue->where('type', CreditDebtTypeEnum::CREDIT)),
            'overdue_debts_total' => $sum($overdue->where('type', CreditDebtTypeEnum::DEBIT)),
            'current_month_credits_total' => $sum($currentMonth->where('type', CreditDebtTypeEnum::CREDIT)),
            'current_month_debts_total' => $sum($currentMonth->where('type', CreditDebtTypeEnum::DEBIT)),
            'future_credits_total' => $sum($future->where('type', CreditDebtTypeEnum::CREDIT)),
            'future_debts_total' => $sum($future->where('type', CreditDebtTypeEnum::DEBIT)),
            'net_expected_total' => number_format($creditsRemaining - $debtsRemaining, 2, '.', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptionsPayload(Request $request): array
    {
        $accounts = $this->accessibleAccountsQuery
            ->editable($request->user())
            ->with('accountType:id,code')
            ->orderByDesc('is_owned')
            ->orderBy('accounts.name')
            ->get(['accounts.*']);

        return [
            'accounts' => $accounts->map(fn (Account $account): array => [
                'value' => $account->uuid,
                'uuid' => $account->uuid,
                'label' => $account->name,
                'currency_code' => $account->currency_code,
                'bank_name' => BankNamePresenter::forAccount($account),
                'account_type_code' => $account->accountType?->code,
            ])->values()->all(),
            'categories' => $accounts
                ->mapWithKeys(fn (Account $account): array => [
                    $account->uuid => $this->categoryOptionsForAccount($account),
                ])
                ->all(),
            'references' => $accounts
                ->mapWithKeys(fn (Account $account): array => [
                    $account->uuid => $this->trackedItemOptionsForAccount($account),
                ])
                ->all(),
            'currencies' => collect(config('currencies.supported', []))
                ->keys()
                ->map(fn (string $code): array => [
                    'value' => $code,
                    'label' => $code,
                ])
                ->values()
                ->all(),
            'years' => PeriodOptions::yearOptions($this->availableYears($request)),
            'months' => PeriodOptions::monthOptions(),
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function availableYears(Request $request): array
    {
        $currentYear = CarbonImmutable::now(config('app.timezone'))->year;
        $years = $this->userYearService->availableYears($request->user());

        if ($years === []) {
            return [$currentYear];
        }

        return collect([...$years, $currentYear])
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function selectedYear(Request $request): int
    {
        $currentYear = CarbonImmutable::now(config('app.timezone'))->year;
        $availableYears = $this->availableYears($request);
        $requestedYear = $request->filled('year') && is_numeric($request->input('year'))
            ? (int) $request->input('year')
            : $currentYear;

        if (in_array($requestedYear, $availableYears, true)) {
            return $requestedYear;
        }

        return in_array($currentYear, $availableYears, true)
            ? $currentYear
            : max($availableYears);
    }

    protected function selectedMonth(Request $request): ?int
    {
        if (! $request->filled('month')) {
            return null;
        }

        $month = $request->input('month');

        if ($month === 'all') {
            return null;
        }

        return PeriodOptions::isValidMonth(is_numeric($month) ? (int) $month : null, false)
            ? (int) $month
            : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function categoryOptionsForAccount(Account $account): array
    {
        $categories = $this->operationalTransactionCategoryResolver->categoriesForAccount($account)->values();
        $categoriesById = $categories->keyBy('id');

        return HierarchyOptionLabel::withDisambiguatedLabels(
            collect(CategoryHierarchy::buildFlat($categories))
                ->map(function (array $category) use ($categoriesById): array {
                    $sourceCategory = $categoriesById->get($category['id']);

                    return [
                        'value' => $category['uuid'],
                        'uuid' => $category['uuid'],
                        'label' => $category['name'],
                        'full_path' => $category['full_path'],
                        'direction_type' => $category['direction_type'],
                        'is_selectable' => (bool) ($category['is_selectable'] ?? false),
                        'owner_user_id' => $sourceCategory instanceof Category ? (int) $sourceCategory->user_id : null,
                        'ancestor_uuids' => collect($category['ancestor_uuids'] ?? [])
                            ->filter(fn ($value): bool => is_string($value) && $value !== '')
                            ->values()
                            ->all(),
                    ];
                })
        )->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function trackedItemOptionsForAccount(Account $account): array
    {
        return collect(
            $this->operationalTransactionCategoryResolver->trackedItemOptionsFromCollection(
                $this->operationalTransactionCategoryResolver->trackedItemsForAccount($account)
            )
        )
            ->map(fn (array $trackedItem): array => [
                ...$trackedItem,
                'account_uuid' => $account->uuid,
            ])
            ->values()
            ->all();
    }
}
