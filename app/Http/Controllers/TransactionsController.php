<?php

namespace App\Http\Controllers;

use App\Enums\TransactionKindEnum;
use App\Http\Requests\Transactions\PreviewBalanceAdjustmentRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Transactions\BalanceAdjustmentService;
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
        protected BalanceAdjustmentService $balanceAdjustmentService,
        protected TransactionMutationService $transactionMutationService,
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
}
