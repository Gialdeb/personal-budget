<?php

namespace App\Http\Controllers;

use App\Http\Requests\SharedExpenses\StoreSharedExpenseCounterpartyRequest;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyViewService;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Support\MoneyAmount;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
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

    public function index(Request $request): Response
    {
        $user = $request->user();
        ['year' => $year, 'month' => $contextMonth] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $contextMonth ?? now()->month;
        $this->managementContextResolver->persist($user, $year, $month);
        $entryUuid = $request->query('entry');
        $search = trim((string) $request->query('search', ''));
        $counterpartyUuid = $request->query('counterparty');
        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();
        $effect = $request->string('effect')->toString();
        $counterparties = TrackedItem::query()->sharedExpenseHistory($user->id)->orderBy('name')->get(['id', 'uuid', 'user_id', 'account_id', 'name', 'is_active', 'is_shared_expense_counterpart']);
        $counterpartyIds = $counterparties->pluck('id');
        $accountIds = $this->accessibleAccountsQuery->get($user)->pluck('id');
        $editableAccounts = $this->accessibleAccountsQuery->getEditable($user);
        $transactions = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereIn('shared_expense_counterparty_id', $counterpartyIds)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_date', '<=', $dateTo))
            ->with(['refundTransaction', 'category:id,uuid,name', 'trackedItem:id,uuid,name', 'sharedExpenseCounterparty:id,uuid,name', 'recurringOccurrence.recurringEntry:id,uuid,entry_type'])
            ->when($counterpartyUuid, fn ($query) => $query->whereHas('sharedExpenseCounterparty', fn ($counterpartyQuery) => $counterpartyQuery->where('uuid', $counterpartyUuid)))
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
            ->whereIn('counterparty_id', $counterpartyIds)
            ->whereYear('entry_date', $year)
            ->whereMonth('entry_date', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('entry_date', '<=', $dateTo))
            ->with(['counterparty:id,uuid,name', 'occurrence.recurringEntry:id,uuid,entry_type'])
            ->when($counterpartyUuid, fn ($query) => $query->whereHas('counterparty', fn ($counterpartyQuery) => $counterpartyQuery->where('uuid', $counterpartyUuid)))
            ->get()
            ->map(fn (SharedExpenseLedgerEntry $entry): array => [
                'can_cancel_payment' => $entry->recurring_entry_occurrence_id !== null,
                'common_settlement' => app(CounterpartyViewService::class)->sourceSettlement($user->id, $entry->uuid, 'shared_ledger_share'),
                'uuid' => $entry->uuid, 'date' => $entry->entry_date?->toDateString(), 'title' => $entry->title,
                'counterparty' => $entry->counterparty?->name, 'counterparty_uuid' => $entry->counterparty?->uuid,
                'category' => null, 'reference' => null,
                'origin' => $entry->occurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.installment_c9bb1db2') : __('ui.recurring_entry_258b334d'),
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

        $recordedEntries = $transactions->map(function (Transaction $transaction) use ($user): array {
            $totalCents = $this->sharedExpenseAllocation->toCents((string) $transaction->amount);
            $allocation = [
                'paid_by' => $transaction->shared_expense_paid_by ?? SharedExpenseAllocation::PAID_BY_SELF,
                'my_share_cents' => $transaction->shared_expense_my_share_cents ?? intdiv($totalCents, 2),
                'counterparty_share_cents' => $transaction->shared_expense_counterparty_share_cents ?? ($totalCents - intdiv($totalCents, 2)),
            ];

            return [
                'common_settlement' => app(CounterpartyViewService::class)->sourceSettlement($user->id, $transaction->uuid, 'shared_transaction_share'),
                'uuid' => $transaction->uuid, 'date' => $transaction->transaction_date?->toDateString(), 'title' => $transaction->description, 'counterparty' => $transaction->sharedExpenseCounterparty?->name,
                'counterparty_uuid' => $transaction->sharedExpenseCounterparty?->uuid, 'category' => $transaction->category?->name, 'reference' => $transaction->trackedItem?->name,
                'origin' => $transaction->recurringOccurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.installment_c9bb1db2') : ($transaction->recurring_entry_occurrence_id ? __('ui.recurring_entry_258b334d') : __('ui.transaction_680db696')),
                'origin_url' => route('transactions.show', ['year' => $transaction->transaction_date?->year, 'month' => $transaction->transaction_date?->month]),
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
            ->with(['counterparty:id,uuid,name', 'transaction.account:id,uuid,user_id,name,currency_code', 'transaction.category:id,uuid,name', 'transaction.refundTransaction'])
            ->when($counterpartyUuid, fn ($query) => $query->whereHas('counterparty', fn ($counterpartyQuery) => $counterpartyQuery->where('uuid', $counterpartyUuid)))
            ->get()
            ->map(fn (SharedExpenseSettlement $settlement): array => [
                'uuid' => $settlement->uuid,
                'date' => $settlement->settled_at?->toDateString(),
                'title' => $settlement->direction === SharedExpenseSettlementService::COUNTERPARTY_TO_ME ? __('ui.repayment_received_e2d49757') : __('ui.repayment_sent_3456298d'),
                'counterparty' => $settlement->counterparty?->name,
                'counterparty_uuid' => $settlement->counterparty?->uuid,
                'category' => null,
                'reference' => null,
                'origin' => __('ui.repayment_c1c8589b'),
                'origin_url' => $settlement->transaction ? route('transactions.show', ['year' => $settlement->transaction->transaction_date?->year, 'month' => $settlement->transaction->transaction_date?->month]) : null,
                'origin_label' => __('ui.open_transaction_ed56aaa7'),
                'amount' => $settlement->amount_cents / 100,
                'currency' => 'EUR',
                'status' => $this->sharedExpenseSettlementService->settlementEffectCents($settlement) === 0
                    ? __('transactions.enums.status.review_needed')
                    : ($settlement->transaction?->refundTransaction !== null ? __('transactions.enums.recurring_transaction_status.refunded') : 'Registrato'),
                'effect_cents' => $this->sharedExpenseSettlementService->settlementEffectCents($settlement),
                'is_settlement' => true,
                'settlement' => [
                    'account_uuid' => $settlement->transaction?->account?->uuid,
                    'category_uuid' => $settlement->transaction?->category?->uuid,
                    'reference' => $settlement->transaction?->description,
                    'notes' => $settlement->notes,
                ],
            ]);

        $filteredEntries = ($entryUuid ? $ledgerEntries : $recordedEntries->concat($ledgerEntries)->concat($settlements))
            ->when($effect === 'receivable', fn ($entries) => $entries->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) > 0))
            ->when($effect === 'payable', fn ($entries) => $entries->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) < 0))
            ->when($effect === 'settled', fn ($entries) => $entries->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) === 0))
            ->sortByDesc('date')->values();

        $currentPage = min(LengthAwarePaginator::resolveCurrentPage(), max(1, (int) ceil($filteredEntries->count() / 20)));
        $paginator = (new LengthAwarePaginator(
            $filteredEntries->forPage($currentPage, 20)->values(),
            $filteredEntries->count(),
            20,
            $currentPage,
            ['path' => $request->url()],
        ))->withQueryString();

        $entryCounts = $filteredEntries->countBy('counterparty_uuid');
        $counterpartyBalances = $this->counterpartyBalanceService->forUser($user, $counterparties);
        $balances = $counterparties->mapWithKeys(fn (TrackedItem $item): array => [$item->uuid => $this->sharedExpenseSettlementService->balanceCents($user, $item)])->all();

        return Inertia::render('SharedExpenses', [
            'selectedEntryUuid' => $entryUuid,
            'filters' => ['month' => $month, 'year' => $year, 'counterparty' => $counterpartyUuid, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'effect' => $effect],
            'counterparties' => $counterparties->filter(fn (TrackedItem $item): bool => $item->is_active && $item->is_shared_expense_counterpart)->map(fn (TrackedItem $item) => ['uuid' => $item->uuid, 'name' => $item->name])->values(),
            'historicalCounterparties' => $counterparties->map(fn (TrackedItem $item): array => ['uuid' => $item->uuid, 'name' => $item->name])->values(),
            'entries' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
            'counterpartyEntryCounts' => $entryCounts->all(),
            'visibleCounterparties' => $counterparties
                ->filter(fn (TrackedItem $item): bool => ($entryCounts[$item->uuid] ?? 0) > 0 || $balances[$item->uuid] !== 0 || collect($counterpartyBalances[$item->uuid] ?? [])->contains(fn (array $balance): bool => $balance['manual_cents'] !== 0 || $balance['shared_cents'] !== 0))
                ->map(fn (TrackedItem $item): array => ['uuid' => $item->uuid, 'name' => $item->name])->values(),
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
        $name = $this->collapseCounterpartyWhitespace(
            (string) $request->validated('name'),
        );
        $normalizedName = $this->normalizeCounterpartyName($name);
        $userId = $request->user()->id;
        $item = TrackedItem::query()
            ->ownedBy($userId)
            ->get()
            ->first(
                fn (TrackedItem $candidate): bool => $this->normalizeCounterpartyName(
                    $candidate->name,
                ) === $normalizedName,
            );

        if (! $item instanceof TrackedItem) {
            $baseSlug = 'shared-expense-'.Str::slug($name);
            $slug = $baseSlug;
            $suffix = 2;

            while (TrackedItem::query()->ownedBy($userId)->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $item = TrackedItem::query()->create([
                'user_id' => $userId,
                'account_id' => null,
                'type' => null,
                'slug' => $slug,
                'name' => $name,
                'parent_id' => null,
                'is_active' => true,
                'is_shared_expense_counterpart' => true,
                'settings' => [],
            ]);
        } elseif (! $item->is_active || ! $item->is_shared_expense_counterpart) {
            $item->update([
                'is_active' => true,
                'is_shared_expense_counterpart' => true,
            ]);
        }

        return response()->json(['uuid' => $item->uuid, 'name' => $item->name], 201);
    }

    private function normalizeCounterpartyName(string $name): string
    {
        return mb_strtolower($this->collapseCounterpartyWhitespace($name));
    }

    private function collapseCounterpartyWhitespace(string $name): string
    {
        return preg_replace('/\\s+/u', ' ', trim($name)) ?? '';
    }
}
