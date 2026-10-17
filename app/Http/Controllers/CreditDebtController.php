<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditDebts\StoreCreditDebtItemRequest;
use App\Http\Requests\CreditDebts\UpdateCreditDebtItemRequest;
use App\Http\Requests\FinancialIndexRequest;
use App\Http\Resources\CreditDebtItemResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\TrackedItem;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyViewService;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\CreditDebtItemService;
use App\Services\CreditDebts\DerivedObligationsService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\UserYearService;
use App\Support\Banks\BankNamePresenter;
use App\Support\MoneyAmount;
use App\Supports\CategoryHierarchy;
use App\Supports\HierarchyOptionLabel;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        protected DerivedObligationsService $derivedObligationsService,
        protected CounterpartyBalanceService $counterpartyBalanceService,
    ) {}

    public function index(FinancialIndexRequest $request): JsonResponse|InertiaResponse
    {
        Gate::authorize('viewAny', CreditDebtItem::class);

        $query = CreditDebtItem::query()
            ->forUser($request->user())
            ->with([
                'account',
                'category',
                'reference',
                'counterparty',
                'payments.account',
                'payments.transaction',
                'counterparty.activeCommonRelations', 'activeCommonObligation.relation',
                'activeCommonObligation.ledgerEntries',
                'activeCommonObligation.allocationEntries',
            ])
            ->withReferenceConfirmationHistory()
            ->withCount('payments')
            ->latest();

        $this->applyIndexFilters($query, $request);

        $periodStart = CarbonImmutable::create($this->selectedYear($request), $this->selectedMonth($request) ?? 1, 1)->startOfDay();
        $items = collect(CreditDebtItemResource::collection($query->get())->resolve($request))
            ->filter(fn (array $item): bool => $item['due_date'] === null || $item['due_date'] >= $periodStart->toDateString() || $item['status'] !== 'settled');
        $items = $this->filterItemsByComputedStatus($items, $request);
        $counterpartyBalances = $this->counterpartyBalanceService->forUser($request->user());
        $derived = $this->derivedObligationsService->positions($request->user(), $counterpartyBalances);
        $payload = $items
            ->concat($this->filterDerivedPositions($derived, $request))->values()->all();

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        $openPositions = $this->derivedObligationsService->openPositions($request->user(), derived: $derived);

        return Inertia::render('credits-debts/Index', [
            'items' => $payload,
            'summary' => $this->summaryForCurrency($request, $openPositions, $request->user()->base_currency_code ?? 'EUR'),
            'summariesByCurrency' => $this->summariesByCurrency($request, $openPositions),
            'commonRelations' => app(CounterpartyViewService::class)->forUser($request->user()),
            'counterpartyBalances' => $counterpartyBalances,
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

        return CreditDebtItemResource::make($creditDebtItem->newQuery()->withReferenceConfirmationHistory()->findOrFail($creditDebtItem->id)->load([
            'counterparty',
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

        $item = app(TrackedItemIdentityService::class)->withNewReferences([
            'reference_id' => $request->validated('reference_id'),
            'counterparty_id' => $request->validated('counterparty_id'),
        ], fn () => $service->create($request->user(), $request->validated()));

        if ($request->expectsJson()) {
            return CreditDebtItemResource::make($item);
        }

        return back()->with('success', __('ui.credit_debt_created_cdbb88e3'));
    }

    public function update(UpdateCreditDebtItemRequest $request, CreditDebtItem $creditDebtItem, CreditDebtItemService $service): CreditDebtItemResource|RedirectResponse
    {
        Gate::authorize('update', $creditDebtItem);

        $item = $service->update($request->user(), $creditDebtItem, $request->validated());

        if ($request->expectsJson()) {
            return CreditDebtItemResource::make($item);
        }

        return back()->with('success', __('ui.credit_debt_updated_4ec69497'));
    }

    public function destroy(Request $request, CreditDebtItem $creditDebtItem, CreditDebtItemService $service): Response|RedirectResponse
    {
        Gate::authorize('delete', $creditDebtItem);

        $service->delete($request->user(), $creditDebtItem);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', __('ui.credit_debt_deleted_279be09b'));
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
            $query->whereHas('reference', fn (Builder $query): Builder => $query->whereIn('uuid', $this->referenceFamilyUuids($request)));
        }

        $periodStart = CarbonImmutable::create($this->selectedYear($request), $this->selectedMonth($request) ?? 1, 1)->startOfDay();
        $query->where(function (Builder $period) use ($request, $periodStart): void {
            $period->where(function (Builder $current) use ($request): void {
                $current->whereYear('due_date', $this->selectedYear($request));
                if ($this->selectedMonth($request) !== null) {
                    $current->whereMonth('due_date', $this->selectedMonth($request));
                }
            })->orWhereDate('due_date', '<', $periodStart->toDateString())->orWhereNull('due_date');
        });

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
                'future' => $query->whereDate('due_date', '>', $today->toDateString()),
                'undated' => $query->whereNull('due_date'),
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
                ->orWhereHas('counterparty', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$like]))
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

    /** @return array<int, string> */
    protected function referenceFamilyUuids(Request $request): array
    {
        $key = self::class.':family:'.$request->input('reference_uuid');
        if (! $request->attributes->has($key)) {
            $reference = TrackedItem::query()->ownedBy($request->user()->id)->where('uuid', $request->input('reference_uuid'))->first();
            $request->attributes->set($key, $reference === null ? [] : app(TrackedItemIdentityService::class)->familyMembers($reference)->pluck('uuid')->all());
        }

        return $request->attributes->get($key);
    }

    protected function filledFilter(Request $request, string $key): bool
    {
        return $request->filled($key) && $request->string($key)->toString() !== 'all';
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function filterDerivedPositions(Collection $positions, Request $request): Collection
    {
        return $positions->filter(function (array $item) use ($request): bool {
            foreach (['type', 'status'] as $key) {
                if ($this->filledFilter($request, $key) && $item[$key] !== $request->input($key)) {
                    return false;
                }
            }
            if ($this->filledFilter($request, 'reference_uuid') && ! in_array($item['reference']['uuid'] ?? null, $this->referenceFamilyUuids($request), true)) {
                return false;
            }
            foreach (['account', 'category'] as $relation) {
                if ($this->filledFilter($request, $relation.'_uuid') && ($item[$relation]['uuid'] ?? null) !== $request->input($relation.'_uuid')) {
                    return false;
                }
            }
            if ($request->filled('search') && ! str_contains(mb_strtolower(implode(' ', [
                $item['description'], $item['reference']['name'] ?? '', $item['account']['name'] ?? '',
                $item['category']['name'] ?? '', $item['remaining_amount'], $item['due_date'] ?? '',
            ])), mb_strtolower(trim((string) $request->input('search'))))) {
                return false;
            }
            if ($this->filledFilter($request, 'due_bucket')) {
                $date = $item['due_date'];
                $today = today()->toDateString();

                return match ($request->input('due_bucket')) {
                    'overdue' => $date !== null && $date < $today,
                    'current_month' => $date !== null && $date >= $today && substr($date, 0, 7) === sprintf('%04d-%02d', $this->selectedYear($request), $this->selectedMonth($request) ?? (int) now()->month),
                    'future' => $date !== null && $date > $today,
                    'undated' => $date === null,
                    default => true,
                };
            }

            return true;
        })->values();
    }

    /** @return array<string, array<string, string|int>> */
    protected function summariesByCurrency(Request $request, Collection $positions): array
    {
        $currencies = $positions->pluck('currency_code')->concat(CreditDebtItem::query()->forUser($request->user())->pluck('currency_code'))->push($request->user()->base_currency_code ?? 'EUR')->unique()->sort();

        return $currencies->mapWithKeys(fn (string $currency): array => [$currency => $this->summaryForCurrency($request, $positions, $currency)])->all();
    }

    /** @return array<string, string|int> */
    protected function summaryForCurrency(Request $request, Collection $positions, string $currency): array
    {
        $positions = $positions->where('currency_code', $currency);
        $summary = $this->derivedObligationsService->openSummary($positions);
        $today = today()->toDateString();
        $month = sprintf('%04d-%02d', $this->selectedYear($request), $this->selectedMonth($request) ?? (int) now()->month);
        foreach (['credit' => 'credits', 'debit' => 'debts'] as $type => $side) {
            $typed = $positions->where('type', $type);
            $sum = fn (Collection $items): string => MoneyAmount::decimal($items->sum(fn (array $item): int => MoneyAmount::cents($item['remaining_amount'])));
            $summary['current_month_'.$side.'_total'] = $sum($typed->filter(fn (array $item): bool => $item['due_date'] !== null && $item['due_date'] >= $today && substr($item['due_date'], 0, 7) === $month));
            $summary['future_'.$side.'_total'] = $sum($typed->filter(fn (array $item): bool => $item['due_date'] !== null && $item['due_date'] > $today));
            $summary['undated_'.$side.'_total'] = $sum($typed->whereNull('due_date'));
        }

        return $summary;
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
            ->filter(fn (array $item): bool => $item['status'] === $request->string('status')->toString())
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptionsPayload(Request $request): array
    {
        $accounts = $this->accessibleAccountsQuery->getEditable($request->user());
        $usedRelationIdsByAccount = CreditDebtItem::query()
            ->forUser($request->user())
            ->whereIn('account_id', $accounts->pluck('id'))
            ->get(['account_id', 'category_id', 'reference_id'])
            ->groupBy('account_id');

        $references = TrackedItem::query()->ownedBy($request->user()->id)
            ->where(function (Builder $query) use ($request): void {
                $query->where('is_active', true)->orWhereIn('id', CreditDebtItem::query()
                    ->forUser($request->user())->select('reference_id')->whereNotNull('reference_id'));
            })
            ->with(['compatibleCategories:id,uuid,parent_id,user_id', 'websiteIdentity'])
            ->orderBy('name')->get();
        $referencesByUuid = $references->keyBy('uuid');
        $referenceOptions = collect($this->operationalTransactionCategoryResolver->trackedItemOptionsFromCollection($references))
            ->map(fn (array $option): array => [...$option, 'is_selectable' => $referencesByUuid->get($option['value'])->is_active])
            ->all();

        return [
            'counterparties' => TrackedItem::query()->ownedBy($request->user()->id)
                ->where(function ($query) use ($request): void {
                    $query->where(fn ($active) => $active->where('is_active', true)->where('is_shared_expense_counterpart', true))
                        ->orWhereIn('id', CreditDebtItem::query()->forUser($request->user())->select('counterparty_id')->whereNotNull('counterparty_id'));
                })->orderBy('name')->get()->map(fn (TrackedItem $item): array => ['value' => $item->uuid, 'label' => $item->name, 'is_selectable' => $item->is_active && $item->is_shared_expense_counterpart])->all(),
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
                    $account->uuid => $this->categoryOptionsForAccount(
                        $account,
                        $usedRelationIdsByAccount->get($account->id, collect())->pluck('category_id')->filter()->map(fn ($id): int => (int) $id)->all(),
                    ),
                ])
                ->all(),
            'references' => $accounts
                ->mapWithKeys(fn (Account $account): array => [
                    $account->uuid => $referenceOptions,
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
    protected function categoryOptionsForAccount(Account $account, array $usedCategoryIds = []): array
    {
        $categories = $this->operationalTransactionCategoryResolver->categoriesForAccount($account, $usedCategoryIds)->values();
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
}
