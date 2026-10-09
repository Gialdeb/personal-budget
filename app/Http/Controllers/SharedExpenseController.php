<?php

namespace App\Http\Controllers;

use App\Http\Requests\SharedExpenses\StoreSharedExpenseCounterpartyRequest;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        ['year' => $year, 'month' => $contextMonth] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $contextMonth ?? now()->month;
        $this->managementContextResolver->persist($user, $year, $month);
        $search = trim((string) $request->query('search', ''));
        $counterpartyUuid = $request->query('counterparty');
        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();
        $effect = $request->string('effect')->toString();
        $counterparties = TrackedItem::query()->sharedExpenseCounterparties($user->id)->orderBy('name')->get(['id', 'uuid', 'name']);
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
            ->with(['category:id,uuid,name', 'trackedItem:id,uuid,name', 'sharedExpenseCounterparty:id,uuid,name', 'recurringOccurrence.recurringEntry:id,uuid,entry_type'])
            ->when($counterpartyUuid, fn ($query) => $query->whereHas('sharedExpenseCounterparty', fn ($counterpartyQuery) => $counterpartyQuery->where('uuid', $counterpartyUuid)))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('description', 'ilike', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('trackedItem', fn ($itemQuery) => $itemQuery->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('sharedExpenseCounterparty', fn ($counterpartyQuery) => $counterpartyQuery->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->orderByDesc('transaction_date')->orderByDesc('id')->get();

        $ledgerEntries = SharedExpenseLedgerEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('counterparty_id', $counterpartyIds)
            ->whereYear('entry_date', $year)
            ->whereMonth('entry_date', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('entry_date', '<=', $dateTo))
            ->with(['counterparty:id,uuid,name', 'occurrence.recurringEntry:id,uuid,entry_type'])
            ->when($counterpartyUuid, fn ($query) => $query->whereHas('counterparty', fn ($counterpartyQuery) => $counterpartyQuery->where('uuid', $counterpartyUuid)))
            ->get()
            ->map(fn (SharedExpenseLedgerEntry $entry): array => [
                'uuid' => $entry->uuid, 'date' => $entry->entry_date?->toDateString(), 'title' => $entry->title,
                'counterparty' => $entry->counterparty?->name, 'counterparty_uuid' => $entry->counterparty?->uuid,
                'category' => null, 'reference' => null,
                'origin' => $entry->occurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.installment_c9bb1db2') : __('ui.recurring_entry_258b334d'),
                'origin_url' => $entry->occurrence?->recurringEntry ? route('recurring-entries.show', $entry->occurrence->recurringEntry) : null,
                'origin_label' => $entry->occurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.open_installment_plan_2a24ad10') : __('ui.open_recurring_plan_2a2d853f'),
                'amount' => $entry->total_cents / 100, 'currency' => $entry->currency, 'status' => __('ui.posted_bf13fd0a'),
                'paid_by' => $entry->paid_by, 'my_share_cents' => $entry->my_share_cents,
                'counterparty_share_cents' => $entry->counterparty_share_cents,
                'effect_cents' => $this->sharedExpenseAllocation->balanceEffectCents([
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

        $recordedEntries = $transactions->map(function (Transaction $transaction): array {
            $totalCents = $this->sharedExpenseAllocation->toCents((string) $transaction->amount);
            $allocation = [
                'paid_by' => $transaction->shared_expense_paid_by ?? SharedExpenseAllocation::PAID_BY_SELF,
                'my_share_cents' => $transaction->shared_expense_my_share_cents ?? intdiv($totalCents, 2),
                'counterparty_share_cents' => $transaction->shared_expense_counterparty_share_cents ?? ($totalCents - intdiv($totalCents, 2)),
            ];

            return [
                'uuid' => $transaction->uuid, 'date' => $transaction->transaction_date?->toDateString(), 'title' => $transaction->description, 'counterparty' => $transaction->sharedExpenseCounterparty?->name,
                'counterparty_uuid' => $transaction->sharedExpenseCounterparty?->uuid, 'category' => $transaction->category?->name, 'reference' => $transaction->trackedItem?->name,
                'origin' => $transaction->recurringOccurrence?->recurringEntry?->entry_type?->value === 'installment' ? __('ui.installment_c9bb1db2') : ($transaction->recurring_entry_occurrence_id ? __('ui.recurring_entry_258b334d') : __('ui.transaction_680db696')),
                'origin_url' => route('transactions.show', ['year' => $transaction->transaction_date?->year, 'month' => $transaction->transaction_date?->month]),
                'origin_label' => __('ui.open_transaction_ed56aaa7'),
                'amount' => (float) $transaction->amount, 'currency' => $transaction->currency, 'status' => __('ui.posted_bf13fd0a'),
                'paid_by' => $allocation['paid_by'], 'my_share_cents' => $allocation['my_share_cents'], 'counterparty_share_cents' => $allocation['counterparty_share_cents'],
                'effect_cents' => $this->sharedExpenseAllocation->balanceEffectCents($allocation),
            ];
        });
        $settlements = $counterpartyIds->isEmpty() ? collect() : SharedExpenseSettlement::query()
            ->where('user_id', $user->id)
            ->whereIn('counterparty_id', $counterpartyIds)
            ->whereYear('settled_at', $year)
            ->whereMonth('settled_at', $month)
            ->when($dateFrom, fn ($query) => $query->whereDate('settled_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('settled_at', '<=', $dateTo))
            ->with(['counterparty:id,uuid,name', 'transaction.account:id,uuid,name', 'transaction.category:id,uuid,name'])
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
                'status' => 'Registrato',
                'effect_cents' => $this->sharedExpenseSettlementService->settlementEffectCents($settlement),
                'is_settlement' => true,
                'settlement' => [
                    'account_uuid' => $settlement->transaction?->account?->uuid,
                    'category_uuid' => $settlement->transaction?->category?->uuid,
                    'reference' => $settlement->transaction?->description,
                    'notes' => $settlement->notes,
                ],
            ]);

        return Inertia::render('SharedExpenses', [
            'filters' => ['month' => $month, 'year' => $year, 'counterparty' => $counterpartyUuid, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'effect' => $effect],
            'counterparties' => $counterparties->map(fn (TrackedItem $item) => ['uuid' => $item->uuid, 'name' => $item->name])->values(),
            'entries' => $recordedEntries->concat($ledgerEntries)->concat($settlements)
                ->when($effect === 'receivable', fn ($entries) => $entries->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) > 0))
                ->when($effect === 'payable', fn ($entries) => $entries->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) < 0))
                ->when($effect === 'settled', fn ($entries) => $entries->filter(fn (array $entry): bool => ($entry['effect_cents'] ?? 0) === 0))
                ->sortByDesc('date')->values(),
            'balances' => $counterparties->mapWithKeys(fn (TrackedItem $item): array => [$item->uuid => $this->sharedExpenseSettlementService->balanceCents($user, $item)])->all(),
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
