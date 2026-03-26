<?php

namespace App\Http\Controllers;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Http\Requests\Recurring\StoreRecurringEntryRequest;
use App\Http\Requests\Recurring\UpdateRecurringEntryRequest;
use App\Http\Resources\RecurringEntryIndexResource;
use App\Http\Resources\RecurringEntryShowResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Supports\ManagementContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecurringEntryController extends Controller
{
    public function __construct(
        protected RecurringEntryManagementService $managementService,
        protected ManagementContextResolver $managementContextResolver,
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildIndexPayload($request);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('transactions/recurring/Index', $payload);
    }

    public function show(Request $request, RecurringEntry $recurringEntry): Response|JsonResponse
    {
        $entry = $this->accessibleRecurringEntry($request, $recurringEntry);
        $editableAccountIds = $this->accessibleAccountsQuery->editableIds($request->user());
        $request->attributes->set('recurring_editable_account_ids', $editableAccountIds);
        $entry->load([
            'account',
            'scope',
            'category',
            'trackedItem',
            'merchant',
            'createdByUser:id,uuid,name,email',
            'updatedByUser:id,uuid,name,email',
            'occurrences' => fn ($query) => $query
                ->orderByRaw('COALESCE(due_date, expected_date)')
                ->orderBy('sequence_number')
                ->orderBy('id'),
            'occurrences.recurringEntry',
            'occurrences.convertedTransaction.refundTransaction',
        ]);

        $payload = [
            'recurringEntry' => (new RecurringEntryShowResource($entry))->resolve(),
            'formOptions' => $this->formOptionsPayload($request),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('transactions/recurring/Show', $payload);
    }

    public function store(StoreRecurringEntryRequest $request): RedirectResponse
    {
        $entry = $this->managementService->store(
            $request->user(),
            $request->validated()
        );

        if ($request->string('redirect_to')->toString() === 'index') {
            return to_route('recurring-entries.index')
                ->with('success', __('transactions.flash.recurring_created'));
        }

        return to_route('recurring-entries.show', $entry->uuid)
            ->with('success', __('transactions.flash.recurring_created'));
    }

    public function update(UpdateRecurringEntryRequest $request, RecurringEntry $recurringEntry): RedirectResponse
    {
        $entry = $this->editableRecurringEntry($request, $recurringEntry);

        $entry = $this->managementService->update(
            $request->user(),
            $entry,
            $request->validated()
        );

        if ($request->string('redirect_to')->toString() === 'index') {
            return to_route('recurring-entries.index')
                ->with('success', __('transactions.flash.recurring_updated'));
        }

        return to_route('recurring-entries.show', $entry->uuid)
            ->with('success', __('transactions.flash.recurring_updated'));
    }

    public function pause(Request $request, RecurringEntry $recurringEntry): RedirectResponse
    {
        $entry = $this->managementService->pause(
            $this->editableRecurringEntry($request, $recurringEntry)
        );

        return to_route('recurring-entries.show', $entry->uuid)
            ->with('success', 'Piano programmato sospeso.');
    }

    public function resume(Request $request, RecurringEntry $recurringEntry): RedirectResponse
    {
        $entry = $this->managementService->resume(
            $this->editableRecurringEntry($request, $recurringEntry)
        );

        return to_route('recurring-entries.show', $entry->uuid)
            ->with('success', 'Piano programmato riattivato.');
    }

    public function cancel(Request $request, RecurringEntry $recurringEntry): RedirectResponse
    {
        $entry = $this->managementService->cancel(
            $this->editableRecurringEntry($request, $recurringEntry)
        );

        return to_route('recurring-entries.show', $entry->uuid)
            ->with('success', 'Piano programmato annullato.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIndexPayload(Request $request): array
    {
        $user = $request->user();
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveDashboard($request, $user);

        $month ??= $this->fallbackMonth($year);

        $this->managementContextResolver->persist($user, $year, $month);
        $accessibleAccountIds = $this->accessibleAccountsQuery->ids($user);
        $editableAccountIds = $this->accessibleAccountsQuery->editableIds($user);
        $editableOwnerIds = $this->accessibleAccountsQuery->editableOwnerIds($user);
        $request->attributes->set('recurring_editable_account_ids', $editableAccountIds);

        $query = RecurringEntry::query()
            ->whereIn('account_id', $accessibleAccountIds)
            ->with([
                'account:id,uuid,name,currency',
                'scope:id,uuid,name',
                'category:id,uuid,name',
                'trackedItem:id,uuid,name',
                'merchant:id,uuid,name',
                'createdByUser:id,uuid,name,email',
                'updatedByUser:id,uuid,name,email',
                'occurrences' => fn ($query) => $query
                    ->select([
                        'id',
                        'uuid',
                        'recurring_entry_id',
                        'expected_date',
                        'due_date',
                        'expected_amount',
                        'status',
                        'converted_transaction_id',
                    ])
                    ->orderByRaw('COALESCE(due_date, expected_date)')
                    ->orderBy('sequence_number')
                    ->orderBy('id'),
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('entry_type')) {
            $query->where('entry_type', (string) $request->input('entry_type'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', (string) $request->input('direction'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', (int) $request->input('account_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('auto_create_transaction')) {
            $query->where('auto_create_transaction', $request->boolean('auto_create_transaction'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = (string) $request->input('sort', 'created_at');
        $direction = (string) $request->input('direction_sort', 'desc');
        $allowedSorts = ['created_at', 'next_occurrence_date', 'title'];

        $query->orderBy(
            in_array($sort, $allowedSorts, true) ? $sort : 'created_at',
            $direction === 'asc' ? 'asc' : 'desc'
        );

        $entries = $query->get();

        return [
            'activePeriod' => $this->activePeriodPayload($year, $month),
            'filters' => [
                'status' => $request->input('status'),
                'entry_type' => $request->input('entry_type'),
                'direction' => $request->input('direction'),
                'account_id' => $request->input('account_id'),
                'category_id' => $request->input('category_id'),
                'auto_create_transaction' => $request->input('auto_create_transaction'),
                'search' => $request->input('search'),
                'sort' => $sort,
                'direction_sort' => $direction,
            ],
            'recurringEntries' => RecurringEntryIndexResource::collection($entries)->resolve(),
            'monthlyCalendar' => $this->monthlyCalendarPayload($entries, $year, $month, $editableAccountIds),
            'formOptions' => $this->formOptionsPayload($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function activePeriodPayload(int $year, int $month): array
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $periodStart
                ->locale(app()->getLocale())
                ->translatedFormat('F'),
            'period_label' => $periodStart
                ->locale(app()->getLocale())
                ->translatedFormat('F Y'),
            'starts_at' => $periodStart->toDateString(),
            'ends_at' => $periodStart->endOfMonth()->toDateString(),
        ];
    }

    /**
     * @param  EloquentCollection<int, RecurringEntry>  $entries
     * @return array<string, mixed>
     */
    protected function monthlyCalendarPayload(EloquentCollection $entries, int $year, int $month, array $editableAccountIds = []): array
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        if ($entries->isEmpty()) {
            return [
                ...$this->activePeriodPayload($year, $month),
                'summary' => [
                    'entries_count' => 0,
                    'occurrences_count' => 0,
                    'pending_count' => 0,
                    'converted_count' => 0,
                    'planned_income_total' => 0.0,
                    'planned_expense_total' => 0.0,
                ],
                'days' => [],
            ];
        }

        $occurrences = RecurringEntryOccurrence::query()
            ->whereIn('recurring_entry_id', $entries->modelKeys())
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query
                    ->whereBetween('due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orWhere(function ($fallbackQuery) use ($periodStart, $periodEnd): void {
                        $fallbackQuery
                            ->whereNull('due_date')
                            ->whereBetween('expected_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
                    });
            })
            ->with([
                'recurringEntry:id,uuid,title,description,direction,entry_type,status,currency,auto_create_transaction,account_id,category_id,tracked_item_id',
                'recurringEntry.account:id,uuid,name,currency',
                'recurringEntry.category:id,uuid,name',
                'recurringEntry.trackedItem:id,uuid,name',
                'convertedTransaction:id,uuid,transaction_date,amount,currency,kind',
                'convertedTransaction.refundTransaction:id,uuid,transaction_date,amount,currency,refunded_transaction_id',
            ])
            ->orderByRaw('COALESCE(due_date, expected_date)')
            ->orderBy('sequence_number')
            ->get();

        $groupedDays = $occurrences
            ->groupBy(fn (RecurringEntryOccurrence $occurrence): string => $this->occurrenceDate($occurrence)->toDateString())
            ->map(function ($dayOccurrences, string $date) use ($editableAccountIds): array {
                $incomeTotal = 0.0;
                $expenseTotal = 0.0;
                $pendingCount = 0;
                $convertedCount = 0;

                $mappedOccurrences = $dayOccurrences
                    ->map(function (RecurringEntryOccurrence $occurrence) use (&$incomeTotal, &$expenseTotal, &$pendingCount, &$convertedCount, $editableAccountIds): array {
                        $entry = $occurrence->recurringEntry;
                        $amount = (float) ($occurrence->expected_amount ?? 0);
                        $direction = $entry?->direction?->value;
                        $status = $occurrence->status?->value;
                        $canEdit = $entry !== null
                            && in_array((int) $entry->account_id, $editableAccountIds, true);

                        if ($direction === 'income') {
                            $incomeTotal += $amount;
                        } else {
                            $expenseTotal += $amount;
                        }

                        if (in_array($status, [RecurringOccurrenceStatusEnum::PENDING->value, RecurringOccurrenceStatusEnum::GENERATED->value], true)) {
                            $pendingCount++;
                        }

                        if ($occurrence->converted_transaction_id !== null) {
                            $convertedCount++;
                        }

                        return [
                            'uuid' => $occurrence->uuid,
                            'sequence_number' => $occurrence->sequence_number,
                            'status' => $status,
                            'expected_date' => $occurrence->expected_date?->toDateString(),
                            'due_date' => $occurrence->due_date?->toDateString(),
                            'display_date' => $this->occurrenceDate($occurrence)->toDateString(),
                            'expected_amount' => $occurrence->expected_amount !== null ? (float) $occurrence->expected_amount : null,
                            'currency' => $entry?->currency,
                            'notes' => $occurrence->notes,
                            'direction' => $direction,
                            'entry_type' => $entry?->entry_type?->value,
                            'title' => $entry?->title,
                            'description' => $entry?->description,
                            'can_convert' => $canEdit
                                && $occurrence->converted_transaction_id === null
                                && in_array($status, ['pending', 'generated'], true),
                            'converted_transaction' => $occurrence->convertedTransaction === null ? null : [
                                'uuid' => $occurrence->convertedTransaction->uuid,
                                'kind' => $occurrence->convertedTransaction->kind?->value,
                                'transaction_date' => $occurrence->convertedTransaction->transaction_date?->toDateString(),
                                'amount' => (float) $occurrence->convertedTransaction->amount,
                                'currency' => $occurrence->convertedTransaction->currency,
                                'show_url' => $this->transactionShowUrl($occurrence->convertedTransaction),
                                'is_refunded' => $occurrence->convertedTransaction->refundTransaction !== null,
                                'can_refund' => $canEdit
                                    && $this->canRefundFromRecurringContext($occurrence)
                                    && in_array($occurrence->convertedTransaction->kind?->value, ['manual', 'scheduled'], true)
                                    && $occurrence->convertedTransaction->refundTransaction === null,
                                'refund_transaction' => $occurrence->convertedTransaction->refundTransaction === null ? null : [
                                    'uuid' => $occurrence->convertedTransaction->refundTransaction->uuid,
                                    'transaction_date' => $occurrence->convertedTransaction->refundTransaction->transaction_date?->toDateString(),
                                    'show_url' => $this->transactionShowUrl($occurrence->convertedTransaction->refundTransaction),
                                ],
                            ],
                            'recurring_entry' => $entry === null ? null : [
                                'uuid' => $entry->uuid,
                                'title' => $entry->title,
                                'status' => $entry->status?->value,
                                'auto_create_transaction' => (bool) $entry->auto_create_transaction,
                                'account' => $entry->account === null ? null : [
                                    'uuid' => $entry->account->uuid,
                                    'name' => $entry->account->name,
                                    'currency' => $entry->account->currency,
                                ],
                                'category' => $entry->category === null ? null : [
                                    'uuid' => $entry->category->uuid,
                                    'name' => $entry->category->name,
                                ],
                                'tracked_item' => $entry->trackedItem === null ? null : [
                                    'uuid' => $entry->trackedItem->uuid,
                                    'name' => $entry->trackedItem->name,
                                ],
                                'show_url' => route('recurring-entries.show', $entry->uuid),
                            ],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'date' => $date,
                    'anchor' => "occurrence-day-{$date}",
                    'income_total' => round($incomeTotal, 2),
                    'expense_total' => round($expenseTotal, 2),
                    'occurrences_count' => count($mappedOccurrences),
                    'pending_count' => $pendingCount,
                    'converted_count' => $convertedCount,
                    'occurrences' => $mappedOccurrences,
                ];
            })
            ->sortKeys()
            ->values();

        return [
            ...$this->activePeriodPayload($year, $month),
            'summary' => [
                'entries_count' => $entries->count(),
                'occurrences_count' => $occurrences->count(),
                'pending_count' => $occurrences->filter(
                    fn (RecurringEntryOccurrence $occurrence): bool => in_array($occurrence->status?->value, ['pending', 'generated'], true)
                )->count(),
                'converted_count' => $occurrences->whereNotNull('converted_transaction_id')->count(),
                'planned_income_total' => round($occurrences->sum(function (RecurringEntryOccurrence $occurrence): float {
                    if ($occurrence->recurringEntry?->direction?->value !== 'income') {
                        return 0.0;
                    }

                    return (float) ($occurrence->expected_amount ?? 0);
                }), 2),
                'planned_expense_total' => round($occurrences->sum(function (RecurringEntryOccurrence $occurrence): float {
                    if ($occurrence->recurringEntry?->direction?->value !== 'expense') {
                        return 0.0;
                    }

                    return (float) ($occurrence->expected_amount ?? 0);
                }), 2),
            ],
            'days' => $groupedDays->all(),
        ];
    }

    protected function occurrenceDate(RecurringEntryOccurrence $occurrence): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $occurrence->due_date?->toDateString() ?? $occurrence->expected_date?->toDateString()
        )->startOfDay();
    }

    protected function fallbackMonth(int $year): int
    {
        $now = now(config('app.timezone'));

        return $year === (int) $now->year ? (int) $now->month : 1;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptionsPayload(Request $request): array
    {
        $user = $request->user();
        $editableAccounts = $this->accessibleAccountsQuery->editable($user)
            ->orderByDesc('is_owned')
            ->orderBy('accounts.name')
            ->get(['accounts.*']);
        $accessibleAccounts = $this->accessibleAccountsQuery->get($user);

        return [
            'accounts' => $editableAccounts
                ->map(fn (Account $account): array => [
                    'value' => $account->uuid,
                    'label' => $this->recurringAccountLabel($account),
                    'currency' => $account->currency,
                    'owner_user_id' => (int) $account->user_id,
                    'category_contributor_user_ids' => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account),
                    'scope_contributor_user_ids' => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account),
                    'tracked_item_contributor_user_ids' => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account),
                    'is_owned' => (bool) $account->getAttribute('is_owned'),
                    'is_shared' => (bool) $account->getAttribute('is_shared'),
                    'membership_role' => $account->getAttribute('membership_role'),
                    'membership_status' => $account->getAttribute('membership_status'),
                    'can_edit' => (bool) $account->getAttribute('can_edit'),
                ])
                ->all(),
            'filter_accounts' => $accessibleAccounts
                ->map(fn (Account $account): array => [
                    'value' => (string) $account->id,
                    'label' => $this->recurringAccountLabel($account),
                    'currency' => $account->currency,
                    'is_owned' => (bool) $account->getAttribute('is_owned'),
                    'is_shared' => (bool) $account->getAttribute('is_shared'),
                    'membership_role' => $account->getAttribute('membership_role'),
                    'membership_status' => $account->getAttribute('membership_status'),
                    'can_edit' => (bool) $account->getAttribute('can_edit'),
                ])
                ->all(),
            'scopes' => $editableAccounts
                ->flatMap(
                    fn (Account $account) => $this->operationalTransactionCategoryResolver->scopesForAccount($account)
                )
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn (Scope $scope): array => [
                    'value' => $scope->uuid,
                    'label' => $scope->name,
                    'owner_user_id' => (int) $scope->user_id,
                ])
                ->all(),
            'categories' => $editableAccounts
                ->flatMap(
                    fn (Account $account) => $this->operationalTransactionCategoryResolver->categoriesForAccount($account)
                )
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn (Category $category): array => [
                    'value' => $category->uuid,
                    'label' => $category->name,
                    'direction_type' => $category->direction_type?->value,
                    'owner_user_id' => (int) $category->user_id,
                ])
                ->all(),
            'tracked_items' => $editableAccounts
                ->flatMap(
                    fn (Account $account) => $this->operationalTransactionCategoryResolver->trackedItemsForAccount($account)
                )
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn (TrackedItem $trackedItem): array => [
                    'value' => $trackedItem->uuid,
                    'label' => $trackedItem->name,
                    'owner_user_id' => (int) $trackedItem->user_id,
                ])
                ->all(),
            'merchants' => Merchant::query()
                ->whereIn('user_id', $editableAccounts->pluck('user_id')->unique()->all() !== [] ? $editableAccounts->pluck('user_id')->unique()->all() : [0])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['uuid', 'name'])
                ->map(fn (Merchant $merchant): array => [
                    'value' => $merchant->uuid,
                    'label' => $merchant->name,
                ])
                ->all(),
            'directions' => collect([
                TransactionDirectionEnum::INCOME,
                TransactionDirectionEnum::EXPENSE,
            ])->map(fn (TransactionDirectionEnum $direction): array => [
                'value' => $direction->value,
                'label' => $direction->label(),
            ])->values()->all(),
            'entry_types' => [
                [
                    'value' => RecurringEntryTypeEnum::RECURRING->value,
                    'label' => __('transactions.recurring.entry_types.recurring'),
                ],
                [
                    'value' => RecurringEntryTypeEnum::INSTALLMENT->value,
                    'label' => __('transactions.recurring.entry_types.installment'),
                ],
            ],
            'statuses' => [
                [
                    'value' => RecurringEntryStatusEnum::ACTIVE->value,
                    'label' => __('transactions.recurring.plan_statuses.active'),
                ],
                [
                    'value' => RecurringEntryStatusEnum::PAUSED->value,
                    'label' => __('transactions.recurring.plan_statuses.paused'),
                ],
                [
                    'value' => RecurringEntryStatusEnum::COMPLETED->value,
                    'label' => __('transactions.recurring.plan_statuses.completed'),
                ],
                [
                    'value' => RecurringEntryStatusEnum::CANCELLED->value,
                    'label' => __('transactions.recurring.plan_statuses.cancelled'),
                ],
            ],
            'end_modes' => [
                [
                    'value' => RecurringEndModeEnum::NEVER->value,
                    'label' => __('transactions.recurring.end_modes.never'),
                ],
                [
                    'value' => RecurringEndModeEnum::AFTER_OCCURRENCES->value,
                    'label' => __('transactions.recurring.end_modes.after_occurrences'),
                ],
                [
                    'value' => RecurringEndModeEnum::UNTIL_DATE->value,
                    'label' => __('transactions.recurring.end_modes.until_date'),
                ],
            ],
            'recurrence_types' => collect([
                RecurringEntryRecurrenceTypeEnum::DAILY,
                RecurringEntryRecurrenceTypeEnum::WEEKLY,
                RecurringEntryRecurrenceTypeEnum::MONTHLY,
                RecurringEntryRecurrenceTypeEnum::QUARTERLY,
                RecurringEntryRecurrenceTypeEnum::YEARLY,
            ])->map(fn (RecurringEntryRecurrenceTypeEnum $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->all(),
        ];
    }

    protected function recurringAccountLabel(Account $account): string
    {
        $bankName = $account->userBank?->name ?? $account->bank?->name;

        return collect([$bankName, $account->name])
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->implode(' · ');
    }

    protected function transactionShowUrl(Transaction $transaction): ?string
    {
        if ($transaction->transaction_date === null) {
            return null;
        }

        return route('transactions.show', [
            'year' => $transaction->transaction_date->year,
            'month' => $transaction->transaction_date->month,
            'highlight' => $transaction->uuid,
            'source' => 'recurring',
        ]);
    }

    protected function canRefundFromRecurringContext(RecurringEntryOccurrence $occurrence): bool
    {
        if ($occurrence->convertedTransaction === null) {
            return false;
        }

        $latestConvertedOccurrenceId = RecurringEntryOccurrence::query()
            ->where('recurring_entry_id', $occurrence->recurring_entry_id)
            ->whereNotNull('converted_transaction_id')
            ->orderByRaw('COALESCE(due_date, expected_date) desc')
            ->orderByDesc('sequence_number')
            ->orderByDesc('id')
            ->value('id');

        return $latestConvertedOccurrenceId === $occurrence->id;
    }

    protected function accessibleRecurringEntry(Request $request, RecurringEntry $recurringEntry, bool $requireEdit = false): RecurringEntry
    {
        abort_unless(
            $this->accessibleAccountsQuery->canViewAccountId($request->user(), (int) $recurringEntry->account_id),
            404
        );

        if (
            $requireEdit
            && ! $this->accessibleAccountsQuery->canEditAccountId($request->user(), (int) $recurringEntry->account_id)
        ) {
            throw ValidationException::withMessages([
                'entry' => __('transactions.validation.account_read_only'),
            ]);
        }

        return $recurringEntry;
    }

    protected function editableRecurringEntry(Request $request, RecurringEntry $recurringEntry): RecurringEntry
    {
        return $this->accessibleRecurringEntry($request, $recurringEntry, true);
    }
}
