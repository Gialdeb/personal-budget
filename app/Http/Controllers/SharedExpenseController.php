<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialIndexRequest;
use App\Http\Requests\SharedExpenses\ResolveSharedExpenseReferenceRequest;
use App\Http\Requests\SharedExpenses\StoreSharedExpenseCounterpartyRequest;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Counterparties\CounterpartyViewService;
use App\Services\Counterparties\SharedSourceProjector;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\ManualEconomicSubjectResolver;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use App\Services\SharedExpenses\ResolveSharedExpenseReference;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\TrackedItems\PendingTrackedItemService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\TrackedItems\TrackedItemResolver;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Support\MoneyAmount;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SharedExpenseController extends Controller
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected SharedExpenseAllocation $sharedExpenseAllocation,
        protected SharedExpenseSettlementService $sharedExpenseSettlementService,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected ManagementContextResolver $managementContextResolver,
        protected CounterpartyBalanceService $counterpartyBalanceService,
    ) {}

    public function index(FinancialIndexRequest $request): Response
    {
        $user = $request->user();
        ['year' => $year, 'month' => $contextMonth] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $contextMonth ?? now()->month;
        $this->managementContextResolver->persist($user, $year, $month);
        $entryUuid = $request->query('entry');
        $search = trim((string) $request->query('search', ''));
        $counterpartyUuid = $request->query('counterparty');
        $filterParty = is_string($counterpartyUuid) ? TrackedItem::query()->ownedBy($user->id)->where('uuid', $counterpartyUuid)->first() : null;
        $filterPartyIds = $filterParty === null ? [] : app(TrackedItemIdentityService::class)->familyMembers($filterParty)->modelKeys();
        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();
        $effect = $request->has('effect') ? $request->string('effect')->toString() : ($entryUuid ? 'all' : 'pending');
        $accountIds = $this->accessibleAccountsQuery->get($user)->pluck('id');
        $sharedPeople = TrackedItem::query()->ownedBy($user->id)
            ->where(function ($query) use ($user, $accountIds): void {
                $query->whereIn('id', Transaction::query()->whereIn('account_id', $accountIds)->select('shared_expense_counterparty_id')->whereNotNull('shared_expense_counterparty_id'))
                    ->orWhereIn('id', SharedExpenseLedgerEntry::query()->where('user_id', $user->id)->select('counterparty_id'));
            })->pluck('id');
        $manualItems = CreditDebtItem::query()->forUser($user)->with('reference')->withCount('payments')->get();
        $resolver = app(ManualEconomicSubjectResolver::class);
        $confirmedManualItems = $manualItems->filter(fn (CreditDebtItem $item): bool => $resolver->consistent($item))->groupBy('counterparty_id');
        $settlementPeople = SharedExpenseSettlement::query()->where('user_id', $user->id)->pluck('counterparty_id');
        $paymentPeople = CounterpartyRelation::query()->where('user_id', $user->id)->whereNotNull('activated_at')
            ->whereHas('payments')->pluck('counterparty_id');
        $economicPeople = $sharedPeople->merge($confirmedManualItems->keys())->merge($settlementPeople)->merge($paymentPeople)->unique();
        $counterparties = TrackedItem::query()->ownedBy($user->id)
            ->where(fn ($query) => $query->where('is_shared_expense_counterpart', true)->orWhereIn('id', $economicPeople))
            ->orderBy('name')->get(['id', 'uuid', 'user_id', 'account_id', 'name', 'is_active', 'is_shared_expense_counterpart']);
        $counterpartyIds = $counterparties->pluck('id');
        $unassignedRepayments = SharedExpenseSettlement::query()->where('user_id', $user->id)
            ->whereIn('counterparty_id', $counterpartyIds)->with(['transaction.refundTransaction', 'transaction.account'])->get()
            ->filter(fn (SharedExpenseSettlement $settlement): bool => $this->sharedExpenseSettlementService->settlementEffectCents($settlement) !== 0)
            ->pluck('counterparty_id')->unique();
        $manualReferenceIssues = $manualItems
            ->reject(fn (CreditDebtItem $item): bool => $resolver->consistent($item))
            ->map(fn (CreditDebtItem $item): array => ['reference_uuid' => $item->reference?->uuid])->values();
        $editableAccounts = $this->accessibleAccountsQuery->getEditable($user);
        $transactions = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where(fn ($query) => $query->whereNotNull('shared_expense_counterparty_id')->orWhereNotNull('shared_expense_paid_by'))
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->with(['refundTransaction', 'category:id,uuid,name', 'trackedItem:id,uuid,name,user_id,account_id', 'sharedExpenseCounterparty:id,uuid,name,user_id,account_id', 'recurringOccurrence.recurringEntry:id,uuid,entry_type'])
            ->when($counterpartyUuid, fn ($query) => $query->whereIn('shared_expense_counterparty_id', $filterPartyIds))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->whereLike('description', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->whereLike('name', "%{$search}%"))
                        ->orWhereHas('trackedItem', fn ($itemQuery) => $itemQuery->whereLike('name', "%{$search}%"))
                        ->orWhereHas('sharedExpenseCounterparty', fn ($counterpartyQuery) => $counterpartyQuery->whereLike('name', "%{$search}%"));
                });
            })
            ->orderByDesc('transaction_date')->orderByDesc('id')->get();

        $ledgerEntries = SharedExpenseLedgerEntry::query()
            ->where('user_id', $user->id)
            ->when($entryUuid, fn ($query) => $query->where('uuid', $entryUuid))
            ->whereYear('entry_date', $year)
            ->whereMonth('entry_date', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('entry_date', '<=', $dateTo))
            ->with(['counterparty:id,uuid,name,user_id,account_id', 'occurrence.recurringEntry:id,uuid,entry_type'])
            ->when($counterpartyUuid, fn ($query) => $query->whereIn('counterparty_id', $filterPartyIds))
            ->get();
        $sourceModels = $transactions->concat($ledgerEntries)->keyBy('uuid');
        $ledgerSettlements = app(CounterpartyViewService::class)->sourceSettlements($user->id, $ledgerEntries->pluck('uuid')->all(), 'shared_ledger_share');
        $ledgerEntries = $ledgerEntries->map(fn (SharedExpenseLedgerEntry $entry): array => [
            'source_kind' => 'ledger',
            'can_cancel_payment' => $entry->recurring_entry_occurrence_id !== null,
            'is_cancelled' => ! $entry->isRecorded(),
            'economic_group_uuid' => $entry->occurrence?->uuid,
            'original_effect_cents' => $this->sharedExpenseAllocation->balanceEffectCents(['paid_by' => $entry->paid_by, 'my_share_cents' => $entry->my_share_cents, 'counterparty_share_cents' => $entry->counterparty_share_cents]),
            'common_settlement' => $ledgerSettlements[$entry->uuid] ?? null,
            'uuid' => $entry->uuid, 'date' => $entry->entry_date?->toDateString(), 'title' => $entry->title,
            'counterparty' => ($entry->counterparty === null ? null : app(TrackedItemIdentityService::class)->presentation($entry->counterparty)['label']), 'counterparty_uuid' => $entry->counterparty?->uuid,
            'category' => null, 'reference' => null,
            'origin' => $entry->occurrence === null ? __('ui.shared_expense') : ($entry->occurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.installment_c9bb1db2') : __('ui.recurring_entry_258b334d')),
            'origin_url' => $entry->occurrence?->recurringEntry ? route('recurring-entries.show', ['recurringEntry' => $entry->occurrence->recurringEntry, 'highlight' => $entry->occurrence->uuid]) : null,
            'origin_label' => $entry->occurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.open_installment_plan_2a24ad10') : __('ui.open_recurring_plan_2a2d853f'),
            'amount' => $entry->total_cents / 100, 'currency' => $entry->currency, 'status' => $entry->isRecorded() ? __('ui.posted_bf13fd0a') : 'Annullata',
            'paid_by' => $entry->paid_by, 'my_share_cents' => $entry->my_share_cents,
            'counterparty_share_cents' => $entry->counterparty_share_cents,
            'effect_cents' => ! $entry->isRecorded() ? 0 : $this->sharedExpenseAllocation->balanceEffectCents([
                'paid_by' => $entry->paid_by,
                'my_share_cents' => $entry->my_share_cents,
                'counterparty_share_cents' => $entry->counterparty_share_cents,
            ]),
        ])
            ->filter(function (array $entry) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower(implode(' ', [$entry['title'], $entry['category'], $entry['reference'], $entry['counterparty']])), mb_strtolower($search));
            });

        $transactionSettlements = app(CounterpartyViewService::class)->sourceSettlements($user->id, $transactions->pluck('uuid')->all(), 'shared_transaction_share');
        $recordedEntries = $transactions->map(function (Transaction $transaction) use ($transactionSettlements, $request): array {
            $totalCents = $this->sharedExpenseAllocation->toCents((string) $transaction->amount);
            $allocation = [
                'paid_by' => $transaction->shared_expense_paid_by ?? SharedExpenseAllocation::PAID_BY_SELF,
                'my_share_cents' => $transaction->shared_expense_my_share_cents ?? intdiv($totalCents, 2),
                'counterparty_share_cents' => $transaction->shared_expense_counterparty_share_cents ?? ($totalCents - intdiv($totalCents, 2)),
            ];

            return [
                'common_settlement' => $transactionSettlements[$transaction->uuid] ?? null,
                'economic_group_uuid' => $transaction->recurringOccurrence?->uuid,
                'source_kind' => 'transaction',
                'requires_review' => ! in_array($transaction->status->value, ['confirmed', 'ignored'], true),
                'is_cancelled' => $transaction->status->value === 'ignored',
                'original_effect_cents' => $this->sharedExpenseAllocation->balanceEffectCents($allocation),
                'refund_date' => empty($transactionSettlements[$transaction->uuid]) ? $transaction->refundTransaction?->transaction_date?->toDateString() : null,
                'uuid' => $transaction->uuid, 'date' => $transaction->transaction_date?->toDateString(), 'title' => $transaction->description, 'counterparty' => ($transaction->sharedExpenseCounterparty === null ? null : app(TrackedItemIdentityService::class)->presentation($transaction->sharedExpenseCounterparty)['label']),
                'counterparty_uuid' => $transaction->sharedExpenseCounterparty?->uuid, 'category' => $transaction->category?->name, 'reference' => ($transaction->trackedItem === null ? null : app(TrackedItemIdentityService::class)->presentation($transaction->trackedItem)['label']),
                'origin' => $transaction->recurringOccurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.installment_c9bb1db2') : ($transaction->recurring_entry_occurrence_id ? __('ui.recurring_entry_258b334d') : __('ui.transaction_680db696')),
                'origin_url' => route('transactions.show', ['year' => $transaction->transaction_date?->year, 'month' => $transaction->transaction_date?->month, 'highlight' => $transaction->uuid, 'edit' => $transaction->uuid, 'return_to' => $request->getRequestUri()]),
                'origin_label' => __('ui.open_transaction_ed56aaa7'),
                'amount' => (float) $transaction->amount, 'currency' => $transaction->currency, 'status' => $transaction->refundTransaction !== null ? __('transactions.enums.recurring_transaction_status.refunded') : __('ui.posted_bf13fd0a'),
                'paid_by' => $allocation['paid_by'], 'my_share_cents' => $allocation['my_share_cents'], 'counterparty_share_cents' => $allocation['counterparty_share_cents'],
                'effect_cents' => $totalCents > 0 ? MoneyAmount::proportional($this->sharedExpenseAllocation->balanceEffectCents($allocation), $this->sharedExpenseSettlementService->activeTransactionCents($transaction), $totalCents) : 0,
            ];
        });
        $settlements = $counterpartyIds->isEmpty() ? collect() : SharedExpenseSettlement::query()
            ->where('user_id', $user->id)
            ->whereIn('counterparty_id', $counterpartyIds)
            ->whereYear('settled_at', $year)
            ->whereMonth('settled_at', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('settled_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('settled_at', '<=', $dateTo))
            ->with(['transaction' => fn ($query) => $query->withTrashed(), 'counterparty:id,uuid,name,user_id,account_id', 'transaction.account:id,uuid,user_id,name,currency_code', 'transaction.category:id,uuid,name', 'transaction.refundTransaction'])
            ->when($counterpartyUuid, fn ($query) => $query->whereIn('counterparty_id', $filterPartyIds))
            ->get()
            ->map(fn (SharedExpenseSettlement $settlement): array => [
                'uuid' => $settlement->uuid,
                'date' => $settlement->settled_at?->toDateString(),
                'title' => $settlement->direction === SharedExpenseSettlementService::COUNTERPARTY_TO_ME ? __('ui.repayment_received_e2d49757') : __('ui.repayment_sent_3456298d'),
                'counterparty' => ($settlement->counterparty === null ? null : app(TrackedItemIdentityService::class)->presentation($settlement->counterparty)['label']),
                'counterparty_uuid' => $settlement->counterparty?->uuid,
                'category' => null,
                'reference' => null,
                'origin' => __('ui.repayment_c1c8589b'),
                'origin_url' => $settlement->transaction ? route('transactions.show', ['year' => $settlement->transaction->transaction_date?->year, 'month' => $settlement->transaction->transaction_date?->month, 'highlight' => $settlement->transaction->uuid, 'edit' => $settlement->transaction->uuid]) : null,
                'origin_label' => __('ui.open_transaction_ed56aaa7'),
                'amount' => $settlement->amount_cents / 100,
                'currency' => 'EUR',
                'status' => $this->sharedExpenseSettlementService->settlementEffectCents($settlement) === 0
                    ? __('transactions.enums.status.review_needed')
                    : ($settlement->transaction?->refundTransaction !== null ? __('transactions.enums.recurring_transaction_status.refunded') : 'Registrato'),
                'effect_cents' => $this->sharedExpenseSettlementService->settlementEffectCents($settlement),
                'is_settlement' => true,
                'is_cancelled' => $this->settlementIsInactive($settlement),
                'requires_payment_review' => $this->sharedExpenseSettlementService->settlementEffectCents($settlement) === 0 && ! $this->settlementIsInactive($settlement),
                'settlement' => [
                    'account_uuid' => $settlement->transaction?->account?->uuid,
                    'category_uuid' => $settlement->transaction?->category?->uuid,
                    'reference' => $settlement->transaction?->description,
                    'notes' => $settlement->notes,
                ],
            ]);

        $allEntries = ($entryUuid ? $ledgerEntries : $recordedEntries->concat($ledgerEntries)->concat($settlements));
        $unresolvedGroups = $allEntries->whereNotNull('economic_group_uuid')
            ->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) !== 0)
            ->groupBy('economic_group_uuid')->filter(function ($rows) use ($sourceModels): bool {
                if ($rows->count() < 2 || $rows->every(fn (array $row): bool => ! empty($row['common_settlement']))) {
                    return false;
                }
                if ($rows->pluck('source_kind')->filter()->unique()->count() > 1) {
                    return true;
                }
                try {
                    app(SharedSourceProjector::class)->describe($sourceModels->get($rows->first()['uuid']));

                    return false;
                } catch (ValidationException) {
                    return true;
                }
            })->keys();
        $peopleByUuid = $counterparties->keyBy('uuid');
        $entries = $allEntries->map(function (array $entry) use ($unassignedRepayments, $unresolvedGroups, $peopleByUuid): array {
            $party = $peopleByUuid->get($entry['counterparty_uuid'] ?? '');
            $original = abs((int) ($entry['original_effect_cents'] ?? $entry['effect_cents'] ?? 0));
            $remaining = abs((int) ($entry['effect_cents'] ?? 0));
            $common = $entry['common_settlement'] ?? null;
            $paid = $common === null ? max(0, $original - $remaining) : MoneyAmount::cents($common['paid']);
            $compensated = $common === null ? 0 : MoneyAmount::cents($common['compensated']);
            if ($common !== null) {
                $remaining = MoneyAmount::cents($common['remaining']);
                $original = $paid + $compensated + $remaining;
            }
            $reviewReason = match (true) {
                $party === null => 'reference',
                ! empty($entry['requires_review']) => 'transaction',
                ! empty($entry['requires_payment_review']) => 'payment_document',
                ! empty($entry['economic_group_uuid']) && $unresolvedGroups->contains($entry['economic_group_uuid']) => 'sources',
                empty($entry['is_settlement']) && $entry['currency'] === 'EUR' && $remaining > 0 && $common === null && $unassignedRepayments->contains($party?->id) => 'payments',
                default => null,
            };
            $review = $reviewReason !== null;
            $state = 'review';
            if (! empty($entry['is_cancelled'])) {
                $review = false;
                $remaining = 0;
                $state = 'cancelled';
            } elseif (! $review) {
                if (! empty($entry['is_settlement'])) {
                    $state = 'repayment';
                } elseif ($remaining === 0) {
                    $state = 'settled';
                } elseif ($paid + $compensated > 0) {
                    $state = 'partial';
                } else {
                    $state = ($entry['original_effect_cents'] ?? $entry['effect_cents'] ?? 0) > 0 ? 'receivable' : 'payable';
                }
            }

            return [...$entry, 'review_reason' => $reviewReason, 'display_state' => $state, 'remaining_cents' => $review ? null : $remaining,
                'original_share_cents' => $original, 'repaid_cents' => $paid, 'compensated_cents' => $compensated];
        });
        $isBlocked = fn (array $entry): bool => $entry['display_state'] === 'review' && (in_array($entry['review_reason'], ['sources', 'payment_document'], true) || ($entry['review_reason'] === 'payments' && ! config('counterparty_ledger.public_enabled')));
        $blockedEntries = $entries->filter($isBlocked)->sortByDesc('date')->values();
        $reviewEntries = $entries->where('display_state', 'review')->reject($isBlocked)->sortByDesc('date')->values();
        $filteredEntries = $entries
            ->when($effect === 'pending', fn ($rows) => $rows->filter(fn (array $row): bool => empty($row['is_settlement']) && $row['remaining_cents'] !== null && $row['remaining_cents'] > 0))
            ->when($effect === 'receivable', fn ($rows) => $rows->filter(fn (array $row): bool => empty($row['is_settlement']) && $row['remaining_cents'] > 0 && ($row['original_effect_cents'] ?? 0) > 0))
            ->when($effect === 'payable', fn ($rows) => $rows->filter(fn (array $row): bool => empty($row['is_settlement']) && $row['remaining_cents'] > 0 && ($row['original_effect_cents'] ?? 0) < 0))
            ->when($effect === 'settled', fn ($rows) => $rows->where('display_state', 'settled'))
            ->sortByDesc('date')->values();

        $currentPage = min(LengthAwarePaginator::resolveCurrentPage(), max(1, (int) ceil($filteredEntries->count() / 20)));
        $paginator = (new LengthAwarePaginator(
            $filteredEntries->forPage($currentPage, 20)->values(),
            $filteredEntries->count(),
            20,
            $currentPage,
            ['path' => $request->url()],
        ))->withQueryString();

        $entryCounts = $entries->where('is_settlement', '!=', true)->countBy('counterparty_uuid');
        $counterpartyBalances = $this->counterpartyBalanceService->forUser($user, $counterparties);
        $balances = $counterparties->mapWithKeys(fn (TrackedItem $item): array => [$item->uuid => (int) (collect($counterpartyBalances[$item->uuid] ?? [])->firstWhere('currency', 'EUR')['shared_cents'] ?? 0)])->all();

        return Inertia::render('SharedExpenses', [
            'selectedEntryUuid' => $entryUuid,
            'canPreparePayments' => (bool) config('counterparty_ledger.public_enabled'),
            'referenceOptions' => TrackedItem::query()->ownedBy($user->id)->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
            'filters' => ['month' => $month, 'year' => $year, 'counterparty' => $counterpartyUuid, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'effect' => $effect],
            'counterparties' => $counterparties->filter(fn (TrackedItem $item): bool => $item->is_active && $item->is_shared_expense_counterpart)->map(fn (TrackedItem $item) => ['uuid' => $item->uuid, 'name' => $item->name])->values(),
            'historicalCounterparties' => $counterparties->whereIn('id', $economicPeople)->map(fn (TrackedItem $item): array => app(TrackedItemIdentityService::class)->presentation($item))->map(fn (array $item): array => ['uuid' => $item['uuid'], 'name' => $item['name']])->unique('uuid')->values(),
            'entries' => $paginator->items(),
            'reviewEntries' => $effect === 'pending' ? $reviewEntries : [],
            'blockedEntries' => $effect === 'pending' ? $blockedEntries : [],
            'referenceReviewPeople' => $counterparties->filter(fn (TrackedItem $item): bool => $manualReferenceIssues->contains(fn (array $issue): bool => $issue['reference_uuid'] === $item->uuid))->pluck('uuid'),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
            'counterpartyEntryCounts' => $entryCounts->all(),
            'visibleCounterparties' => $counterparties
                ->whereIn('id', $economicPeople)
                ->map(fn (TrackedItem $item): array => ['uuid' => $item->uuid, 'name' => app(TrackedItemIdentityService::class)->presentation($item)['label']])->values(),
            'relationshipOrigins' => $counterparties->mapWithKeys(function (TrackedItem $item) use ($confirmedManualItems, $sharedPeople, $settlementPeople, $paymentPeople): array {
                $manual = $confirmedManualItems->get($item->id, collect());
                $origins = $manual->map(fn (CreditDebtItem $position): string => 'manual_'.$position->type->value);
                if ($sharedPeople->contains($item->id)) {
                    $origins->push('shared');
                }
                if ($manual->sum('payments_count') > 0 || $settlementPeople->contains($item->id) || $paymentPeople->contains($item->id)) {
                    $origins->push('payments');
                }

                return [$item->uuid => $origins->unique()->values()->all()];
            }),
            'balances' => $balances,
            'commonRelations' => app(CounterpartyViewService::class)->forUser($request->user()),
            'counterpartyBalances' => $counterpartyBalances,
            'settlementOptions' => [
                'accounts' => $editableAccounts->map(fn ($account): array => ['uuid' => $account->uuid, 'name' => $account->name, 'currency' => $account->currency_code])->values(),
                'categories' => $editableAccounts->mapWithKeys(fn ($account): array => [$account->uuid => $this->operationalTransactionCategoryResolver->categoriesForAccount($account)
                    ->filter(fn ($category): bool => $category->is_selectable)
                    ->map(fn ($category): array => ['uuid' => $category->uuid, 'name' => $category->name, 'icon' => $category->icon, 'color' => $category->color, 'parent_id' => $category->parent_id, 'id' => $category->id])
                    ->values()]),
                'references' => $editableAccounts->mapWithKeys(fn ($account): array => [$account->uuid => $this->operationalTransactionCategoryResolver->trackedItemOptionsFromCollection($this->operationalTransactionCategoryResolver->trackedItemsForAccount($account))]),
            ],
        ]);
    }

    public function preparePayments(Request $request, TrackedItem $counterparty, CounterpartyBackfillService $backfill, CounterpartyPaymentService $payments): RedirectResponse
    {
        abort_unless(config('counterparty_ledger.public_enabled') && $counterparty->user_id === $request->user()->id && $counterparty->account_id === null, 404);
        $relation = DB::transaction(function () use ($request, $counterparty, $backfill, $payments): CounterpartyRelation {
            TrackedItem::whereKey($counterparty->id)->lockForUpdate()->firstOrFail();
            $relation = CounterpartyRelation::where('user_id', $request->user()->id)->where('counterparty_id', $counterparty->id)->where('currency_code', 'EUR')->first();
            if ($relation?->activated_at === null) {
                $plan = $backfill->run($request->user(), $counterparty, 'EUR');
                if ($plan['ambiguous'] !== []) {
                    throw ValidationException::withMessages(['payments' => 'Non è ancora possibile collegare questi rimborsi: ci sono riferimenti non confermati o movimenti con collegamenti in conflitto. Correggi prima i movimenti segnalati.']);
                }
                $backfill->run($request->user(), $counterparty, 'EUR', true);
                $relation = CounterpartyRelation::where('user_id', $request->user()->id)->where('counterparty_id', $counterparty->id)->where('currency_code', 'EUR')->firstOrFail();
                $payments->activate($request->user(), $relation, 'shared-payment-review:'.$relation->uuid);
            }

            return $relation;
        });

        return to_route('counterparties.show', ['relation' => $relation->uuid, 'scope' => 'shared']);
    }

    public function resolveReference(ResolveSharedExpenseReferenceRequest $request, string $kind, string $uuid, ResolveSharedExpenseReference $resolver): RedirectResponse
    {
        $resolver->resolve($request->user(), $kind, $uuid, $request->validated('reference_uuid'));

        return back();
    }

    public function destroyLedger(Request $request, SharedExpenseLedgerEntry $ledger, UndoRecurringOccurrenceConversionService $undo): RedirectResponse
    {
        abort_unless($ledger->user_id === $request->user()->id && $ledger->occurrence !== null, 404);
        $entry = $ledger->occurrence->recurringEntry;
        abort_unless($this->accessibleAccountsQuery->canEditAccountId($request->user(), $entry->account_id), 404);
        $uuid = $ledger->occurrence->uuid;
        $undo->undo($ledger->occurrence, externalOnly: true);

        return to_route('recurring-entries.show', ['recurringEntry' => $entry, 'highlight' => $uuid]);
    }

    public function storeCounterparty(StoreSharedExpenseCounterpartyRequest $request): JsonResponse
    {
        if ($request->boolean('draft')) {
            $option = app(PendingTrackedItemService::class)->preview($request->user(), $request->validated(), counterparty: true);

            return response()->json(['uuid' => $option['value'], 'name' => $option['name']]);
        }
        $item = app(TrackedItemResolver::class)->resolve(
            userId: $request->user()->id,
            accountId: null,
            name: $request->validated('name'),
            uuid: $request->validated('tracked_item_uuid'),
            counterparty: true,
        );

        return response()->json(['uuid' => $item->uuid, 'name' => $item->name], 201);
    }

    private function settlementIsInactive(SharedExpenseSettlement $settlement): bool
    {
        $transaction = $settlement->transaction;
        if ($transaction === null) {
            return false;
        }
        if ($transaction->trashed() || $transaction->status->value === 'ignored') {
            return true;
        }

        return $transaction->status->value === 'confirmed' && $transaction->refundTransaction?->status->value === 'confirmed'
            && MoneyAmount::cents((string) $transaction->refundTransaction->amount) >= MoneyAmount::cents((string) $transaction->amount);
    }
}
