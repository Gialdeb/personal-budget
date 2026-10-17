<?php

namespace App\Http\Controllers;

use App\Enums\TransactionKindEnum;
use App\Http\Requests\FinancialIndexRequest;
use App\Http\Requests\Transactions\PreviewBalanceAdjustmentRequest;
use App\Http\Requests\Transactions\PreviewTransactionExchangeRequest;
use App\Http\Requests\Transactions\RefundTransactionRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\TrackedItems\TrackedItemResolver;
use App\Services\TrackedItems\WebsiteLogoService;
use App\Services\Transactions\BalanceAdjustmentService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use App\Services\Transactions\TransactionNavigationService;
use App\Services\UserYearService;
use App\Supports\ManagementContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    public function __construct(
        protected ManagementContextResolver $managementContextResolver,
        protected TransactionNavigationService $transactionNavigationService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected MonthlyTransactionSheetService $monthlyTransactionSheetService,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected SharedAccountTrackedItemCatalogService $sharedAccountTrackedItemCatalogService,
        protected BalanceAdjustmentService $balanceAdjustmentService,
        protected TransactionExchangeSnapshotService $transactionExchangeSnapshotService,
        protected TransactionMutationService $transactionMutationService,
        protected TransactionRefundService $transactionRefundService,
        protected UserYearService $userYearService,
        protected WebsiteLogoService $websiteLogoService
    ) {}

    public function index(FinancialIndexRequest $request): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        ['year' => $year] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $this->transactionNavigationService->resolveLandingMonth(
            $user,
            $year,
            session('dashboard_month')
        );

        $request->route()->setParameter('year', $year);
        $request->route()->setParameter('month', $month);

        return $this->show($request);
    }

    public function show(FinancialIndexRequest $request): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveTransactions($request, $user);
        $highlight = $request->query('highlight');
        $focusedTransaction = is_string($highlight)
            ? Transaction::query()->where('uuid', $highlight)
                ->whereIn('account_id', $this->accessibleAccountsQuery->ids($user))->first()
            : null;

        if ($focusedTransaction?->transaction_date !== null) {
            $year = $focusedTransaction->transaction_date->year;
            $month = $focusedTransaction->transaction_date->month;
        }

        if (! $request->routeIs('transactions.index') && ((int) $request->route('year') !== $year || (int) $request->route('month') !== $month)) {
            return redirect()->route('transactions.show', [
                ...$request->query(),
                'year' => $year,
                'month' => $month,
            ]);
        }

        if ($request->routeIs('transactions.index')) {
            $request->route()->setParameter('year', $year);
            $request->route()->setParameter('month', $month);
            $request->attributes->set('inertia.canonical_url', route('transactions.show', [
                ...$request->query(), 'year' => $year, 'month' => $month,
            ], absolute: false));
        }

        $this->managementContextResolver->persist($user, $year, $month);

        $data = $this->monthlyTransactionSheetService->build($user, $year, $month);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        $template = $request->filled('template')
            ? TransactionTemplate::where('user_id', $user->id)->where('is_active', true)->where('uuid', $request->string('template'))->firstOrFail()
            : null;

        return Inertia::render('transactions/Show', [...$this->showPayload($data, $year, $month), 'transaction_template' => $template?->prefill()]);
    }

    public function updateSharedLedger(UpdateTransactionRequest $request, int $year, int $month, SharedExpenseLedgerEntry $ledger): RedirectResponse
    {
        abort_unless($ledger->user_id === $request->user()->id && $ledger->entry_date->year === $year && $ledger->entry_date->month === $month, 404);
        $this->transactionMutationService->updateSharedLedger($request->user(), $ledger, $request->validated());

        return to_route('transactions.show', ['year' => $year, 'month' => $month])->with('success', __('transactions.flash.updated'));
    }

    public function store(StoreTransactionRequest $request, int $year, int $month): RedirectResponse
    {
        $created = app(TrackedItemIdentityService::class)->withNewReferences([
            'tracked_item_id' => $request->validated('tracked_item_id'),
            'shared_expense_counterparty_id' => $request->validated('shared_expense_counterparty_id'),
        ], function () use ($request): Transaction|SharedExpenseLedgerEntry {
            $template = $request->filled('transaction_template_uuid')
                ? TransactionTemplate::where('user_id', $request->user()->id)->where('is_active', true)->where('uuid', $request->validated('transaction_template_uuid'))->lockForUpdate()->firstOrFail()
                : null;
            if ($template) {
                foreach ($template->prefill()['issues'] as $key => $message) {
                    if ($key === 'shared_counterparty' && ! $request->boolean('is_shared_expense')) {
                        continue;
                    }
                    $field = $key === 'shared_counterparty' ? 'shared_expense_counterparty_id' : $key.'_id';
                    if (! $request->validated($field)) {
                        throw ValidationException::withMessages([$key === 'shared_counterparty' ? 'shared_expense_counterparty_uuid' : $key.'_uuid' => $message]);
                    }
                }
            }
            $created = $this->transactionMutationService->store($request->user(), $request->validated());
            if ($template) {
                $template->forceFill(['usage_count' => $template->usage_count + 1, 'last_used_at' => now()])->save();
            }

            return $created;
        });

        if ($request->filled('payment_occurrence_uuid') && $created instanceof Transaction) {
            return to_route('transactions.show', [
                'year' => $created->transaction_date->year,
                'month' => $created->transaction_date->month,
                'highlight' => $created->uuid,
            ])->with('success', __('transactions.flash.created'));
        }

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.created'));
    }

    public function previewBalanceAdjustment(
        PreviewBalanceAdjustmentRequest $request,
        int $year,
        int $month
    ): JsonResponse {
        $account = $this->accessibleAccountsQuery->findAccessibleAccount(
            $request->user(),
            (int) $request->validated('account_id'),
            true
        );

        abort_unless($account !== null, 422);

        $preview = $this->balanceAdjustmentService->preview(
            $account,
            (string) $request->validated('transaction_date'),
            (float) $request->validated('desired_balance')
        );

        return response()->json([
            'transaction_date' => $request->validated('transaction_date'),
            'account_uuid' => $request->validated('account_uuid'),
            'theoretical_balance_raw' => $preview['theoretical_balance'],
            'desired_balance_raw' => $preview['desired_balance'],
            'adjustment_amount_raw' => $preview['adjustment_amount'],
            'direction' => $preview['direction'],
        ]);
    }

    public function previewExchangeSnapshot(
        PreviewTransactionExchangeRequest $request,
        int $year,
        int $month,
    ): JsonResponse {
        $account = $this->accessibleAccountsQuery->editable($request->user())
            ->where('accounts.uuid', $request->validated('account_uuid'))
            ->with('user:id,base_currency_code')
            ->first();

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                'account_uuid' => __('transactions.validation.account_unavailable'),
            ]);
        }

        $snapshot = $this->transactionExchangeSnapshotService->buildForAccount(
            $account,
            (float) $request->validated('amount'),
            (string) $request->validated('transaction_date'),
        );

        $isMultiCurrency = $snapshot['currency_code'] !== $snapshot['base_currency_code'];

        return response()->json([
            ...$snapshot,
            'amount_raw' => round((float) $request->validated('amount'), 2),
            'converted_base_amount_raw' => (float) $snapshot['converted_base_amount'],
            'is_multi_currency' => $isMultiCurrency,
            'should_preview' => $isMultiCurrency,
        ]);
    }

    public function storeTrackedItemOption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required_without:tracked_item_uuid', 'nullable', 'string', 'max:150'],
            'tracked_item_uuid' => ['nullable', 'uuid'],
            'account_uuid' => ['required', 'uuid'],
            'category_uuid' => ['required', 'uuid'],
            'type_key' => ['required', 'string', 'in:income,expense,bill,debt,saving'],
            'website_url' => ['nullable', 'string', 'max:2048'],
            'context' => ['nullable', 'string', 'in:credit_debt'],
        ]);

        if (($validated['context'] ?? null) === 'credit_debt') {
            abort_unless((bool) config('features.credits_debts.enabled'), 404);
        }

        $account = $this->accessibleAccountsQuery
            ->editable($request->user())
            ->where('accounts.uuid', $validated['account_uuid'])
            ->first();

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                'account_uuid' => __('transactions.validation.account_unavailable'),
            ]);
        }

        $categoryId = Category::query()
            ->where('uuid', $validated['category_uuid'])
            ->value('id');
        $category = $categoryId === null
            ? null
            : $this->operationalTransactionCategoryResolver->findCategoryForAccount($account, (int) $categoryId);

        if (! $category instanceof Category) {
            throw ValidationException::withMessages([
                'category_uuid' => __('transactions.form.errors.categoryRequired'),
            ]);
        }

        $websiteAttributes = [];

        if (filled($validated['website_url'] ?? null)) {
            $websiteUrl = $this->websiteLogoService->normalizeUrl($validated['website_url']);
            $websiteIdentity = $this->websiteLogoService->resolve($websiteUrl, retryUnavailable: true);
            $websiteAttributes = [
                'website_url' => $websiteUrl,
                'website_identity_id' => $websiteIdentity->id,
            ];
        }

        $trackedItem = app(TrackedItemResolver::class)->forAccount(
            actor: $request->user(),
            account: $account,
            name: $validated['name'] ?? null,
            uuid: $validated['tracked_item_uuid'] ?? null,
            category: $category,
            groupKey: $validated['type_key'],
            attributes: [...$websiteAttributes, 'settings' => ['economic_subject' => ($validated['context'] ?? null) === 'credit_debt']],
            personalCatalog: ($validated['context'] ?? null) === 'credit_debt',
        );

        $option = ($validated['context'] ?? null) === 'credit_debt'
            ? $this->operationalTransactionCategoryResolver->trackedItemOptionsFromCollection(
                TrackedItem::query()->ownedBy($request->user()->id)->with(['compatibleCategories', 'websiteIdentity'])->get(),
            )
            : [$this->trackedItemOptionPayloadForAccount($account, $trackedItem)];
        $option = collect($option)->firstWhere('value', $trackedItem->uuid);
        $option['available_account_uuids'] = $this->accessibleAccountsQuery->getEditable($request->user())
            ->filter(fn (Account $candidate): bool => $trackedItem->account_id !== null
                ? $candidate->id === $trackedItem->account_id
                : ! $this->sharedAccountTrackedItemCatalogService->usesAccountScopedCatalog($candidate)
                    && in_array($trackedItem->user_id, $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($candidate), true))
            ->pluck('uuid')->values()->all();

        return response()->json(['item' => $option]);
    }

    public function update(
        UpdateTransactionRequest $request,
        int $year,
        int $month,
        Transaction $transaction
    ): RedirectResponse {
        $transaction = $this->accessibleTransaction($request, $transaction, $year, $month, true);

        if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.opening_balance.mutation_locked'),
            ]);
        }

        if ($transaction->kind === TransactionKindEnum::BALANCE_ADJUSTMENT) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.balance_adjustment_update_blocked'),
            ]);
        }

        if ($transaction->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.update_credit_card_settlement_blocked'),
            ]);
        }

        if ($transaction->recurring_entry_occurrence_id !== null && $transaction->payment_verification_started_at === null) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.recurring_transaction_update_from_plan'),
            ]);
        }

        if ($transaction->recurring_entry_occurrence_id !== null) {
            $data = $request->validated();
            $structuralChanged = (int) $data['account_id'] !== (int) $transaction->account_id
                || (int) ($data['category_id'] ?? 0) !== (int) $transaction->category_id
                || (int) ($data['tracked_item_id'] ?? 0) !== (int) $transaction->tracked_item_id
                || (int) ($data['scope_id'] ?? 0) !== (int) $transaction->scope_id
                || ($data['type_key'] ?? null) !== $transaction->direction->value
                || (bool) ($data['is_shared_expense'] ?? false) !== ($transaction->shared_expense_counterparty_id !== null);
            if ($structuralChanged || $transaction->refundTransaction()->exists() || $transaction->recurringOccurrence?->converted_transaction_id !== $transaction->id) {
                throw ValidationException::withMessages(['transaction' => __('transactions.validation.recurring_transaction_update_from_plan')]);
            }
        }

        $this->transactionMutationService->update(
            $request->user(),
            $transaction,
            $request->validated()
        );

        $updatedDate = CarbonImmutable::parse((string) $request->validated('transaction_date'));

        return to_route('transactions.show', [
            'year' => $updatedDate->year,
            'month' => $updatedDate->month,
        ])->with('success', __('transactions.flash.updated'));
    }

    public function refund(
        RefundTransactionRequest $request,
        int $year,
        int $month,
        Transaction $transaction,
    ): RedirectResponse {
        $transaction = $this->accessibleTransaction($request, $transaction, $year, $month, true);

        if (! $this->canRefundTransaction($transaction)) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.refund_blocked'),
            ]);
        }

        if ($transaction->refundTransaction !== null) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.refund_already_created'),
            ]);
        }

        $refundDate = $request->filled('transaction_date')
            ? CarbonImmutable::parse((string) $request->validated('transaction_date'))
            : CarbonImmutable::parse((string) $transaction->transaction_date?->toDateString());

        $this->userYearService->ensureYearIsOpen($request->user(), $refundDate->year, 'transaction');

        $refund = $this->transactionRefundService->refund($transaction, $request->validated());

        return to_route('transactions.show', [
            'year' => (int) $refund->transaction_date?->year,
            'month' => (int) $refund->transaction_date?->month,
        ])->with('success', __('transactions.flash.refund_created'));
    }

    public function undoRefund(
        Request $request,
        int $year,
        int $month,
        Transaction $transaction,
    ): RedirectResponse {
        $transaction = $this->accessibleTransaction($request, $transaction, $year, $month, true);

        if (! $this->canUndoRefundTransaction($transaction)) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.undo_refund_blocked'),
            ]);
        }

        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');
        $this->transactionRefundService->undo($transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.refund_undone'));
    }

    public function destroy(
        Request $request,
        int $year,
        int $month,
        Transaction $transaction
    ): RedirectResponse {
        $transaction = $this->accessibleTransaction($request, $transaction, $year, $month, true);
        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');

        $this->transactionMutationService->destroy($request->user(), $transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.deleted'));
    }

    public function restore(
        Request $request,
        int $year,
        int $month,
        string $transactionUuid
    ): RedirectResponse {
        $transaction = $this->accessibleTransactionByUuid($request, $transactionUuid, $year, $month, true, true);
        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');

        $this->transactionMutationService->restore($request->user(), $transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.restored'));
    }

    public function forceDestroy(
        Request $request,
        int $year,
        int $month,
        string $transactionUuid
    ): RedirectResponse {
        $transaction = $this->accessibleTransactionByUuid($request, $transactionUuid, $year, $month, true, true);
        $this->userYearService->ensureYearIsOpen($request->user(), $year, 'transaction');

        $this->transactionMutationService->forceDelete($request->user(), $transaction);

        return to_route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ])->with('success', __('transactions.flash.force_deleted'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function showPayload(array $data, int $year, int $month): array
    {
        return [
            'monthlySheet' => $data,
            'transactionsPage' => [
                'year' => $year,
                'month' => $month,
                'month_label' => $data['period']['month_label'],
                'period_label' => mb_strtolower($data['period']['month_label']).' '.$year,
                'records_count' => $data['meta']['transactions_count'],
                'last_recorded_at' => $data['meta']['last_recorded_at'],
            ],
            'year' => $year,
            'month' => $month,
        ];
    }

    protected function accessibleTransaction(
        Request $request,
        Transaction $transaction,
        int $year,
        int $month,
        bool $requireEdit = false,
    ): Transaction {
        abort_unless(
            $this->accessibleAccountsQuery->canViewAccountId($request->user(), (int) $transaction->account_id),
            404
        );

        if (
            $requireEdit
            && ! $this->accessibleAccountsQuery->canEditAccountId($request->user(), (int) $transaction->account_id)
        ) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.transaction_read_only'),
            ]);
        }

        if (
            (int) $transaction->transaction_date?->year !== $year
            || (int) $transaction->transaction_date?->month !== $month
        ) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.transaction_outside_visible_month'),
            ]);
        }

        return $transaction;
    }

    /**
     * @return array<string, mixed>
     */
    protected function trackedItemOptionPayloadForAccount(Account $account, TrackedItem $trackedItem): array
    {
        return collect($this->operationalTransactionCategoryResolver->trackedItemOptionsFromCollection(
            $this->operationalTransactionCategoryResolver->trackedItemsForAccount($account),
            $account,
        ))->firstWhere('value', $trackedItem->uuid) ?? [
            'value' => $trackedItem->uuid,
            'uuid' => $trackedItem->uuid,
            'name' => $trackedItem->name,
            'label' => $trackedItem->name,
            'group_keys' => $trackedItem->settings['transaction_group_keys'] ?? [],
            'category_uuids' => $trackedItem->compatibleCategories->pluck('uuid')->all(),
            'logo_url' => $trackedItem->logoUrl(),
        ];
    }

    protected function resolvedTypeKeyForCategory(Category $category): string
    {
        return $category->group_type?->value
            ?? ($category->direction_type?->value === 'income' ? 'income' : 'expense');
    }

    protected function accessibleTransactionByUuid(
        Request $request,
        string $transactionUuid,
        int $year,
        int $month,
        bool $withTrashed = false,
        bool $requireEdit = false,
    ): Transaction {
        $query = $withTrashed
            ? Transaction::withTrashed()
            : Transaction::query();

        $transaction = $query->where('uuid', $transactionUuid)->firstOrFail();

        return $this->accessibleTransaction($request, $transaction, $year, $month, $requireEdit);
    }

    protected function canRefundTransaction(Transaction $transaction): bool
    {
        if ($transaction->trashed() || $transaction->is_transfer || $transaction->creditDebtPayment()->exists() || $transaction->sharedExpenseSettlement()->exists()) {
            return false;
        }

        return ! in_array($transaction->kind, [
            TransactionKindEnum::OPENING_BALANCE,
            TransactionKindEnum::BALANCE_ADJUSTMENT,
            TransactionKindEnum::SCHEDULED,
            TransactionKindEnum::REFUND,
            TransactionKindEnum::CREDIT_CARD_SETTLEMENT,
        ], true);
    }

    protected function canUndoRefundTransaction(Transaction $transaction): bool
    {
        return ! $transaction->trashed()
            && $transaction->kind === TransactionKindEnum::REFUND
            && $transaction->refunded_transaction_id !== null;
    }
}
