<?php

namespace App\Http\Controllers;

use App\Enums\TransactionKindEnum;
use App\Http\Requests\Transactions\PreviewBalanceAdjustmentRequest;
use App\Http\Requests\Transactions\RefundTransactionRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use App\Services\Transactions\BalanceAdjustmentService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\Transactions\TransactionMutationService;
use App\Services\Transactions\TransactionNavigationService;
use App\Services\UserYearService;
use App\Supports\ManagementContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        protected TransactionMutationService $transactionMutationService,
        protected TransactionRefundService $transactionRefundService,
        protected UserYearService $userYearService
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        ['year' => $year] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $this->transactionNavigationService->resolveLandingMonth(
            $user,
            $year,
            session('dashboard_month')
        );

        return redirect()->route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function show(Request $request): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveTransactions($request, $user);

        if ((int) $request->route('year') !== $year || (int) $request->route('month') !== $month) {
            return redirect()->route('transactions.show', [
                'year' => $year,
                'month' => $month,
            ]);
        }

        $this->managementContextResolver->persist($user, $year, $month);

        $data = $this->monthlyTransactionSheetService->build($user, $year, $month);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('transactions/Show', $this->showPayload($data, $year, $month));
    }

    public function store(StoreTransactionRequest $request, int $year, int $month): RedirectResponse
    {
        $this->transactionMutationService->store(
            $request->user(),
            $request->validated()
        );

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

    public function storeTrackedItemOption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'account_uuid' => ['required', 'uuid'],
            'category_uuid' => ['required', 'uuid'],
            'type_key' => ['required', 'string', 'in:income,expense,bill,debt,saving'],
        ]);

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

        if ($this->resolvedTypeKeyForCategory($category) !== $validated['type_key']) {
            throw ValidationException::withMessages([
                'type_key' => __('transactions.form.errors.invalidTypeForTrackedItem'),
            ]);
        }

        $trackedItem = DB::transaction(function () use ($request, $account, $category, $validated): TrackedItem {
            $slug = Str::slug((string) $validated['name']);

            if ($this->sharedAccountTrackedItemCatalogService->usesAccountScopedCatalog($account)) {
                $trackedItem = TrackedItem::query()
                    ->sharedForAccount($account->id)
                    ->where('slug', $slug)
                    ->first();

                if (! $trackedItem instanceof TrackedItem) {
                    $trackedItem = TrackedItem::query()->create([
                        'user_id' => $account->user_id,
                        'account_id' => $account->id,
                        'parent_id' => null,
                        'name' => (string) $validated['name'],
                        'slug' => $slug,
                        'type' => null,
                        'is_active' => true,
                        'settings' => [],
                    ]);
                }

                $settings = is_array($trackedItem->settings) ? $trackedItem->settings : [];
                $groupKeys = collect($settings['transaction_group_keys'] ?? [])
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->push((string) $validated['type_key'])
                    ->unique()
                    ->values()
                    ->all();
                $categoryUuids = collect($settings['transaction_category_uuids'] ?? [])
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->push($category->uuid)
                    ->unique()
                    ->values()
                    ->all();

                $trackedItem->forceFill([
                    'name' => $trackedItem->name ?: (string) $validated['name'],
                    'is_active' => true,
                    'settings' => [
                        ...$settings,
                        'transaction_group_keys' => $groupKeys,
                        'transaction_category_uuids' => $categoryUuids,
                        'shared_account_uuid' => $account->uuid,
                    ],
                ])->save();

                $trackedItem->compatibleCategories()->syncWithoutDetaching([
                    (int) $category->id,
                ]);

                return $trackedItem->fresh(['compatibleCategories']);
            }

            $trackedItem = TrackedItem::query()
                ->ownedBy($request->user()->id)
                ->where('slug', $slug)
                ->first();

            if (! $trackedItem instanceof TrackedItem) {
                $trackedItem = TrackedItem::query()->create([
                    'user_id' => $request->user()->id,
                    'account_id' => null,
                    'parent_id' => null,
                    'name' => (string) $validated['name'],
                    'slug' => $slug,
                    'type' => null,
                    'is_active' => true,
                    'settings' => [],
                ]);
            }

            $settings = is_array($trackedItem->settings) ? $trackedItem->settings : [];
            $groupKeys = collect($settings['transaction_group_keys'] ?? [])
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->push((string) $validated['type_key'])
                ->unique()
                ->values()
                ->all();
            $categoryUuids = collect($settings['transaction_category_uuids'] ?? [])
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->push($category->uuid)
                ->unique()
                ->values()
                ->all();

            $trackedItem->forceFill([
                'name' => $trackedItem->name ?: (string) $validated['name'],
                'is_active' => true,
                'settings' => [
                    ...$settings,
                    'transaction_group_keys' => $groupKeys,
                    'transaction_category_uuids' => $categoryUuids,
                ],
            ])->save();

            $trackedItem->compatibleCategories()->syncWithoutDetaching([
                (int) $category->id,
            ]);

            return $trackedItem->fresh(['compatibleCategories']);
        });

        return response()->json([
            'item' => $this->trackedItemOptionPayloadForAccount($account, $trackedItem),
        ]);
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
        return $this->operationalTransactionCategoryResolver
            ->trackedItemOptionsFromCollection(
                collect([
                    $this->operationalTransactionCategoryResolver->findTrackedItemForAccount(
                        $account,
                        (int) $trackedItem->id,
                    ) ?? $trackedItem,
                ]),
            )[0];
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
        if ($transaction->trashed() || $transaction->is_transfer) {
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
